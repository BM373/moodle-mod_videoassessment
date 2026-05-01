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

/**
 * Video assessment
 *
 * @package
 * @module     mod_videoassessment/record
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global RecordRTC */
define([
    'mod_videoassessment/media',
    'mod_videoassessment/uploader'
], function (media, uploader) {

    /**
     * Initializes the recording UI and logic.
     */
    function init() {
        const btnStart = document.querySelector('#btn-start-recording');
        const btnPause = document.querySelector('#btn-pause-recording');
        let recorder, stream;

        btnStart.addEventListener('click', function () {
            if (recorder && recorder.getState() === 'recording') {
                recorder.stopRecording(() => {
                    const blob = recorder.getBlob();
                    uploader.upload(blob, recorder.mimeType);
                });
                return;
            }

            media.captureUserMedia((userStream) => {
                stream = userStream;
                recorder = new RecordRTC(stream, {
                    type: 'video',
                    mimeType: 'video/webm',
                    disableLogs: false
                });

                recorder.startRecording();
                btnPause.style.display = '';
                btnStart.textContent = M.str.videoassessment.stoprecording;
            }, (err) => {
                alert(M.str.videoassessment.errorcapturingmedia + ' ' + err.message);
            });
        });

        btnPause.addEventListener('click', function () {
            if (!recorder) { return; }
            if (btnPause.textContent === M.str.videoassessment.pause) {
                recorder.pauseRecording();
                btnPause.textContent = M.str.videoassessment.resumerecording;
            } else {
                recorder.resumeRecording();
                btnPause.textContent = M.str.videoassessment.pause;
            }
        });
    }

    return {
        reCord: init
    };
});