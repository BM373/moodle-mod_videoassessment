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
     * Pick a MediaRecorder mimeType the running browser can actually
     * encode. iOS Safari and macOS Safari do not support `video/webm`,
     * so passing it to RecordRTC silently fails the underlying
     * MediaRecorder.start() — the recorder stays in state 'inactive'
     * and a subsequent tap that the UI thinks is "Stop" starts a
     * second recording instead, leaving the learner unable to submit.
     * Fall back to `video/mp4` (Safari) when webm is unsupported, and
     * to whatever the browser will accept as a last resort.
     *
     * @returns {string} A mimeType string that MediaRecorder accepts.
     */
    function pickSupportedVideoMime() {
        if (typeof window.MediaRecorder === 'undefined'
            || typeof MediaRecorder.isTypeSupported !== 'function') {
            return 'video/webm';
        }
        var candidates = [
            'video/webm',
            'video/webm;codecs=vp8,opus',
            'video/mp4',
            'video/mp4;codecs=h264,aac'
        ];
        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported(candidates[i])) {
                return candidates[i];
            }
        }
        return 'video/webm';
    }

    /**
     * Initialise the recording UI and logic.
     */
    function init() {
        var btnStart = document.querySelector('#btn-start-recording');
        var btnPause = document.querySelector('#btn-pause-recording');
        var recorder;
        var stream;
        var previewVideo = null;
        var autoStopTimer = null;
        // Track recording state ourselves rather than asking the
        // underlying MediaRecorder. On iOS Safari the recorder can fail
        // to start (webm fallback) and getState() returns 'inactive',
        // which used to leave the Start/Stop toggle stuck on Start.
        var isRecording = false;

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
         * Attach the live camera stream to a visible <video> element so
         * the learner can see themselves while recording.
         *
         * Item #3 of the 2026-04 fix programme: Brendon and Matt both
         * reported the recorder captured video "in the background" with
         * no on-screen preview. The MediaStream was handed to RecordRTC
         * but never attached to a <video srcObject>, leaving
         * #record-content-div empty. The preview is muted (so the live
         * microphone does not echo through the speakers) and uses
         * playsinline so iOS Safari renders it inline rather than going
         * fullscreen.
         *
         * @param {MediaStream} mediaStream
         */
        function showPreview(mediaStream) {
            var container = document.querySelector('#record-content-div');
            if (!container) {
                return;
            }
            if (!previewVideo) {
                previewVideo = document.createElement('video');
                previewVideo.className = 'vam-record-preview';
                previewVideo.muted = true;
                previewVideo.defaultMuted = true;
                previewVideo.autoplay = true;
                previewVideo.setAttribute('playsinline', '');
                previewVideo.setAttribute('aria-label', M.str.videoassessment.startrecoding);
            }
            previewVideo.srcObject = mediaStream;
            // Clear any previous preview without innerHTML (avoids the
            // XSS-prone assignment; the container only ever holds our
            // own <video> element anyway).
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }
            container.appendChild(previewVideo);
            var playpromise = previewVideo.play();
            if (playpromise && typeof playpromise.catch === 'function') {
                // Autoplay can be rejected before user interaction; the
                // preview still renders the first frame, so swallow it.
                playpromise.catch(function() {
                    return undefined;
                });
            }
        }

        /**
         * Stop the camera and remove the live preview element. Releasing
         * the tracks turns off the webcam indicator light.
         */
        function teardownPreview() {
            if (stream) {
                stream.getTracks().forEach(function(track) {
                    track.stop();
                });
            }
            if (previewVideo) {
                previewVideo.srcObject = null;
                if (previewVideo.parentNode) {
                    previewVideo.parentNode.removeChild(previewVideo);
                }
                previewVideo = null;
            }
        }

        /**
         *
         */
        function finishRecording() {
            if (!recorder || !isRecording) {
                return;
            }
            isRecording = false;
            clearAutoStop();
            recorder.stopRecording(function() {
                var blob = recorder.getBlob();
                // RecordRTC's recorder.mimeType is undefined for the
                // MediaStreamRecorder backend used in modern browsers.
                // Fall back to the blob's own type (set by the browser
                // when the MediaRecorder finalises the chunks), and
                // finally to the type we asked the recorder to produce.
                var mimeType = (blob && blob.type)
                    ? blob.type
                    : (recorder.mimeType || 'video/webm');
                teardownPreview();
                // Guard against 0-byte blobs (a sign that
                // MediaRecorder.start() was rejected silently). Surface
                // an alert so the learner is not stuck on a screen
                // where "stop" appears to do nothing.
                if (!blob || !blob.size) {
                    window.alert(M.str.videoassessment.errorcapturingmedia);
                    return;
                }
                uploader.upload(blob, mimeType);
            });
        }

        btnStart.addEventListener('click', function() {
            if (isRecording) {
                finishRecording();
                return;
            }

            media.captureUserMedia(function(userStream) {
                stream = userStream;
                // Show the live camera feed BEFORE recording starts so
                // the learner can frame themselves.
                showPreview(stream);
                var mimeType = pickSupportedVideoMime();
                recorder = new RecordRTC(stream, {
                    type: 'video',
                    mimeType: mimeType,
                    disableLogs: false,
                    // Flush a chunk every second so MediaRecorder
                    // produces a non-empty blob on Safari/iOS even if
                    // the final stop event is delayed.
                    timeSlice: 1000
                });

                recorder.startRecording();
                isRecording = true;
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
