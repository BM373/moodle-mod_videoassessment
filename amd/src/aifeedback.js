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
 * AI speech feedback page: in-browser audio recorder and processing trigger.
 *
 * The recorder captures audio only (no camera) with MediaRecorder and puts
 * the result into the upload form's file field, so a recording and an
 * uploaded file travel the same way. After the page reloads with a queued
 * attempt, this module asks the server to process it at once and reloads
 * again when the feedback is ready, instead of waiting for cron.
 *
 * @module     mod_videoassessment/aifeedback
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    var STATUS_QUEUED = 0;
    var STATUS_PROCESSING = 1;
    var POLL_MS = 5000;
    var POLL_LIMIT = 120;

    /**
     * Pick a recording format the browser supports.
     *
     * Chrome and Firefox record WebM/Opus, Safari records MP4/AAC; both
     * are accepted by the transcription API.
     *
     * @return {Object} mimeType ('' lets the browser choose) and file extension.
     */
    var pickFormat = function() {
        var candidates = [
            {mime: 'audio/webm;codecs=opus', ext: 'webm'},
            {mime: 'audio/webm', ext: 'webm'},
            {mime: 'audio/mp4', ext: 'm4a'},
            {mime: 'audio/ogg;codecs=opus', ext: 'ogg'}
        ];
        for (var i = 0; i < candidates.length; i++) {
            if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(candidates[i].mime)) {
                return candidates[i];
            }
        }
        return {mime: '', ext: 'webm'};
    };

    /**
     * Format seconds as m:ss.
     *
     * @param {Number} seconds Elapsed seconds.
     * @return {String}
     */
    var clock = function(seconds) {
        var s = seconds % 60;
        return Math.floor(seconds / 60) + ':' + (s < 10 ? '0' : '') + s;
    };

    /**
     * Wire up the recorder.
     *
     * @param {HTMLElement} root The recorder card.
     * @param {Number} maxSeconds Recording stops by itself after this.
     */
    var initRecorder = function(root, maxSeconds) {
        var form = root.querySelector('[data-region="aifb-form"]');
        var mic = root.querySelector('[data-region="aifb-mic"]');
        var noMic = root.querySelector('[data-region="aifb-nomic"]');
        var recordBtn = root.querySelector('[data-action="aifb-record"]');
        var stopBtn = root.querySelector('[data-action="aifb-stop"]');
        var timer = root.querySelector('[data-region="aifb-timer"]');
        var preview = root.querySelector('[data-region="aifb-preview"]');
        var previewAudio = root.querySelector('[data-region="aifb-preview-audio"]');
        var sendBtn = root.querySelector('[data-action="aifb-send"]');
        var againBtn = root.querySelector('[data-action="aifb-again"]');
        var fileInput = root.querySelector('[data-region="aifb-file"]');
        var duration = root.querySelector('[data-region="aifb-duration"]');
        var sending = root.querySelector('[data-region="aifb-sending"]');

        var showSending = function() {
            sending.classList.remove('d-none');
            root.querySelectorAll('button').forEach(function(b) {
                b.disabled = true;
            });
        };
        form.addEventListener('submit', function() {
            showSending();
        });

        var canRecord = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia &&
            window.MediaRecorder && window.DataTransfer);
        if (!canRecord) {
            noMic.classList.remove('d-none');
            return;
        }
        mic.classList.remove('d-none');

        var format = pickFormat();
        var recorder = null;
        var chunks = [];
        var blob = null;
        var seconds = 0;
        var tick = null;

        var stopTracks = function(stream) {
            stream.getTracks().forEach(function(track) {
                track.stop();
            });
        };

        var stop = function() {
            if (recorder && recorder.state !== 'inactive') {
                recorder.stop();
            }
        };

        recordBtn.addEventListener('click', function() {
            navigator.mediaDevices.getUserMedia({audio: true, video: false}).then(function(stream) {
                chunks = [];
                blob = null;
                seconds = 0;
                recorder = format.mime ? new window.MediaRecorder(stream, {mimeType: format.mime})
                    : new window.MediaRecorder(stream);
                recorder.addEventListener('dataavailable', function(e) {
                    if (e.data && e.data.size) {
                        chunks.push(e.data);
                    }
                });
                recorder.addEventListener('stop', function() {
                    clearInterval(tick);
                    stopTracks(stream);
                    blob = new Blob(chunks, {type: recorder.mimeType || format.mime || 'audio/webm'});
                    previewAudio.src = URL.createObjectURL(blob);
                    preview.classList.remove('d-none');
                    stopBtn.classList.add('d-none');
                    recordBtn.classList.add('d-none');
                    timer.classList.add('d-none');
                });
                recorder.start(1000);
                recordBtn.classList.add('d-none');
                preview.classList.add('d-none');
                stopBtn.classList.remove('d-none');
                timer.classList.remove('d-none');
                timer.textContent = clock(0);
                tick = setInterval(function() {
                    seconds++;
                    timer.textContent = clock(seconds);
                    if (seconds >= maxSeconds) {
                        stop();
                    }
                }, 1000);
                return stream;
            }).catch(function() {
                mic.classList.add('d-none');
                noMic.classList.remove('d-none');
            });
        });

        stopBtn.addEventListener('click', stop);

        againBtn.addEventListener('click', function() {
            blob = null;
            preview.classList.add('d-none');
            recordBtn.classList.remove('d-none');
        });

        sendBtn.addEventListener('click', function() {
            if (!blob) {
                return;
            }
            var file = new File([blob], 'recording.' + format.ext, {type: blob.type});
            var transfer = new window.DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;
            duration.value = String(seconds);
            showSending();
            form.submit();
        });
    };

    /**
     * Reload once an attempt has left the queue.
     *
     * @param {Number} attemptId The attempt.
     */
    var poll = function(attemptId) {
        var count = 0;
        var check = function() {
            count++;
            Ajax.call([{
                methodname: 'mod_videoassessment_get_ai_feedback_status',
                args: {attemptid: attemptId}
            }])[0].then(function(result) {
                if (result.status !== STATUS_QUEUED && result.status !== STATUS_PROCESSING) {
                    window.location.reload();
                } else if (count < POLL_LIMIT) {
                    setTimeout(check, POLL_MS);
                }
                return result;
            }).catch(function() {
                if (count < POLL_LIMIT) {
                    setTimeout(check, POLL_MS);
                }
            });
        };
        setTimeout(check, POLL_MS);
    };

    /**
     * Start processing a queued attempt now; fall back to polling.
     *
     * @param {Number} attemptId The attempt.
     */
    var process = function(attemptId) {
        Ajax.call([{
            methodname: 'mod_videoassessment_process_ai_feedback',
            args: {attemptid: attemptId}
        }])[0].then(function(result) {
            if (result.status === STATUS_QUEUED || result.status === STATUS_PROCESSING) {
                // Another worker has it; wait for that one.
                poll(attemptId);
            } else {
                window.location.reload();
            }
            return result;
        }).catch(function() {
            poll(attemptId);
        });
    };

    return {
        /**
         * Initialise the page.
         *
         * @param {Object} config pending: [{id, status}], process: start queued attempts, maxseconds.
         */
        init: function(config) {
            var root = document.querySelector('[data-region="aifb-recorder"]');
            if (root) {
                initRecorder(root, config.maxseconds || 600);
            }
            (config.pending || []).forEach(function(item) {
                if (config.process && item.status === STATUS_QUEUED) {
                    process(item.id);
                } else {
                    poll(item.id);
                }
            });
        }
    };
});
