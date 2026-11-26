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
 * multi-minute conversion. Conversions now run in cron, and the upload
 * responds as soon as the file is saved. These tests pin:
 *
 * - the conversion is queued for cron and has no web endpoint,
 * - the converting-state detector that backs the learner-facing
 *   "video is being converted" notice,
 * - static wiring contracts (no synchronous convert() in the upload
 *   handler, the JS watchdog exists).
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
     * An upload queues its conversion for cron, once.
     *
     * @covers \videoassessment_bulkupload::queue_conversion
     */
    public function test_queue_conversion(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $this->resetAfterTest();
        ['cm' => $cm] = $this->make_activity();

        $upload = new \videoassessment_bulkupload($cm->id);
        $upload->queue_conversion('clip.webm');
        $upload->queue_conversion('clip.webm');

        $tasks = array_values(\core\task\manager::get_adhoc_tasks(task\convert_uploaded_video::class));
        $this->assertCount(1, $tasks);
        $this->assertSame((int) $cm->id, (int) $tasks[0]->get_custom_data()->cmid);
        $this->assertSame('clip.webm', $tasks[0]->get_custom_data()->file);
    }

    /**
     * ffmpeg must not run in a web request: hosts can limit their memory so
     * much that it cannot start, so there is no endpoint that runs it.
     *
     * @coversNothing
     */
    public function test_conversion_has_no_web_endpoint(): void {
        $this->assertFileDoesNotExist(__DIR__ . '/../bulkupload/async.php');
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
     * Put a file in the activity's temporary folder.
     *
     * @param int $cmid Course module id.
     * @param string $path Path inside the activity's temporary folder.
     * @return void
     */
    private function touch_temp_file(int $cmid, string $path): void {
        $file = make_temp_directory('videoassessment/' . $cmid . '/' . dirname($path)) . '/' . basename($path);
        file_put_contents($file, 'x');
    }

    /**
     * Age an inserted video, which helps to tell a running conversion from one that never finished.
     *
     * @param int $videoid Video id.
     * @param int $age Age in seconds.
     * @return void
     */
    private function set_video_age(int $videoid, int $age): void {
        global $DB;
        $DB->set_field('videoassessment_videos', 'timecreated', time() - $age, ['id' => $videoid]);
    }

    /**
     * An unconverted video has no file name. Once the activity holds any other
     * video, Moodle's file API answers an empty name with the folder itself,
     * which must not make the video look ready.
     *
     * @covers \mod_videoassessment\video
     * @covers \mod_videoassessment\va::get_associated_video
     */
    public function test_unconverted_video_beside_other_videos(): void {
        global $DB;
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'cm' => $cm, 'context' => $context] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'another.mp4',
        ], 'content');

        // The row as video_data_add() really leaves it: no path and no name.
        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv7.mp4', '');
        $DB->set_field('videoassessment_videos', 'filepath', '', ['id' => $videoid]);
        $this->set_video_age($videoid, 10);
        $this->touch_temp_file((int) $cm->id, 'upload/conv7.mp4');

        $this->assertTrue($va->has_converting_video($user, 'before'));
        $this->assertNull($va->get_associated_video($user, 'before'));
        $this->assertSame(va::CONVERSION_CONVERTING, $va->video_conversion_status($user, 'before'));
    }

    /**
     * A recent upload whose source file is waiting for ffmpeg is converting.
     *
     * @covers \mod_videoassessment\va::video_conversion_status
     */
    public function test_conversion_status_while_converting(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'cm' => $cm] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv1.mp4', '');
        $this->set_video_age($videoid, 10);
        $this->touch_temp_file((int) $cm->id, 'upload/conv1.mp4');

        $this->assertSame(va::CONVERSION_CONVERTING, $va->video_conversion_status($user, 'before'));
    }

    /**
     * Failed uploads must not be shown as converting: this is what the demo
     * site did when ffmpeg was missing, so the page never changed.
     *
     * @covers \mod_videoassessment\va::video_conversion_status
     * @covers \videoassessment_bulkupload::conversion_failed
     */
    public function test_conversion_status_when_ffmpeg_failed(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'cm' => $cm] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv2.mp4', '');
        $this->set_video_age($videoid, 10);
        $this->touch_temp_file((int) $cm->id, 'upload/conv2.mp4');
        $this->touch_temp_file((int) $cm->id, 'convert/conv2.err');

        $this->assertSame(va::CONVERSION_FAILED, $va->video_conversion_status($user, 'before'));
    }

    /**
     * Without its source file a video can never be converted any more.
     *
     * @covers \videoassessment_bulkupload::conversion_failed
     */
    public function test_conversion_status_when_source_is_gone(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv3.mp4', '');
        $this->set_video_age($videoid, 10);

        $this->assertSame(va::CONVERSION_FAILED, $va->video_conversion_status($user, 'before'));
    }

    /**
     * A conversion that never finished, such as a lost background request, counts as failed.
     *
     * @covers \videoassessment_bulkupload::conversion_failed
     */
    public function test_conversion_status_after_timeout(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'cm' => $cm] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv4.mp4', '');
        $this->touch_temp_file((int) $cm->id, 'upload/conv4.mp4');

        $this->set_video_age($videoid, \videoassessment_bulkupload::CONVERSION_TIMEOUT - 60);
        $this->assertSame(va::CONVERSION_CONVERTING, $va->video_conversion_status($user, 'before'));

        $this->set_video_age($videoid, \videoassessment_bulkupload::CONVERSION_TIMEOUT + 60);
        $this->assertSame(va::CONVERSION_FAILED, $va->video_conversion_status($user, 'before'));
    }

    /**
     * No status for a learner without a video or with an external link.
     *
     * @covers \mod_videoassessment\va::video_conversion_status
     */
    public function test_conversion_status_without_pending_video(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertSame('', $va->video_conversion_status($user, 'before'));

        $this->insert_video_with_assoc($activity, $user, 'Youtube', '');
        $this->assertSame('', $va->video_conversion_status($user, 'before'));
    }

    /**
     * The notice says what happened, and a failure wins over another timing still converting.
     *
     * @covers \mod_videoassessment\va::conversion_notification
     */
    public function test_conversion_notification(): void {
        global $DB;
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'cm' => $cm] = $this->make_activity();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertSame('', $va->conversion_notification($user, ['before', 'after']));

        $videoid = $this->insert_video_with_assoc($activity, $user, 'conv5.mp4', '');
        $this->set_video_age($videoid, 10);
        $this->touch_temp_file((int) $cm->id, 'upload/conv5.mp4');
        $notice = $va->conversion_notification($user, ['before', 'after']);
        $this->assertStringContainsString(get_string('videoconverting', 'videoassessment'), $notice);

        $second = $this->insert_video_with_assoc($activity, $user, 'conv6.mp4', '');
        $DB->set_field('videoassessment_video_assocs', 'timing', 'after', ['videoid' => $second]);
        $this->set_video_age($second, 10);
        $notice = $va->conversion_notification($user, ['before', 'after']);
        $this->assertStringContainsString(get_string('videoconversionfailed', 'videoassessment'), $notice);
        $this->assertStringNotContainsString(get_string('videoconverting', 'videoassessment'), $notice);
    }

    /**
     * Teachers upload on a learner's behalf, so they need the status too:
     * they used to see nothing at all after a failed conversion.
     *
     * @coversNothing
     */
    public function test_status_is_shown_to_teachers(): void {
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString('$this->conversion_notification($targetuser, [$targettiming])', $vaphp);
        $table = file_get_contents(__DIR__ . '/../classes/grade_table.php');
        $this->assertStringContainsString('$this->va->video_conversion_status(', $table);
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
            '$upload->queue_conversion(',
            $vaphp,
            'view_upload_video() must leave conversions to cron through '
                . 'queue_conversion().'
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
