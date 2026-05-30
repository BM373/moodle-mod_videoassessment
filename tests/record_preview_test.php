<?php
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
 * In-browser recorder live-preview regression tests.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Static-contract tests for the recorder live preview (Item #3).
 *
 * Reviewer reproduction: Brendon (iPhone 16 Pro, Chrome + Safari, and
 * desktop) and Matt (Mac Firefox) both reported that the recorder
 * captures video "in the background" but the user cannot SEE
 * themselves while recording — there was no live camera preview. The
 * MediaStream returned by getUserMedia was handed straight to
 * RecordRTC and never attached to a visible <video> element, so
 * #record-content-div stayed empty.
 *
 * The fix lives in amd/src/record.js. A faithful browser test would
 * need a fake-media-device Selenium/Playwright profile, so to keep the
 * test fast and deterministic we pin the JS contract statically: the
 * source module must (a) create a <video> preview element, (b) attach
 * the live MediaStream via srcObject, (c) drop it into
 * #record-content-div, and (d) tear it down (stop the camera tracks
 * and remove the element) when recording finishes.
 */
final class record_preview_test extends \basic_testcase {
    /**
     * Read the record.js AMD source from the plugin tree.
     *
     * @return string Source with line endings normalised to LF.
     */
    private function read_record_js(): string {
        $path = __DIR__ . '/../amd/src/record.js';
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /**
     * The recorder must create a <video> element for the live preview.
     *
     * @coversNothing
     */
    public function test_record_js_creates_preview_video_element(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            "~createElement\\(\\s*['\"]video['\"]\\s*\\)~",
            $js,
            'record.js must create a <video> element for the live preview.'
        );
    }

