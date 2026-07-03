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
    /**
     * The submission form is rendered with id="mform" on the desktop
     * layout and id="mobileform" on the mobile layout. The uploader
     * must look up both before constructing FormData; otherwise the
     * iOS native-camera path crashes with
     * `TypeError: Argument 1 ('form') to the FormData constructor
     * must be an instance of HTMLFormElement` because the desktop
     * selector misses and FormData(null) is illegal.
     *
     * @coversNothing
     */
    /**
     * The server returns {"action": "..."} JSON on success and a full
     * HTML page on failure (form re-render, exception, upload-size
     * rejection). The uploader must reject anything that does not
     * parse as JSON so silent failures are visible rather than being
     * mistaken for a successful redirect.
     *
     * @coversNothing
     */
    /**
     * The record-video flow uploads as $_FILES['video']; validation
     * must not require $_FILES['mobilevideo'] on that path. Without
     * this skip, the iOS / browser-record POST fails validation,
     * get_data() returns null, the form silently re-renders, and the
     * recording is dropped on the floor.
     *
     * @coversNothing
     */
    /**
     * Moodle's moodleform::_validate_files() walks $_FILES on the
     * first validation pass and unset()s any entry whose registered
     * form element type is not 'file' — and the 'video' element is
     * registered as a 'filemanager'. So the RecordRTC / iOS XHR file
     * is destroyed by `$form->get_data()` before the upload branch
     * can read it. view_upload_video() must snapshot the upload BEFORE
     * the form is constructed and use the snapshot inside the
     * isRecordVideo branch.
     *
     * @coversNothing
     */
    public function test_view_upload_video_snapshots_files_before_form(): void {
        $src = file_get_contents(__DIR__ . '/../classes/va.php');
        // Snapshot variable must be initialised before $form = new ...
        $snapshotpos = strpos($src, '$recordedfile = null;');
        $formpos = strpos($src, '$form = new form\\video_upload(');
        $this->assertNotFalse(
            $snapshotpos,
            'view_upload_video must snapshot $_FILES into a local '
                . '$recordedfile variable so moodleform::_validate_files() '
                . 'cannot consume it before the record branch runs.'
        );
        $this->assertNotFalse($formpos);
        $this->assertLessThan(
            $formpos,
            $snapshotpos,
            'The $_FILES snapshot must happen BEFORE the moodleform is '
                . 'constructed, otherwise validate_files() unsets the '
                . 'entry on the first validation pass.'
        );
        // And the record branch must use the snapshot, not $_FILES.
        $recordbranch = substr(
            $src,
            strpos($src, "optional_param('isRecordVideo', 0, PARAM_INT) == 1) {")
        );
        $recordbranch = substr($recordbranch, 0, strpos($recordbranch, 'echo json_encode'));
        $this->assertStringContainsString(
            '$recordedfile',
            $recordbranch,
            'The isRecordVideo branch must read the snapshotted file, '
                . 'not $_FILES["video"] (which is destroyed by validation).'
        );
        $this->assertStringNotContainsString(
            "\$_FILES['video']",
            $recordbranch,
            'The isRecordVideo branch must not read $_FILES["video"] '
                . 'directly — that array is already unset by the time '
                . 'get_data() returns.'
        );
    }

    /**
     * Static-contract guard for the validation skip introduced when
     * the record-video XHR flow began crashing on the form's
     * mobilevideo requirement. See the docblock above for the full
     * rationale.
     *
     * @coversNothing
     */
    public function test_video_upload_validation_skips_mobile_on_record_path(): void {
        $src = file_get_contents(__DIR__ . '/../classes/form/video_upload.php');
        $this->assertMatchesRegularExpression(
            "~optional_param\\(\\s*['\"]isRecordVideo['\"]~",
            $src,
            'video_upload::validation must check isRecordVideo and skip '
                . 'the mobilevideo requirement when the record flow is '
                . 'active.'
        );
    }

    /**
     * iOS native camera reports recordings as video/quicktime; the
     * literal subtype "quicktime" is 9 chars and overflows the
     * videoassessment_videos.tmpname column (varchar(20)) when the
     * server builds `{timestamp}{N}.{ext}`. uploader.js must normalise
     * the extension (quicktime → mov, x-matroska → mkv, …) and cap
     * any other subtype at 8 chars so the temp filename always fits.
     *
     * @coversNothing
     */
    public function test_uploader_normalises_long_mime_extensions(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/uploader.js');
        $this->assertMatchesRegularExpression(
            "~quicktime['\"]?\\s*:\\s*['\"]mov~",
            $js,
            'uploader.js must map quicktime -> mov so the iOS native '
                . 'camera recording fits in tmpname.'
        );
        $this->assertMatchesRegularExpression(
            '~\.slice\s*\(\s*0\s*,\s*8\s*\)~',
            $js,
            'uploader.js must cap unknown subtypes at 8 chars so any '
                . 'future weird mime cannot overflow tmpname.'
        );
    }

    /**
     * Static-contract guard for the mobile-portrait assess tabs added
     * to give a tall recording the full viewport without burying the
     * rubric. The AMD module must build a .vam-assess-tabs bar and
     * toggle body classes; the CSS must hide one section per active
     * tab inside the phone-portrait media query.
     *
     * @coversNothing
     */
    public function test_assess_mobile_tabs_contract(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/assess_mobile_tabs.js');
        $this->assertStringContainsString(
            'vam-assess-tabs',
            $js,
            'assess_mobile_tabs.js must build a .vam-assess-tabs '
                . 'element so view.css can style it.'
        );
        $this->assertStringContainsString(
            'vam-assess-tab-video-active',
            $js,
            'assess_mobile_tabs.js must toggle the '
                . 'vam-assess-tab-video-active body class.'
        );
        $this->assertStringContainsString(
            'vam-assess-tab-grading-active',
            $js,
            'assess_mobile_tabs.js must toggle the '
                . 'vam-assess-tab-grading-active body class.'
        );
        $this->assertStringContainsString(
            'tabvideo',
            $js,
            'assess_mobile_tabs.js must look up M.str.videoassessment'
                . '.tabvideo for the Video tab label.'
        );
        $css = file_get_contents(__DIR__ . '/../assess.css');
        $this->assertMatchesRegularExpression(
            '~@media[^{]*max-width:\s*768px[\s\S]*?vam-assess-tab-video-active[^{]*\.gradingform~',
            $css,
            'assess.css must hide the rubric inside the phone-portrait '
                . 'media query when the video tab is active.'
        );
        $this->assertMatchesRegularExpression(
            '~@media[^{]*max-width:\s*768px[\s\S]*?vam-assess-tab-grading-active[^{]*\.assess-form-videos~',
            $css,
            'assess.css must hide the video band inside the phone-'
                . 'portrait media query when the grading tab is active.'
        );
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString(
            "'mod_videoassessment/assess_mobile_tabs'",
            $vaphp,
            'view_assess must queue the assess_mobile_tabs AMD module '
                . 'so the tab bar is wired up on the assess action.'
        );
    }

    /**
     * Playback-continuity contract for the assess tabs (item #7,
     * 2026-06 feedback round, revised 2026-07): graders watch/listen
     * to the recording while they fill in the rubric, so the grading
     * tab must keep the video VISIBLE as a height-capped compact band
     * above the rubric (not parked, not display:none), and nothing may
     * call pause() on the players during a tab switch.
     *
     * @coversNothing
     */
    public function test_assess_tabs_keep_audio_playing_contract(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/assess_mobile_tabs.js');
        $this->assertStringNotContainsString(
            'pauseAllVideos',
            $js,
            'assess_mobile_tabs.js must not pause playback on tab '
                . 'switch — audio keeps playing while the user grades.'
        );
        $this->assertStringNotContainsString(
            ".setProperty('display'",
            $js,
            'assess_mobile_tabs.js must not hide panes via '
                . 'display:none — some mobile browsers stall media in '
                . 'display:none subtrees. Park the pane off-screen.'
        );
        $this->assertStringContainsString(
            "'-10000px'",
            $js,
            'assess_mobile_tabs.js must park the hidden rubric pane '
                . 'off-screen (video-tab view) rather than display:none.'
        );
        $css = file_get_contents(__DIR__ . '/../assess.css');
        $this->assertDoesNotMatchRegularExpression(
            '~vam-assess-tab-grading-active\s+\.assess-form-videos\s*\{[^}]*display\s*:\s*none~',
            $css,
            'assess.css must not display:none the video band when the '
                . 'grading tab is active — playback must continue.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~vam-assess-tab-grading-active\s+\.assess-form-videos\s*\{[^}]*left\s*:\s*-10000px~',
            $css,
            'assess.css must not park the video band off-screen when '
                . 'the grading tab is active — the 2026-07 feedback '
                . 'requires the video to stay visible above the rubric.'
        );
        $this->assertMatchesRegularExpression(
            '~vam-assess-tab-grading-active\s+\.assess-form-videos\s+\.video-wrap\s*\{[^}]*max-height\s*:\s*3[0-9]vh~',
            $css,
            'assess.css must height-cap the recording box (.video-wrap) '
                . 'above the rubric when the grading tab is active, so a '
                . 'portrait recording cannot bury the criteria.'
        );
    }

    /**
     * Static-contract guard for the "uploading…" overlay: the uploader
     * must build a `.vam-upload-overlay` element with a spinner and
     * the localised `uploadingvideonotice` label before sending the
     * XHR, and tear it down on failure paths.
     *
     * @coversNothing
     */
    public function test_uploader_shows_upload_overlay(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/uploader.js');
        $this->assertMatchesRegularExpression(
            '~showUploadOverlay\s*\(\s*\)~',
            $js,
            'uploader.js must define and call showUploadOverlay() to '
                . 'cover the page while the upload XHR is in flight.'
        );
        $this->assertStringContainsString(
            'vam-upload-overlay',
            $js,
            'uploader.js must place a .vam-upload-overlay element so '
                . 'view.css can backdrop and centre it.'
        );
        $this->assertStringContainsString(
            'uploadingvideonotice',
            $js,
            'uploader.js must label the overlay with the localised '
                . 'uploadingvideonotice string (visible to the learner).'
        );
        $this->assertMatchesRegularExpression(
            '~hideUploadOverlay\s*\(\s*\)~',
            $js,
            'uploader.js must hide the overlay on the failure / non-2xx '
                . 'paths so the learner is not stuck on a grey screen.'
        );
        // The overlay must not assign to .innerHTML — build with
        // createElement so a future string change cannot introduce
        // XSS. Match an assignment specifically so the explanatory
        // comment ("rather than innerHTML") does not trip the guard.
        $this->assertDoesNotMatchRegularExpression(
            '~\.innerHTML\s*=~',
            $js,
            'uploader.js must build overlay nodes via createElement, '
                . 'not innerHTML, to avoid XSS exposure on the label.'
        );
        // The CSS rules backing the overlay must ship in view.css.
        $css = file_get_contents(__DIR__ . '/../view.css');
        $this->assertStringContainsString(
            '.vam-upload-overlay',
            $css,
            'view.css must style .vam-upload-overlay with the grey '
                . 'backdrop and centred card the JS overlay expects.'
        );
        // The strings_for_js call must pre-load the label so M.str.* is defined.
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString(
            "'uploadingvideonotice'",
            $vaphp,
            'classes/va.php must pre-load uploadingvideonotice via '
                . '$PAGE->requires->strings_for_js() so the overlay '
                . 'label resolves on first paint.'
        );
    }

    /**
     * Static-contract guard for the JSON response validation added
     * after the iOS upload silently redirected on non-JSON failures.
     *
     * @coversNothing
     */
    public function test_uploader_validates_json_response(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/uploader.js');
        $this->assertMatchesRegularExpression(
            '~JSON\.parse\s*\(\s*xhr\.responseText~',
            $js,
            'uploader.js must parse xhr.responseText as JSON so a '
                . 'silent failure (HTML form re-render with status 200) '
                . 'is detected instead of redirecting blindly.'
        );
        $this->assertStringContainsString(
            'upload not accepted by the server',
            $js,
            'uploader.js must surface a clear failure message when '
                . 'the response is not valid JSON.'
        );
    }

    /**
     * Static-contract guard for the dual lookup of #mform / #mobileform
     * so the iOS upload can reach the mobile-rendered form id.
     *
     * @coversNothing
     */
    public function test_uploader_finds_mobile_and_desktop_form(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/uploader.js');
        $this->assertStringContainsString(
            '#mform',
            $js,
            'uploader.js must look up the desktop submission form id.'
        );
        $this->assertStringContainsString(
            '#mobileform',
            $js,
            'uploader.js must also look up the mobileform id so the '
                . 'iOS native-camera path can post on a mobile UA.'
        );
    }

    /**
     * Static-contract guard for the persistent iOS overlay so the
     * file input does not disappear between camera open and change.
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
