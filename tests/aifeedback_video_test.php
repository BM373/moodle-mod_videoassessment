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
 * Tests for AI feedback on submitted videos.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use GuzzleHttp\Psr7\Response;
use mod_videoassessment\aifeedback\attempt_manager;
use mod_videoassessment\task\convert_uploaded_video;
use mod_videoassessment\task\process_video_ai_feedback;

/**
 * A submitted video goes to the AI by itself, audio only, for learners who accepted the AI policy.
 *
 * ffmpeg is replaced by a small script, and the transcription and Bedrock
 * services by Moodle's mocked HTTP client, so no request leaves the test.
 *
 * @covers \mod_videoassessment\aifeedback\attempt_manager
 * @covers \mod_videoassessment\task\process_video_ai_feedback
 * @covers \mod_videoassessment\task\convert_uploaded_video
 * @covers \videoassessment_bulkupload
 */
final class aifeedback_video_test extends \advanced_testcase {
    /** @var string AI answer used by the tests. */
    const AI_ANSWER = '{"summary": "Clear topic.", "strengths": [{"quote": "space debris", "comment": "On topic."}], '
        . '"improvements": [{"quote": "very big", "comment": "Vague.", "suggestion": "enormous"}], '
        . '"nextstep": "Add a conclusion."}';

    /** @var string PATH when the test started; ffmpeg calls clear it. */
    private $path;

    /**
     * Keep the process environment as it was.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $this->path = (string)getenv('PATH');
    }

    /**
     * Restore the process environment.
     */
    protected function tearDown(): void {
        putenv('PATH=' . $this->path);
        parent::tearDown();
    }