    /**
     * The captured MediaStream must be attached to the preview via
     * srcObject so the learner sees themselves while recording.
     *
     * @coversNothing
     */
    public function test_record_js_attaches_stream_via_srcobject(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'srcObject',
            $js,
            'record.js must set video.srcObject to the live MediaStream.'
        );
    }

    /**
     * The preview must be muted so the live mic does not echo back
     * through the speakers while recording.
     *
     * @coversNothing
     */
    public function test_record_js_preview_is_muted(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~\.muted\s*=\s*true~',
            $js,
            'record.js must mute the preview <video> to avoid audio feedback.'
        );
    }

    /**
     * The preview element must be appended to #record-content-div.
     *
     * @coversNothing
     */
    public function test_record_js_appends_preview_to_container(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'record-content-div',
            $js,
            'record.js must place the preview inside #record-content-div.'
        );
    }

    /**
     * When recording finishes the camera tracks must be stopped (so the
     * webcam light goes off) and the preview removed from the DOM.
     *
     * @coversNothing
     */
    public function test_record_js_tears_down_preview_and_tracks(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'getTracks',
            $js,
            'record.js must stop the MediaStream tracks when recording ends.'
        );
        $this->assertMatchesRegularExpression(
            '~\.stop\(\)~',
            $js,
            'record.js must call track.stop() to release the camera.'
        );
    }

    /**
     * iOS Safari cannot encode `video/webm`; passing it to RecordRTC
     * leaves MediaRecorder in state 'inactive' so the recorder never
     * stops cleanly and "録画停止" appears to do nothing. The module
     * must pick a mimeType the running browser supports before handing
     * one to RecordRTC.
     *
     * @coversNothing
     */
    public function test_record_js_picks_supported_video_mime(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'MediaRecorder.isTypeSupported',
            $js,
            'record.js must probe MediaRecorder.isTypeSupported() so a '
                . 'Safari/iOS-compatible mimeType is picked at runtime.'
        );
        $this->assertStringContainsString(
            'video/mp4',
            $js,
            'record.js must list video/mp4 as a Safari fallback.'
        );
    }

    /**
     * Stop/Start must be driven by an own boolean state instead of
     * RecordRTC.getState(), which on iOS Safari returns "inactive"
     * after a silent webm-fallback failure and used to trap the user
     * in a Start/Stop loop with no upload.
     *
     * @coversNothing
     */
    public function test_record_js_tracks_recording_state_locally(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~\bisRecording\b~',
            $js,
            'record.js must keep its own isRecording flag instead of '
                . 'relying on RecordRTC.getState() for the Stop toggle.'
        );
    }

    /**
     * MediaRecorder.start() needs a non-zero timeSlice on iOS Safari for
     * ondataavailable to flush before stop(); without it the final blob
     * can be 0 bytes.
     *
     * @coversNothing
     */
    public function test_record_js_uses_timeslice_for_safari(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~timeSlice\s*:\s*\d+~',
            $js,
            'record.js must request a periodic ondataavailable timeslice '
                . 'so the iOS recorder produces a complete blob.'
        );
    }

    /**
     * A 0-byte blob (the failure mode on Safari without timeSlice) must
     * surface as a visible error rather than uploading silently.
     *
     * @coversNothing
     */
    public function test_record_js_guards_against_empty_blob(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~!blob\.size|blob\.size\s*===\s*0|blob\.size\s*<\s*1~',
            $js,
            'record.js must reject an empty recording blob and notify '
                . 'the learner instead of uploading 0 bytes.'
        );
    }

    /**
     * iOS Safari's isTypeSupported() lies about `video/webm`, so the
     * mimeType picker must try MP4 BEFORE webm on Safari/iOS and
     * skip webm entirely on iOS. Pin this ordering so a future
     * refactor cannot regress us back to silent webm failures.
     *
     * @coversNothing
     */
    public function test_record_js_prefers_mp4_on_ios_safari(): void {
        $js = $this->read_record_js();
        // The Safari/iOS UA branches must list mp4 before webm.
        $this->assertStringContainsString(
            'iPhone|iPad|iPod',
            $js,
            'record.js must detect iOS via a UA test and special-case '
                . 'mimeType selection there.'
        );
        // Extract the iOS branch's candidates array literal.
        $iospattern = "~isIOS\\s*\\)\\s*\\{[^{}]*?candidates\\s*=\\s*\\[([^\\]]*)\\]~";
        $this->assertSame(
            1,
            preg_match($iospattern, $js, $iosmatch),
            'pickSupportedVideoMime must have an isIOS branch with a candidates array.'
        );
        $this->assertStringContainsString(
            'video/mp4',
            $iosmatch[1],
            'The iOS branch of pickSupportedVideoMime must use mp4.'
        );
        $this->assertStringNotContainsString(
            'video/webm',
            $iosmatch[1],
            'The iOS branch must NOT list video/webm — Safari mobile '
                . 'cannot actually encode it even when isTypeSupported '
                . 'returns truthy.'
        );
        // Extract the macOS Safari branch's candidates array literal.
        $safaripattern = "~isSafari\\s*\\)\\s*\\{[^{}]*?candidates\\s*=\\s*\\[([^\\]]*)\\]~";
        $this->assertSame(
            1,
            preg_match($safaripattern, $js, $safarimatch),
            'pickSupportedVideoMime must have a macOS Safari branch with a candidates array.'
        );
        $mp4pos = strpos($safarimatch[1], 'video/mp4');
        $webmpos = strpos($safarimatch[1], 'video/webm');
        $this->assertNotFalse($mp4pos);
        $this->assertNotFalse($webmpos);
        $this->assertLessThan(
            $webmpos,
            $mp4pos,
            'The macOS Safari branch of pickSupportedVideoMime must '
                . 'try mp4 before webm.'
        );
    }

    /**
     * If MediaRecorder reports a non-'recording' state ~2.5s after
     * startRecording(), the encoder was rejected silently. The
     * watchdog surfaces this rather than leaving the learner to
     * record two minutes of nothing.
     *
     * @coversNothing
     */
    public function test_record_js_has_silent_start_watchdog(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~recorder failed to start~',
            $js,
            'record.js must include a watchdog that alerts when '
                . 'MediaRecorder failed to enter the recording state.'
        );
        $this->assertMatchesRegularExpression(
            "~getState\\(\\)~",
            $js,
            'The watchdog must consult recorder.getState() to detect '
                . 'the silent-failure case.'
        );
    }

    /**
     * After any failure path that does not upload (empty blob,
     * watchdog hit), the UI must be reset so the learner can retry
     * without reloading the page.
     *
     * @coversNothing
     */
    public function test_record_js_resets_ui_after_failure(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~resetRecordingUi\s*\(\s*\)~',
            $js,
            'record.js must reset the recording UI after a failure so '
                . 'the Start/Stop button reverts to its initial label.'
        );
        $this->assertMatchesRegularExpression(
            '~btnStart\.textContent\s*=\s*M\.str\.videoassessment\.startrecoding~',
            $js,
            'resetRecordingUi must restore the Start label on the '
                . 'primary record button.'
        );
    }

    /**
     * iOS Safari's MediaRecorder video path is not reliable, so the
     * recorder must delegate to the iOS native camera app via
     * <input type="file" capture> on iOS and bypass the
     * RecordRTC/MediaRecorder flow entirely.
     *
     * @coversNothing
     */
    public function test_record_js_delegates_to_native_camera_on_ios(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~setupIosNativeCamera\s*\(~',
            $js,
            'record.js must define a setupIosNativeCamera helper that '
                . 'installs the iOS camera overlay on init.'
        );
        $this->assertMatchesRegularExpression(
            "~setAttribute\\(\\s*['\"]capture['\"]~",
            $js,
            'The iOS path must use an <input type=file capture> so '
                . 'Safari/iOS opens the native camera UI.'
        );
        $this->assertMatchesRegularExpression(
            "~accept\\s*=\\s*['\"]video/\\*['\"]~",
            $js,
            'The iOS capture input must accept video/* so the camera '
                . 'app records video rather than offering a photo.'
        );
    }

    /**
     * The iOS overlay must be installed at init time (not lazily on
     * each click) so the <input type=file capture> stays in the DOM
     * across the camera transition — that is what makes Safari fire
     * the change event reliably with the recorded mp4.
     *
     * @coversNothing
     */
    public function test_record_js_keeps_ios_input_persistent(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~iosCapture\s*&&\s*btnStart[^{]*\{[^}]*setupIosNativeCamera~s',
            $js,
            'setupIosNativeCamera must be invoked once at init time '
                . 'on iOS — not on every click — so the file input '
                . 'persists across the native-camera transition.'
        );
        $this->assertMatchesRegularExpression(
            "~position\\s*=\\s*['\"]absolute['\"]~",
            $js,
            'The iOS overlay must be position:absolute over the Start '
                . 'button so the tap is recognised as a user gesture '
                . 'on the file input itself.'
        );
    }
}
