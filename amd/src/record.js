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
     * encode.
     *
     * iOS Safari MUST get `video/mp4` (H.264/AAC) — its
     * MediaRecorder.isTypeSupported('video/webm') can return truthy
     * but start() then fails silently, the recorder stays in state
     * 'inactive', no chunks are emitted, and stopRecording() returns
     * a 0-byte blob to the upload path. Try MP4 first on Safari and
     * skip webm entirely on iOS; fall back to an empty string (which
     * lets MediaRecorder choose its own default) rather than the
     * known-broken 'video/webm' so any future Safari quirk degrades
     * to "the browser picks" instead of "we force a bad codec".
     *
     * @returns {string} A mimeType string MediaRecorder accepts (may be empty).
     */
    function pickSupportedVideoMime() {
        if (typeof window.MediaRecorder === 'undefined'
            || typeof MediaRecorder.isTypeSupported !== 'function') {
            return '';
        }
        var ua = navigator.userAgent || '';
        var isIOS = /iPhone|iPad|iPod/i.test(ua);
        var isSafari = /^((?!chrome|android).)*safari/i.test(ua);
        // On iOS skip webm entirely — isTypeSupported lies about it.
        // On macOS Safari prefer mp4 but allow webm as a last resort.
        // On Chromium/Firefox keep the historical webm-first order
        // because their mp4 support is more recent and uneven.
        var candidates;
        if (isIOS) {
            candidates = ['video/mp4;codecs=h264,aac', 'video/mp4'];
        } else if (isSafari) {
            candidates = [
                'video/mp4;codecs=h264,aac',
                'video/mp4',
                'video/webm;codecs=vp8,opus',
                'video/webm'
            ];
        } else {
            candidates = [
                'video/webm;codecs=vp8,opus',
                'video/webm',
                'video/mp4;codecs=h264,aac',
                'video/mp4'
            ];
        }
        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported(candidates[i])) {
                return candidates[i];
            }
        }
        // Last resort: let MediaRecorder pick its own default rather
        // than passing a literal we already know does not work.
        return '';
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
         * Reset the UI to its pre-recording state so the learner can
         * retry instead of being stranded on a "Stop recording" button
         * that no longer does anything.
         */
        function resetRecordingUi() {
            isRecording = false;
            clearAutoStop();
            teardownPreview();
            if (btnStart) {
                btnStart.textContent = M.str.videoassessment.startrecoding;
            }
            if (btnPause) {
                btnPause.style.display = 'none';
                btnPause.textContent = M.str.videoassessment.pause;
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
                    : (recorder.mimeType || '');
                teardownPreview();
                // Guard against 0-byte blobs (a sign that
                // MediaRecorder.start() was rejected silently). Surface
                // an alert WITH diagnostic context so the learner can
                // report what happened, and reset the UI so they can
                // retry without reloading the page.
                if (!blob || !blob.size) {
                    var detail = blob
                        ? (blob.size + ' bytes, type=' + (blob.type || 'n/a'))
                        : 'null';
                    window.alert(
                        M.str.videoassessment.errorcapturingmedia
                        + ' (blob ' + detail + ', mime=' + (mimeType || 'auto') + ')'
                    );
                    resetRecordingUi();
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

                // Safari "silent start" watchdog: if MediaRecorder did
                // not actually enter the 'recording' state within
                // ~2.5 s, the codec was rejected at runtime even though
                // isTypeSupported() said yes. Surface that instead of
                // letting the learner record two minutes of nothing.
                window.setTimeout(function() {
                    if (!isRecording || !recorder) {
                        return;
                    }
                    var state = (typeof recorder.getState === 'function')
                        ? recorder.getState()
                        : null;
                    if (state && state !== 'recording') {
                        window.alert(
                            M.str.videoassessment.errorcapturingmedia
                            + ' (recorder failed to start: ' + mimeType + ', state=' + state + ')'
                        );
                        resetRecordingUi();
                    }
                }, 2500);
            }, function(err) {
                window.alert(M.str.videoassessment.errorcapturingmedia + ' ' + (err && err.message ? err.message : ''));
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
