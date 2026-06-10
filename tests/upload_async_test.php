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
 * Tests for the background-conversion upload flow (item #3).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Item #3 of the 2026-06 feedback round: the "never-ending uploading
 * circle".
 *
 * The single-video upload paths ran the whole FFmpeg conversion
 * synchronously inside the upload POST, so the XHR response — and with
 * it the "uploading…" overlay — was held open for the entire
 * multi-minute conversion. The fix dispatches the conversion to
 * bulkupload/async.php (the mechanism the bulk uploader has always
 * used) and responds as soon as the file is saved. These tests pin:
 *
 * - the async callback URL / token construction,
 * - the converting-state detector that backs the learner-facing
 *   "video is being converted" notice,
 * - static wiring contracts (no synchronous convert() in the upload
 *   handler, async.php stays a cookieless token endpoint, the JS
 *   watchdog exists).
 */
final class upload_async_test extends \advanced_testcase {
    /**
     * Create a course + activity and return useful handles.
     *
     * @return array{va:va,activity:\stdClass,course:\stdClass,cm:\stdClass,context:\context_module}
     */
    private function make_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(
            'videoassessment',
            ['course' => $course->id]
        );
        $cm = get_coursemodule_from_instance(
            'videoassessment',
            $activity->id,
            $course->id,
            false,
            MUST_EXIST
        );
        $context = \context_module::instance($cm->id);
        $va = new va($context, $cm, $course);
        return compact('va', 'activity', 'course', 'cm', 'context');
    }

    /**
     * Insert a video row + association simulating "upload finished,
     * conversion still running" (filename not yet filled in by
     * video_data_update()).
     *
     * @param \stdClass $activity Activity record.
     * @param int $userid Associated user.
     * @param string $tmpname Temp name ('Youtube' for external links).
     * @param string $filename Converted filename ('' while converting).
     * @return int Video id.
     */
    private function insert_video_with_assoc(
        \stdClass $activity,
        int $userid,
        string $tmpname,
        string $filename
    ): int {
        global $DB;
        $videoid = $DB->insert_record('videoassessment_videos', (object) [
            'videoassessment' => $activity->id,
            'filepath' => '/',
            'filename' => $filename,
            'thumbnailname' => '',
            'tmpname' => $tmpname,
            'originalname' => 'clip.webm',
            'timecreated' => 1700000000,
            'timemodified' => 1700000000,
        ]);
        $DB->insert_record('videoassessment_video_assocs', (object) [
            'videoassessment' => $activity->id,
            'videoid' => $videoid,
            'associationtype' => '1',
            'timing' => 'before',
            'associationid' => $userid,
            'timemodified' => 1700000000,
        ]);
        return (int) $videoid;
    }

    /**
     * The async callback URL must target bulkupload/async.php with the
     * cmid, the temp filename and the site-identifier token that
     * async.php verifies.
     *
     * @covers \videoassessment_bulkupload::build_async_convert_url
     */
    public function test_build_async_convert_url(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $this->resetAfterTest();
        ['cm' => $cm] = $this->make_activity();

        $upload = new \videoassessment_bulkupload($cm->id);
        $url = $upload->build_async_convert_url('20991231-clip.webm');

        $this->assertStringEndsWith(
            '/mod/videoassessment/bulkupload/async.php',
            $url->out_omit_querystring()
        );
        $params = $url->params();
        $this->assertSame((string) $cm->id, (string) $params['cmid']);
        $this->assertSame('20991231-clip.webm', $params['file']);
        $this->assertSame(
            md5('20991231-clip.webm' . get_site_identifier()),
            $params['token'],
            'The token must match what async.php recomputes server-side.'
        );
    }

    /**
     * While the background conversion is running (filename still
     * empty), has_converting_video() must report true so the learner
     * sees the "being converted" notice instead of an empty page.
     *
     * @covers \mod_videoassessment\va::has_converting_video
     */
    public function test_has_converting_video_while_pending(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->insert_video_with_assoc($activity, (int) $student->id, 'tmp123.webm', '');

        $this->assertTrue($va->has_converting_video($student->id, 'before'));
        $this->assertFalse(
            $va->has_converting_video($student->id, 'after'),
            'Only the timing with the pending association may report converting.'
        );
    }

    /**
     * Once the converted file lands in the file storage the detector
     * must flip to false (the video renders normally from then on).
     *
     * @covers \mod_videoassessment\va::has_converting_video
     */
    public function test_has_converting_video_after_conversion(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course, 'context' => $context]
            = $this->make_activity();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $videoid = $this->insert_video_with_assoc($activity, (int) $student->id, 'tmp123.webm', 'clip.mp4');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'clip.mp4',
        ], 'fake-video-bytes');

        $this->assertFalse(
            $va->has_converting_video($student->id, 'before'),
            "Video {$videoid} is converted and stored; nothing is pending."
        );
    }

    /**
     * External (YouTube) links never go through FFmpeg, so they must
     * never be reported as converting; and a user with no association
     * at all reports false.
     *
     * @covers \mod_videoassessment\va::has_converting_video
     */
    public function test_has_converting_video_youtube_and_none(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->assertFalse($va->has_converting_video($student->id, 'before'));

        $this->insert_video_with_assoc($activity, (int) $student->id, 'Youtube', '');
        $this->assertFalse(
            $va->has_converting_video($student->id, 'before'),
            'YouTube links are never converted, so never "converting".'
        );
    }

    /**
     * Static wiring contract: the upload handler must dispatch the
     * conversion in the background. A synchronous $upload->convert()
     * call in view_upload_video() would silently bring the endless
     * spinner back.
     *
     * @coversNothing
     */
    public function test_upload_handler_uses_background_conversion(): void {
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringNotContainsString(
            '$upload->convert(',
            $vaphp,
            'view_upload_video() must not run FFmpeg synchronously '
                . 'inside the upload POST — that is the "never-ending '
                . 'uploading circle" the customers reported.'
        );
        $this->assertStringContainsString(
            '$upload->dispatch_async_convert(',
            $vaphp,
            'view_upload_video() must dispatch conversions through '
                . 'dispatch_async_convert().'
        );
        $this->assertStringContainsString(
            "self::str('videoconverting')",
            $vaphp,
            'The learner-facing "being converted" notice must be '
                . 'rendered so an in-flight conversion does not look '
                . 'like a failed upload.'
        );
    }

    /**
     * Static wiring contract: async.php is a cookieless server-to-
     * server callback. A require_login() there redirects the
     * cookieless dispatch to the login page and conversions silently
     * never run.
     *
     * @coversNothing
     */
    public function test_async_endpoint_is_cookieless_token_callback(): void {
        $async = file_get_contents(__DIR__ . '/../bulkupload/async.php');
        $this->assertStringContainsString(
            "define('NO_MOODLE_COOKIES', true)",
            $async,
            'async.php must run cookieless — it is called by a raw '
                . 'fire-and-forget socket with no session.'
        );
        $this->assertStringNotContainsString(
            'require_login()',
            $async,
            'async.php must not require a login: the dispatching '
                . 'socket has no session cookie, so require_login() '
                . 'redirects and the conversion never runs.'
        );
        $this->assertStringContainsString(
            'md5($file . get_site_identifier())',
            $async,
            'async.php must keep validating the site-identifier token.'
        );
    }

    /**
     * Static wiring contract: the uploader JS keeps a watchdog so the
     * overlay can never sit on screen forever even if the post-upload
     * navigation stalls.
     *
     * @coversNothing
     */
    public function test_uploader_watchdog_contract(): void {
        $js = file_get_contents(__DIR__ . '/../amd/src/uploader.js');
        $this->assertStringContainsString(
            'setTimeout(hideUploadOverlay',
            $js,
            'uploader.js must arm a watchdog that hides the overlay '
                . 'if the page navigation stalls after a successful '
                . 'upload.'
        );
    }
}
