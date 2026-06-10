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
 * Tests for the feedback-video WebM to MP4 conversion task (item #6).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use mod_videoassessment\task\convert_feedback_video;

/**
 * Test double: replaces the FFmpeg invocation with a file copy so the
 * full execute() pipeline (file storage + HTML rewrite + DB update)
 * can run inside PHPUnit.
 */
final class testable_convert_feedback_video extends convert_feedback_video {
    /**
     * Pretend-conversion: write a fake MP4 instead of spawning FFmpeg.
     *
     * @param string $src WebM source path.
     * @param string $dst MP4 destination path.
     * @return bool Always true.
     */
    protected function convert_file(string $src, string $dst): bool {
        return (bool) file_put_contents($dst, 'fake-mp4-derived-from:' . md5_file($src));
    }
}

/**
 * Item #6 of the 2026-06 feedback round: teacher feedback videos
 * (editor recordings, WebM) play on desktop but not on iPhone, because
 * iOS WebKit does not decode the editor's WebM output.
 *
 * The contract pinned here:
 * - replace_filename_references() rewrites both the rawurlencoded
 *   @@PLUGINFILE@@ URL spelling and the visible link text.
 * - queue_if_needed() queues exactly when a WebM without an MP4
 *   counterpart exists in the grade's submissioncomment filearea.
 * - execute() stores the MP4 next to the WebM, rewrites the saved
 *   comment HTML, and is idempotent.
 * - The grade-save flow in va.php calls queue_if_needed().
 */
final class feedback_video_conversion_test extends \advanced_testcase {
    /**
     * Create course + activity and a grade row owning a feedback
     * filearea.
     *
     * @param string $comment Initial submissioncomment HTML.
     * @return array{gradeid:int,context:\context_module}
     */
    private function make_grade_fixture(string $comment): array {
        global $DB;
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
        $gradeid = (int) $DB->insert_record('videoassessment_grades', (object) [
            'videoassessment' => $activity->id,
            'gradeitem' => 0,
            'timemarked' => 1700000000,
            'grade' => 0,
            'submissioncomment' => $comment,
            'mailed' => 0,
            'isnotifystudent' => 0,
        ]);
        return ['gradeid' => $gradeid, 'context' => $context];
    }

    /**
     * Store a file into a grade's submissioncomment filearea.
     *
     * @param \context_module $context Module context.
     * @param int $gradeid Grade id (filearea itemid).
     * @param string $filename File name.
     * @return \stored_file
     */
    private function store_feedback_file(\context_module $context, int $gradeid, string $filename): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'submissioncomment',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'webm-bytes-' . $filename);
    }

    /**
     * The HTML rewrite must hit both the URL-encoded href and the
     * plain-text label, and leave unrelated names alone.
     *
     * @covers \mod_videoassessment\task\convert_feedback_video::replace_filename_references
     */
    public function test_replace_filename_references(): void {
        $html = '<p>good work!</p>'
            . '<a href="@@PLUGINFILE@@/my%20feedback.webm">my feedback.webm</a>'
            . '<a href="@@PLUGINFILE@@/other.webm">other.webm</a>';

        $out = convert_feedback_video::replace_filename_references(
            $html,
            'my feedback.webm',
            'my feedback.mp4'
        );

        $this->assertStringContainsString('@@PLUGINFILE@@/my%20feedback.mp4', $out);
        $this->assertStringContainsString('>my feedback.mp4</a>', $out);
        $this->assertStringContainsString(
            '@@PLUGINFILE@@/other.webm',
            $out,
            'Unrelated files must not be rewritten.'
        );

        // Boundary: simple name with no characters needing encoding.
        $out2 = convert_feedback_video::replace_filename_references(
            '<a href="@@PLUGINFILE@@/clip.webm">clip.webm</a>',
            'clip.webm',
            'clip.mp4'
        );
        $this->assertSame('<a href="@@PLUGINFILE@@/clip.mp4">clip.mp4</a>', $out2);
    }

    /**
     * queue_if_needed() must queue exactly once for a WebM with no MP4
     * counterpart, and not at all when the area has no WebM or the MP4
     * already exists.
     *
     * @covers \mod_videoassessment\task\convert_feedback_video::queue_if_needed
     */
    public function test_queue_if_needed(): void {
        $this->resetAfterTest();
        ['gradeid' => $gradeid, 'context' => $context] = $this->make_grade_fixture('');

        // Empty filearea: nothing to do.
        $this->assertFalse(convert_feedback_video::queue_if_needed($context->id, $gradeid));

        // Non-video attachment only: nothing to do.
        $this->store_feedback_file($context, $gradeid, 'notes.pdf');
        $this->assertFalse(convert_feedback_video::queue_if_needed($context->id, $gradeid));

        // A WebM appears: queue.
        $this->store_feedback_file($context, $gradeid, 'recording.webm');
        $this->assertTrue(convert_feedback_video::queue_if_needed($context->id, $gradeid));
        $tasks = \core\task\manager::get_adhoc_tasks(convert_feedback_video::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertSame($gradeid, (int) $data->gradeid);
        $this->assertSame((int) $context->id, (int) $data->contextid);

        // MP4 counterpart exists: no further queueing needed.
        $this->store_feedback_file($context, $gradeid, 'recording.mp4');
        $this->assertFalse(convert_feedback_video::queue_if_needed($context->id, $gradeid));
    }

    /**
     * Full pipeline: execute() converts the WebM, stores the MP4 in
     * the same filearea, and rewrites the saved comment HTML.
     *
     * @covers \mod_videoassessment\task\convert_feedback_video::execute
     */
    public function test_execute_converts_and_rewrites(): void {
        global $DB;
        $this->resetAfterTest();
        $html = '<p>Nice fluency.</p><a href="@@PLUGINFILE@@/recording.webm">recording.webm</a>';
        ['gradeid' => $gradeid, 'context' => $context] = $this->make_grade_fixture($html);
        $this->store_feedback_file($context, $gradeid, 'recording.webm');

        $task = new testable_convert_feedback_video();
        $task->set_custom_data((object) ['contextid' => $context->id, 'gradeid' => $gradeid]);
        $task->execute();

        $this->assertTrue(
            get_file_storage()->file_exists(
                $context->id,
                'mod_videoassessment',
                'submissioncomment',
                $gradeid,
                '/',
                'recording.mp4'
            ),
            'The MP4 must be stored next to the WebM original.'
        );
        $saved = $DB->get_field('videoassessment_grades', 'submissioncomment', ['id' => $gradeid]);
        $this->assertStringContainsString('@@PLUGINFILE@@/recording.mp4', $saved);
        $this->assertStringNotContainsString('recording.webm', $saved);

        // Idempotence: a second run must not change anything further.
        $task->execute();
        $savedagain = $DB->get_field('videoassessment_grades', 'submissioncomment', ['id' => $gradeid]);
        $this->assertSame($saved, $savedagain);
    }

    /**
     * A grade whose comment references no WebM (e.g. text-only
     * feedback) must pass through execute() untouched.
     *
     * @covers \mod_videoassessment\task\convert_feedback_video::execute
     */
    public function test_execute_without_webm_is_a_noop(): void {
        global $DB;
        $this->resetAfterTest();
        ['gradeid' => $gradeid, 'context' => $context] = $this->make_grade_fixture('<p>text only</p>');

        $task = new testable_convert_feedback_video();
        $task->set_custom_data((object) ['contextid' => $context->id, 'gradeid' => $gradeid]);
        $task->execute();

        $this->assertSame(
            '<p>text only</p>',
            $DB->get_field('videoassessment_grades', 'submissioncomment', ['id' => $gradeid])
        );
    }

    /**
     * Static wiring contract: the grade-save flow must schedule the
     * conversion so the fix actually fires in production.
     *
     * @coversNothing
     */
    public function test_grade_save_flow_queues_conversion(): void {
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString(
            'convert_feedback_video::queue_if_needed(',
            $vaphp,
            'The grade-save flow must queue the WebM-to-MP4 feedback '
                . 'conversion, otherwise iPhone users keep getting a '
                . 'dead player in "See Report".'
        );
    }
}
