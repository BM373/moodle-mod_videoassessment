// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/* eslint-disable no-restricted-properties, no-alert */
/**
 * Video assessment
 *
 * @module mod_videoassessment/uploader
 * @package
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['mod_videoassessment/utils'], function(utils) {

    /**
     * Show the full-screen "uploading…" overlay with a spinning
     * indicator centred over a translucent grey backdrop. Marked as
     * an aria-live region so assistive technology announces it.
     */
    function showUploadOverlay() {
        if (document.getElementById('vam-upload-overlay')) {
            return;
        }
        const label = (M && M.str && M.str.videoassessment
            && M.str.videoassessment.uploadingvideonotice)
            || 'Uploading…';
        const overlay = document.createElement('div');
        overlay.id = 'vam-upload-overlay';
        overlay.className = 'vam-upload-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.setAttribute('aria-busy', 'true');

        const box = document.createElement('div');
        box.className = 'vam-upload-overlay-box';

        const spinner = document.createElement('span');
        spinner.className = 'vam-upload-overlay-spinner';
        // Font Awesome shipped by every Moodle theme; same icon the
        // form-side spinner used before this overlay. Build the icon
        // with createElement rather than innerHTML so a future change
        // to this file cannot accidentally introduce XSS via the label.
        const icon = document.createElement('i');
        icon.className = 'fa fa-circle-o-notch fa-spin fa-3x';
        icon.setAttribute('aria-hidden', 'true');
        spinner.appendChild(icon);

        const message = document.createElement('div');
        message.className = 'vam-upload-overlay-message';
        message.textContent = label;

        box.appendChild(spinner);
        box.appendChild(message);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    /**
     * Remove the upload overlay if it is currently displayed.
     */
    function hideUploadOverlay() {
        const overlay = document.getElementById('vam-upload-overlay');
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
    }

    return {

        /**
         * Uploads a recorded blob to the Moodle server.
         * @param {Blob} blob
         * @param {string} mimeType
         */
        upload(blob, mimeType) {
            // Defend against an undefined or codec-suffixed mimeType so
            // the upload never falls over on a Blob the browser fills
            // in differently (e.g. RecordRTC's MediaStreamRecorder
            // backend produces "video/webm;codecs=vp8,opus" while the
            // Cisco/Safari path can leave it empty). Strip everything
            // after the first ";" so the extension lookup gets a bare
            // "webm" / "mp4" instead of "webm;codecs=vp8,opus".
            const safeMime = (mimeType || (blob && blob.type) || '')
                .split(';')[0]
                .trim();
            // The submission form's id is "mform" on the desktop layout
            // and "mobileform" on the mobile layout (mod_form sets one
            // or the other based on the device class), so the upload
            // path must look up both. Without this fallback the iOS
            // native-camera path throws TypeError when FormData(null)
            // is constructed because the desktop selector misses.
            const form = document.querySelector('#mform')
                || document.querySelector('#mobileform')
                || document.querySelector('form.mform');
            if (!form) {
                window.alert(
                    ((M && M.str && M.str.videoassessment
                        && M.str.videoassessment.errorcapturingmedia)
                        || 'Error capturing media.')
                    + ' (submission form not found)'
                );
                return;
            }
            const formData = new FormData(form);
            // Normalise the file extension we ask the helper to use.
            // iOS native camera reports the recording as
            // `video/quicktime`; the literal "quicktime" subtype is 9
            // chars which, combined with the server-side
            // `{unix-timestamp}{N}.{ext}` template, overflows
            // videoassessment_videos.tmpname (varchar(20)) and the DB
            // INSERT throws "Data too long for column 'tmpname'".
            // Map the well-known long subtypes to their conventional
            // short extensions and cap any future weird subtype at
            // eight chars so the temp name always fits.
            const subtype = (safeMime.split('/')[1] || 'mp4').trim();
            const extmap = {
                'quicktime': 'mov',
                'x-matroska': 'mkv',
                'mpeg': 'mpg'
            };
            const ext = (extmap[subtype] || subtype).slice(0, 8) || 'mp4';
            const fileName = utils.getFileName(ext);
            formData.append('isRecordVideo', 1);
            formData.append(safeMime.startsWith('audio') ? 'audio' : 'video', blob, fileName);
            // The PHP handler reads the original filename via
            // optional_param('video-filename', ...) and persists it as
            // videoassessment_videos.originalname (NOT NULL). Without
            // this field the DB insert throws and the upload silently
            // dies inside video_data_add().
            formData.append('video-filename', fileName);

            const url = form.getAttribute('action');
            const id = formData.get('id');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            const failureMessage = (M && M.str && M.str.videoassessment
                && M.str.videoassessment.errorcapturingmedia)
                || 'Error capturing media.';
            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) {
                    // Surface a visible error rather than leaving the
                    // learner staring at a frozen "Stop recording"
                    // screen.
                    hideUploadOverlay();
                    window.alert(failureMessage + ' (HTTP ' + xhr.status + ')');
                    return;
                }
                // The server returns JSON ({"action": ""}) on success.
                // If we get an HTML page instead — typically the form
                // re-rendered because get_data() returned null (PHP
                // upload size limit exceeded, sesskey mismatch, etc.)
                // — surface a snippet of the response so the silent
                // failure becomes visible.
                let parsed;
                try {
                    parsed = JSON.parse(xhr.responseText || '');
                } catch (e) {
                    parsed = null;
                }
                if (parsed && typeof parsed.action !== 'undefined') {
                    // Leave the overlay visible while the page navigates
                    // away — hiding it now causes a brief blank screen
                    // before the redirect lands. The FFmpeg conversion
                    // runs in the background on the server (item #3),
                    // so this response arrives as soon as the file is
                    // saved instead of after the whole conversion.
                    window.location.href = `${url}?id=${id}`;
                    // Watchdog: if the navigation stalls, do not strand
                    // the learner behind an endless spinner — reveal
                    // the page again after 15s. The timer dies with
                    // the page when the navigation succeeds.
                    setTimeout(hideUploadOverlay, 15000);
                    return;
                }
                hideUploadOverlay();
                const snippet = (xhr.responseText || '')
                    .replace(/<[^>]+>/g, '')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .slice(0, 200);
                window.alert(
                    failureMessage
                    + ' (upload not accepted by the server'
                    + (snippet ? ': ' + snippet : '')
                    + ')'
                );
            };
            xhr.onerror = () => {
                hideUploadOverlay();
                window.alert(failureMessage);
            };
            // Show the "uploading…" spinner just before the network
            // request leaves the browser. iOS native-camera uploads can
            // take several seconds for a two-minute clip; without this
            // feedback the page just sits silent and the learner taps
            // again or navigates away.
            showUploadOverlay();
            xhr.send(formData);
        }
    };
});
