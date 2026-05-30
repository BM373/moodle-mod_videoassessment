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
            const safeMime = (mimeType || (blob && blob.type) || 'video/webm')
                .split(';')[0]
                .trim();
            const formData = new FormData(document.querySelector('#mform'));
            const fileName = utils.getFileName(safeMime.split('/')[1]);
            formData.append('isRecordVideo', 1);
            formData.append(safeMime.startsWith('audio') ? 'audio' : 'video', blob, fileName);
            // The PHP handler reads the original filename via
            // optional_param('video-filename', ...) and persists it as
            // videoassessment_videos.originalname (NOT NULL). Without
            // this field the DB insert throws and the upload silently
            // dies inside video_data_add().
            formData.append('video-filename', fileName);

            const url = document.querySelector('#mform').getAttribute('action');
            const id = formData.get('id');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            const failureMessage = (M && M.str && M.str.videoassessment
                && M.str.videoassessment.errorcapturingmedia)
                || 'Error capturing media.';
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    window.location.href = `${url}?id=${id}`;
                } else {
                    // Surface a visible error rather than leaving the
                    // learner staring at a frozen "Stop recording"
                    // screen. Without this the iPhone fix programme
                    // ("録画停止" did not advance the form) only made
                    // the silent failure mode louder.
                    window.alert(failureMessage + ' (HTTP ' + xhr.status + ')');
                }
            };
            xhr.onerror = () => {
                window.alert(failureMessage);
            };
            xhr.send(formData);
        }
    };
});