    /**
     * Activity with AI feedback, one learner and an association-free video.
     *
     * @param array $settings Activity settings.
     * @return array{va: \stdClass, cm: \stdClass, context: \context_module, student: \stdClass, manager: attempt_manager}
     */
    private function setup_activity(array $settings = []): array {
        global $DB;
        $this->resetAfterTest();
        set_config('aitranscribeapikey', 'test-key', 'videoassessment');
        set_config('aifeedbackengine', 'bedrock', 'videoassessment');
        set_config('awsregion', 'ap-northeast-1', 'videoassessment');
        set_config('awsaccesskeyid', 'AKIDEXAMPLE', 'videoassessment');
        set_config('awssecretaccesskey', 'secret', 'videoassessment');
        set_config('awsbedrockmodel', 'test-model', 'videoassessment');
        $this->assertTrue(attempt_manager::site_ready(), 'The other tests must not pass just because the site is not ready.');
        $course = $this->getDataGenerator()->create_course();
        $va = $this->getDataGenerator()->create_module('videoassessment', $settings + [
            'course' => $course->id,
            'aifeedback' => 1,
            'aifeedbacklang' => 'en',
            'aitranscribelang' => 'en',
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $va = $DB->get_record('videoassessment', ['id' => $va->id]);
        return [
            'va' => $va,
            'cm' => $cm,
            'context' => $context,
            'student' => $student,
            'manager' => new attempt_manager($va, $context),
        ];
    }

    /**
     * A converted video associated with a learner.
     *
     * @param \stdClass $va Activity.
     * @param \context_module $context Its context.
     * @param int $userid Learner.
     * @return int Video id.
     */
    private function add_video(\stdClass $va, \context_module $context, int $userid): int {
        global $DB;
        $name = 'video' . random_string(6) . '.mp4';
        $videoid = (int)$DB->insert_record('videoassessment_videos', (object)[
            'videoassessment' => $va->id,
            'filepath' => '/',
            'filename' => $name,
            'thumbnailname' => '',
            'tmpname' => $name,
            'originalname' => $name,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $name,
        ], 'video content');
        $DB->insert_record('videoassessment_video_assocs', (object)[
            'videoassessment' => $va->id,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $userid,
            'timemodified' => time(),
        ]);
        return $videoid;
    }

    /**
     * Use a script instead of ffmpeg: it records its arguments and writes its last argument.
     *
     * @param int $exitcode Exit code of the script.
     * @return string Path of the file that receives the arguments.
     */
    private function fake_ffmpeg(int $exitcode = 0): string {
        global $CFG;
        $dir = make_request_directory();
        $args = $dir . '/args.txt';
        $script = $dir . '/fake-ffmpeg';
        file_put_contents($script, "#!/bin/sh\necho \"\$@\" >> '{$args}'\nfor a in \"\$@\"; do out=\"\$a\"; done\n"
            . ($exitcode ? '' : "printf 'media' > \"\$out\"\n") . "exit {$exitcode}\n");
        chmod($script, 0755);
        $CFG->videoassessment_ffmpegcommand = $script . ' -i {INPUT} -c:v libx264 {OUTPUT}';
        $CFG->videoassessment_ffmpegthumbnailcommand = $script . ' -i {INPUT} -vframes 1 {OUTPUT}';
        $CFG->videoassessment_mp4boxcommand = '';
        $CFG->videoassessment_videoformat = '.mp4';
        return $args;
    }

    /**
     * Answer one transcription and one AI request.
     *
     * @param string $transcript What the transcription service hears.
     * @param bool $aifails Whether the AI request fails.
     * @return void
     */
    private function mock_services(
        string $transcript = 'Today I talk about space debris. It is a very big problem.',
        bool $aifails = false
    ): void {
        $history = [];
        ['mock' => $http] = $this->get_mocked_http_client($history);
        $http->append(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'text' => $transcript,
        ])));
        $http->append($aifails
            ? new Response(500, ['Content-Type' => 'application/json'], json_encode(['message' => 'Internal error']))
            : new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'output' => ['message' => ['role' => 'assistant', 'content' => [['text' => self::AI_ANSWER]]]],
                'stopReason' => 'end_turn',
            ])));
    }

    /**
     * A video still waiting for its conversion: only the original upload exists.
     *
     * @param \stdClass $va Activity.
     * @param \stdClass $cm Its course module.
     * @param int $userid Learner it is associated with.
     * @return array{videoid: int, upload: string} Video id and path of the upload.
     */
    private function add_unconverted_video(\stdClass $va, \stdClass $cm, int $userid): array {
        global $DB;
        $upload = new \videoassessment_bulkupload($cm->id);
        $upload->create_temp_dirs();
        $name = $upload->get_temp_name('clip.mov');
        $path = $upload->get_tempdir() . '/upload/' . $name;
        file_put_contents($path, 'source');
        $videoid = (int)$upload->video_data_add($name, 'clip.mov');
        $DB->insert_record('videoassessment_video_assocs', (object)[
            'videoassessment' => $va->id,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $userid,
            'timemodified' => time(),
        ]);
        return ['videoid' => $videoid, 'upload' => $path];
    }

    /**
     * Task custom data of the queued video tasks.
     *
     * @return array[] Each as [videoid, userid].
     */
    private function queued(): array {
        return array_map(
            fn($task) => [(int)$task->get_custom_data()->videoid, (int)$task->get_custom_data()->userid],
            array_values(\core\task\manager::get_adhoc_tasks(process_video_ai_feedback::class))
        );
    }

    /**
     * Associating a video queues it once, and only when the activity asks for it.
     */
    public function test_associating_a_video_queues_it(): void {
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        $course = get_course($cm->course);
        $module = new va($context, $cm, $course);
        $associate = new \ReflectionMethod($module, 'associate_video');
        $associate->setAccessible(true);

        $associate->invoke($module, $student->id, 'before', $videoid);
        $associate->invoke($module, $student->id, 'before', $videoid);

        $this->assertSame([[$videoid, (int)$student->id]], $this->queued());
    }

    /**
     * The activity setting turns it off, and so does turning AI feedback off.
     */
    public function test_settings_switch_it_off(): void {
        ['va' => $va, 'student' => $student] = $this->setup_activity();

        $va->aifeedbackauto = 0;
        attempt_manager::queue_video($va, 5, (int)$student->id);
        $va->aifeedbackauto = 1;
        $va->aifeedback = 0;
        attempt_manager::queue_video($va, 5, (int)$student->id);

        $this->assertSame([], $this->queued());
    }

    /**
     * New activities have it on, as agreed.
     */
    public function test_on_by_default(): void {
        ['va' => $va] = $this->setup_activity();
        $this->assertSame(1, (int)$va->aifeedbackauto);
    }

    /**
     * Finishing a conversion queues the video for every learner it is associated with.
     */
    public function test_conversion_queues_associated_learners(): void {
        global $DB;
        ['va' => $va, 'cm' => $cm, 'student' => $student] = $this->setup_activity();
        $this->fake_ffmpeg();

        $upload = new \videoassessment_bulkupload($cm->id);
        $upload->create_temp_dirs();
        $name = $upload->get_temp_name('clip.mov');
        file_put_contents($upload->get_tempdir() . '/upload/' . $name, 'source');
        $videoid = (int)$upload->video_data_add($name, 'clip.mov');
        $DB->insert_record('videoassessment_video_assocs', (object)[
            'videoassessment' => $va->id,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $student->id,
            'timemodified' => time(),
        ]);

        $this->setAdminUser();
        $upload->convert($name);

        $this->assertSame([[$videoid, (int)$student->id]], $this->queued());
    }

    /**
     * Conversion runs in cron. The video counts as converting until then, and a
     * failure is final: visible to the user, and the task does not retry for ever.
     */
    public function test_conversion_runs_in_cron(): void {
        global $DB;
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student] = $this->setup_activity();
        $upload = new \videoassessment_bulkupload($cm->id);
        $upload->create_temp_dirs();
        $name = $upload->get_temp_name('clip.mov');
        file_put_contents($upload->get_tempdir() . '/upload/' . $name, 'source');
        $videoid = (int)$upload->video_data_add($name, 'clip.mov');
        $DB->insert_record('videoassessment_video_assocs', (object)[
            'videoassessment' => $va->id,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $student->id,
            'timemodified' => time(),
        ]);
        $module = new va($context, $cm, get_course($cm->course));
        $this->setAdminUser();
        $task = new convert_uploaded_video();
        $task->set_custom_data(['cmid' => $cm->id, 'file' => $name]);

        $this->assertSame(va::CONVERSION_CONVERTING, $module->video_conversion_status((int)$student->id, 'before'));

        $this->fake_ffmpeg(1);
        $this->expectOutputRegex('/Video conversion failed/');
        $task->execute();
        $this->assertSame(va::CONVERSION_FAILED, $module->video_conversion_status((int)$student->id, 'before'));

        unlink($upload->get_tempdir() . '/convert/' . pathinfo($name, PATHINFO_FILENAME) . '.err');
        $this->fake_ffmpeg();
        $task->execute();
        $this->assertSame('', $module->video_conversion_status((int)$student->id, 'before'));
        $this->assertNotNull($module->get_associated_video((int)$student->id, 'before'));
        $this->assertSame([[$videoid, (int)$student->id]], $this->queued());
    }

    /**
     * The whole path: only the sound is taken, the attempt is linked to the video and processed, and only once.
     */
    public function test_process_video(): void {
        global $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $args = $this->fake_ffmpeg();
        $this->mock_services();

        $attempt = $manager->process_video($videoid, (int)$student->id);

        $this->assertSame(attempt_manager::STATUS_DONE, (int)$attempt->status);
        $this->assertSame($videoid, (int)$attempt->videoid);
        $this->assertStringContainsString('space debris', $attempt->transcript);
        $this->assertStringContainsString('-vn', file_get_contents($args));
        $this->assertSame('attempt1.m4a', $manager->get_audio_file($attempt)->get_filename());

        $this->assertNull($manager->process_video($videoid, (int)$student->id));
        $this->assertSame(1, $DB->count_records(attempt_manager::TABLE, ['userid' => $student->id]));
    }

    /**
     * Nothing is sent before the learner accepts the AI policy; accepting queues their videos.
     */
    public function test_policy_is_respected(): void {
        global $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        $args = $this->fake_ffmpeg();

        $this->assertNull($manager->process_video($videoid, (int)$student->id));
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE));
        $this->assertFileDoesNotExist($args);

        attempt_manager::queue_user_videos($va, (int)$student->id);
        $this->assertSame([[$videoid, (int)$student->id]], $this->queued());
    }

    /**
     * A video without readable sound leaves a visible failed attempt instead of nothing.
     */
    public function test_failed_extraction_is_visible(): void {
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $this->fake_ffmpeg(1);

        $attempt = $manager->process_video($videoid, (int)$student->id);

        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$attempt->status);
        $this->assertSame($videoid, (int)$attempt->videoid);
        $this->assertStringContainsString(
            get_string('aifb_error_noaudio', 'videoassessment'),
            attempt_manager::error_text($attempt, false)
        );
        $this->assertNull($manager->process_video($videoid, (int)$student->id), 'It must not be tried again by itself.');
    }

    /**
     * Unconverted videos, external links and videos no longer theirs are skipped.
     */
    public function test_skipped_videos(): void {
        global $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $args = $this->fake_ffmpeg();

        $videoid = $this->add_video($va, $context, $student->id);
        $other = $this->getDataGenerator()->create_user();
        $this->assertNull($manager->process_video($videoid, (int)$other->id), 'Not associated with this learner.');

        $DB->set_field('videoassessment_videos', 'filename', '', ['id' => $videoid]);
        $this->assertNull($manager->process_video($videoid, (int)$student->id), 'Not converted, and no upload left.');

        $DB->set_field('videoassessment_videos', 'tmpname', 'Youtube', ['id' => $videoid]);
        $this->assertNull($manager->process_video($videoid, (int)$student->id), 'External link.');

        $this->assertNull($manager->process_video(999999, (int)$student->id), 'Deleted video.');
        $this->assertFileDoesNotExist($args);
    }

    /**
     * Deleting a video deletes the audio taken from it, and leaves other attempts alone.
     */
    public function test_deleting_a_video_deletes_its_attempts(): void {
        global $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $path = make_request_directory() . '/take.mp3';
        file_put_contents($path, 'audio');
        $practice = $manager->create_attempt($student->id, $path, 'take.mp3');
        $fromvideo = $manager->create_attempt($student->id, $path, 'take.mp3', 0, 77);

        attempt_manager::delete_video_attempts((int)$va->id, (int)$context->id, 77);

        $this->assertTrue($DB->record_exists(attempt_manager::TABLE, ['id' => $practice->id]));
        $this->assertFalse($DB->record_exists(attempt_manager::TABLE, ['id' => $fromvideo->id]));
        $this->assertNull($manager->get_audio_file($fromvideo));
    }

    /**
     * The overview counts what is on its way to the AI, including submitted videos with no attempt yet.
     */
    public function test_pending_counts(): void {
        global $DB;
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $userid = (int)$student->id;
        \core_ai\manager::user_policy_accepted($userid, $context->id);
        $this->assertSame([], $manager->get_pending_counts());

        // An attempt being processed counts; a finished one does not.
        $path = make_request_directory() . '/take.mp3';
        file_put_contents($path, 'audio');
        $attempt = $manager->create_attempt($userid, $path, 'take.mp3');
        $this->assertSame(1, $manager->get_pending_counts()[$userid]->analysing);
        $DB->set_field(attempt_manager::TABLE, 'status', attempt_manager::STATUS_DONE, ['id' => $attempt->id]);
        $this->assertSame([], $manager->get_pending_counts());

        // A converted video counts only while its task is queued: otherwise nothing is coming.
        $videoid = $this->add_video($va, $context, $userid);
        $this->assertSame([], $manager->get_pending_counts());
        attempt_manager::queue_video($va, $videoid, $userid);
        $this->assertSame(1, $manager->get_pending_counts()[$userid]->videos);

        // Once it has its attempt, the attempt counts instead.
        $DB->set_field(attempt_manager::TABLE, 'videoid', $videoid, ['id' => $attempt->id]);
        $this->assertSame([], $manager->get_pending_counts());
        $DB->set_field(attempt_manager::TABLE, 'videoid', 0, ['id' => $attempt->id]);

        // A video still converting is on its way; one that failed to convert is not.
        $DB->set_field('videoassessment_videos', 'filename', '', ['id' => $videoid]);
        $DB->set_field('videoassessment_videos', 'timecreated', time(), ['id' => $videoid]);
        $tmpname = $DB->get_field('videoassessment_videos', 'tmpname', ['id' => $videoid]);
        $temp = make_temp_directory('videoassessment/' . $cm->id);
        make_temp_directory('videoassessment/' . $cm->id . '/upload');
        make_temp_directory('videoassessment/' . $cm->id . '/convert');
        file_put_contents($temp . '/upload/' . $tmpname, 'source');
        $this->assertSame(1, $manager->get_pending_counts()[$userid]->videos);
        file_put_contents($temp . '/convert/' . pathinfo($tmpname, PATHINFO_FILENAME) . '.err', 'failed');
        $this->assertSame([], $manager->get_pending_counts());
    }

    /**
     * A learner who has not accepted the AI policy is shown as waiting, not as analysing.
     */
    public function test_pending_counts_without_policy(): void {
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $userid = (int)$student->id;
        $this->add_video($va, $context, $userid);

        $counts = $manager->get_pending_counts()[$userid];

        $this->assertSame(0, $counts->videos);
        $this->assertSame(1, $counts->consent);
    }

    /**
     * With the setting off, submitted videos are not on their way to the AI.
     */
    public function test_pending_counts_with_setting_off(): void {
        ['va' => $va, 'context' => $context, 'student' => $student] = $this->setup_activity(['aifeedbackauto' => 0]);
        $this->add_video($va, $context, (int)$student->id);

        $this->assertSame([], (new attempt_manager($va, $context))->get_pending_counts());
    }

    /**
     * With the site setting on, a teacher's upload goes to the AI without the learner's
     * acceptance, and that acceptance is not recorded on the learner's behalf.
     */
    public function test_teacher_upload_counts_as_accepted(): void {
        global $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $userid = (int)$student->id;
        $videoid = $this->add_video($va, $context, $userid);
        $DB->set_field('videoassessment_video_assocs', 'teacherupload', 1, ['videoid' => $videoid]);
        $this->fake_ffmpeg();

        // Off by default: it waits for the learner.
        $this->assertNull($manager->process_video($videoid, $userid));
        $this->assertSame(1, $manager->get_pending_counts()[$userid]->consent);

        set_config('aifbteacherconsent', 1, 'videoassessment');
        attempt_manager::queue_video($va, $videoid, $userid);
        $counts = $manager->get_pending_counts()[$userid];
        $this->assertSame(1, $counts->videos);
        $this->assertSame(0, $counts->consent);

        $this->mock_services();
        $attempt = $manager->process_video($videoid, $userid);
        $this->assertSame(attempt_manager::STATUS_DONE, (int)$attempt->status);
        $this->assertEmpty(\core_ai\manager::get_user_policy_status($userid));
    }

    /**
     * The setting covers teachers' uploads only: a learner's own upload still needs their acceptance.
     */
    public function test_learner_upload_still_needs_policy(): void {
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $userid = (int)$student->id;
        $videoid = $this->add_video($va, $context, $userid);
        set_config('aifbteacherconsent', 1, 'videoassessment');
        $args = $this->fake_ffmpeg();

        $this->assertNull($manager->process_video($videoid, $userid));
        $this->assertFileDoesNotExist($args);
        $this->assertSame(1, $manager->get_pending_counts()[$userid]->consent);
    }

    /**
     * Associating a video records whether a teacher did it for a learner.
     */
    public function test_associating_marks_teacher_uploads(): void {
        global $DB;
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student] = $this->setup_activity();
        $course = get_course($cm->course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $videoid = $this->add_video($va, $context, $student->id);
        $module = new va($context, $cm, $course);
        $associate = new \ReflectionMethod($module, 'associate_video');
        $associate->setAccessible(true);
        $flag = fn() => (int)$DB->get_field('videoassessment_video_assocs', 'teacherupload', ['videoid' => $videoid]);

        $this->setUser($teacher);
        $associate->invoke($module, $student->id, 'before', $videoid);
        $this->assertSame(1, $flag());

        $this->setUser($student);
        $associate->invoke($module, $student->id, 'before', $videoid);
        $this->assertSame(0, $flag());
    }

    /**
     * The task runs the same path, and survives an activity deleted in the meantime.
     */
    public function test_task(): void {
        ['va' => $va, 'context' => $context, 'student' => $student] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $this->fake_ffmpeg();
        $this->mock_services();

        $task = new process_video_ai_feedback();
        $task->set_custom_data(['videoassessment' => $va->id, 'videoid' => $videoid, 'userid' => $student->id]);
        $task->execute();
        $this->assertCount(1, (new attempt_manager($va, $context))->get_user_attempts($student->id));

        $task = new process_video_ai_feedback();
        $task->set_custom_data(['videoassessment' => 999999, 'videoid' => $videoid, 'userid' => $student->id]);
        $task->execute();
    }

    /**
     * The sound is taken from the original upload, so the transcript does not wait for the conversion.
     */
    public function test_sound_is_taken_before_the_conversion(): void {
        global $DB;
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        ['videoid' => $videoid, 'upload' => $upload] = $this->add_unconverted_video($va, $cm, (int)$student->id);
        $args = $this->fake_ffmpeg();
        $this->mock_services();

        $attempt = $manager->process_video($videoid, (int)$student->id);

        $this->assertSame(attempt_manager::STATUS_DONE, (int)$attempt->status);
        $this->assertStringContainsString($upload, file_get_contents($args));
        $this->assertFileExists($upload, 'The conversion still needs it.');
        $this->assertSame('', $DB->get_field('videoassessment_videos', 'filename', ['id' => $videoid]));

        // When the conversion finishes, the video is not sent again.
        $this->assertNull($manager->process_video($videoid, (int)$student->id));
        $this->assertSame(1, $DB->count_records(attempt_manager::TABLE, ['userid' => $student->id]));
    }

    /**
     * An upload removed by a conversion finishing at that moment is not a failure: the conversion's task takes over.
     */
    public function test_upload_removed_meanwhile(): void {
        global $CFG, $DB;
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        ['videoid' => $videoid] = $this->add_unconverted_video($va, $cm, (int)$student->id);
        $this->fake_ffmpeg();
        // The third argument of the audio extraction is its input. The command runs with an empty PATH.
        $script = make_request_directory() . '/vanishing-ffmpeg';
        file_put_contents($script, "#!/bin/sh\n/bin/rm -f \"\$3\"\nexit 1\n");
        chmod($script, 0755);
        $CFG->videoassessment_ffmpegcommand = $script . ' -i {INPUT} {OUTPUT}';

        $this->assertNull($manager->process_video($videoid, (int)$student->id));
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE));
    }

    /**
     * The learner gets the transcript and the AI's comments as a notification, sent by email.
     */
    public function test_learner_gets_the_transcript(): void {
        ['va' => $va, 'cm' => $cm, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $this->fake_ffmpeg();
        $this->mock_services('Today I talk about space debris <b>now</b>. It is a very big problem.');
        $sink = $this->redirectMessages();

        $manager->process_video($videoid, (int)$student->id);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame('mod_videoassessment', $message->component);
        $this->assertSame('aifeedback', $message->eventtype);
        $this->assertSame((int)$student->id, (int)$message->useridto);
        $this->assertStringContainsString($va->name, $message->subject);
        $url = (new \moodle_url('/mod/videoassessment/aifeedback.php', ['id' => $cm->id]))->out(false);
        $this->assertSame($url, $message->contexturl);
        // The transcript first, then the comments.
        $text = $message->fullmessage;
        $this->assertStringContainsString('Today I talk about space debris <b>now</b>.', $text);
        $this->assertLessThan(strpos($text, 'Clear topic.'), strpos($text, 'space debris <b>now</b>'));
        foreach (['On topic.', 'Vague.', 'enormous', 'Add a conclusion.', $url] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringContainsString('space debris &lt;b&gt;now&lt;/b&gt;', $message->fullmessagehtml);
        $this->assertStringNotContainsString('<b>now</b>', $message->fullmessagehtml);
    }

    /**
     * The transcript is sent even when the AI cannot comment on it.
     */
    public function test_transcript_is_sent_when_the_ai_fails(): void {
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $this->fake_ffmpeg();
        $this->mock_services('Today I talk about space debris.', true);
        $sink = $this->redirectMessages();

        $attempt = $manager->process_video($videoid, (int)$student->id);

        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$attempt->status);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $text = reset($messages)->fullmessage;
        $this->assertStringContainsString('Today I talk about space debris.', $text);
        $this->assertStringContainsString(get_string('aifb_email_nofeedback', 'videoassessment'), $text);
    }

    /**
     * Nothing is sent without a transcript, nor for practice recordings, whose feedback is on the page the learner is on.
     */
    public function test_no_message_without_a_submitted_video_transcript(): void {
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $sink = $this->redirectMessages();

        $videoid = $this->add_video($va, $context, $student->id);
        $this->fake_ffmpeg(1);
        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$manager->process_video($videoid, (int)$student->id)->status);

        $path = make_request_directory() . '/take.mp3';
        file_put_contents($path, 'audio');
        $practice = $manager->create_attempt($student->id, $path, 'take.mp3');
        $this->mock_services();
        $this->assertSame(attempt_manager::STATUS_DONE, (int)attempt_manager::process_attempt((int)$practice->id)->status);

        $this->assertSame([], $sink->get_messages());
    }

    /**
     * The message is in the learner's language, whoever's request or cron run processes the video.
     */
    public function test_message_is_in_the_learners_language(): void {
        global $CFG, $DB;
        ['va' => $va, 'context' => $context, 'student' => $student, 'manager' => $manager] = $this->setup_activity();
        // Install Japanese: the plugin's own Japanese strings apply once the language exists.
        make_writable_directory($CFG->dataroot . '/lang/ja');
        file_put_contents($CFG->dataroot . '/lang/ja/langconfig.php', "<?php\n\$string['thislanguage'] = 'Japanese';\n");
        get_string_manager()->reset_caches();
        $DB->set_field('user', 'lang', 'ja', ['id' => $student->id]);
        $videoid = $this->add_video($va, $context, $student->id);
        \core_ai\manager::user_policy_accepted($student->id, $context->id);
        $this->fake_ffmpeg();
        $this->mock_services();
        $sink = $this->redirectMessages();
        $this->setAdminUser();

        $manager->process_video($videoid, (int)$student->id);

        $message = current($sink->get_messages());
        $this->assertStringStartsWith('動画の文字起こし', $message->subject);
        $this->assertSame('en', current_language(), 'The language of the request is restored.');
    }
}
