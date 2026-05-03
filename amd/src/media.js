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

/* eslint-disable promise/always-return */
/**
 * Video assessment
 *
 * @module mod_videoassessment/media
 * @package
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Adds event listeners to detect when the media stream ends.
     * @param {MediaStream} stream
     * @param {Function} callback
     */
    function addStreamStopListener(stream, callback) {
        stream.getTracks().forEach(function(track) {
            ['ended', 'inactive'].forEach(event => {
                track.addEventListener(event, () => {
                    callback();
                    callback = () => undefined;
                });
            });
        });
    }

    /**
     * Captures audio and video from user's media devices.
     *
     * Audio constraints are explicit so the browser does not silently
     * pick a virtual/null device. echoCancellation +
     * noiseSuppression + autoGainControl mirror the defaults used by
     * Google Meet / Zoom and are the values that work reliably on
     * macOS Chrome with the built-in microphone. Without these flags
     * some setups produce a flat -91 dB stream that looks like
     * "audio captured" to MediaRecorder but is actually silence.
     *
     * @param {Function} success
     * @param {Function} error
     */
    function captureUserMedia(success, error) {
        var constraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
                sampleRate: 48000,
                channelCount: 1
            },
            video: {
                width: {ideal: 1280},
                height: {ideal: 720}
            }
        };
        navigator.mediaDevices.getUserMedia(constraints)
            .then(function(stream) {
                // Defensive log: if the audio track came back muted or
                // disabled, surface it to the developer console so the
                // recorder doesn't silently produce a video-only blob.
                stream.getAudioTracks().forEach(function(track) {
                    if (track.muted || !track.enabled) {
                        // eslint-disable-next-line no-console
                        console.warn(
                            'mod_videoassessment: audio track returned ' +
                                'in muted/disabled state — recording will ' +
                                'have no sound. Track:', track.label, track.getSettings()
                        );
                    }
                });
                success(stream);
            })
            .catch(function(err) {
                error(err);
            });
    }

    return {
        captureUserMedia,
        addStreamStopListener
    };
});