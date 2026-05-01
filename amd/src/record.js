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
 * In-browser recording controller.
 *
 * @module     mod_videoassessment/record
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global RecordRTC */
define([
    'mod_videoassessment/media',
    'mod_videoassessment/uploader'
], function(media, uploader) {

    /**
     * Recording length cap, kept in sync with
     * \mod_videoassessment\recording::max_length_seconds in PHP. Item #3
     * of the 2026-04 fix programme. Browser-side enforcement so that
     * MediaRecorder always stops on time even if the learner walks away.
     */
    var MAX_LENGTH_MS = 120 * 1000;

    /**
     * Initialise the recording UI and logic.
     */
    function init() {
        var btnStart = document.querySelector('#btn-start-recording');
        var btnPause = document.querySelector('#btn-pause-recording');
        var recorder;
        var stream;
        var autoStopTimer = null;

        /**
         *
         */
        function clearAutoStop() {
            if (autoStopTimer !== null) {
                clearTimeout(autoStopTimer);
                autoStopTimer = null;
            }
        }

        /**
         *
         */
        function finishRecording() {
            if (!recorder) {
                return;
            }
            clearAutoStop();
            recorder.stopRecording(function() {
                var blob = recorder.getBlob();
                uploader.upload(blob, recorder.mimeType);
            });
        }

        btnStart.addEventListener('click', function() {
            if (recorder && recorder.getState() === 'recording') {
                finishRecording();
                return;
            }

            media.captureUserMedia(function(userStream) {
                stream = userStream;
                recorder = new RecordRTC(stream, {
                    type: 'video',
                    mimeType: 'video/webm',
                    disableLogs: false
                });

                recorder.startRecording();
                btnPause.style.display = '';
                btnStart.textContent = M.str.videoassessment.stoprecording;

                // Item #3 of the 2026-04 fix programme: enforce a 2-minute
                // recording cap so MediaRecorder cannot accumulate
                // unbounded blobs that fail to upload.
                clearAutoStop();
                autoStopTimer = window.setTimeout(finishRecording, MAX_LENGTH_MS);
            }, function(err) {
                window.alert(M.str.videoassessment.errorcapturingmedia + ' ' + err.message);
            });
        });

        btnPause.addEventListener('click', function() {
            if (!recorder) {
                return;
            }
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
