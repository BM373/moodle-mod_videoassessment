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
 * Integration tests for the analytics events introduced by item #10.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\event;

/**
 * Integration tests for the production call sites that fire the four
 * mod_videoassessment analytics events.
 *
 * The companion {@see event_test} class proves each event class
 * satisfies the moodle event contract in isolation. This file picks up
 * where that one stops: it boots a real course and videoassessment
 * activity, then drives the production code paths that are supposed to
 * fire the events and asserts the events actually reach the redirected
 * logstore sink.
 *
 * Covered call sites:
 * - bulkupload/lib.php video_data_add()          -> video_uploaded
 * - bulkupload/lib.php youtube_video_data_add()  -> video_uploaded
 * - classes/va.php emit_grade_save_event()       -> grade_assigned
 *                                                  / peer_review_submitted
 *
 * The report_viewed event fires from print.php at the script-top
 * level, which cannot be re-entered from PHPUnit without re-running the
 * whole controller; the event-contract test in {@see event_test} plus
 * the static call-site assertion below cover that path.
 */
final class event_integration_test extends \advanced_testcase {
    /**
     * Build a real course, a videoassessment activity and the matching
     * coursemodule / context for the rest of the test class to use.
     *
     * @return array{course:\stdClass,activity:\stdClass,cm:\cm_info,context:\context_module}
     */
    private function build_fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(
            'videoassessment',
            ['course' => $course->id, 'name' => 'Speaking task']
        );
        $cm = \cm_info::create(get_coursemodule_from_instance(
            'videoassessment',
            $activity->id,
            $course->id,
            false,
            MUST_EXIST
        ));
        $context = \context_module::instance($cm->id);
        return compact('course', 'activity', 'cm', 'context');
    }

    /**
     * Capture every event triggered while running the callback and
     * return them.
     *
     * @param callable $callback Code that triggers the event(s) under test.
     * @return \core\event\base[]
     */
    private function capture_events_during(callable $callback): array {
        $sink = $this->redirectEvents();
        $callback();
        $events = $sink->get_events();
        $sink->close();
        return $events;
    }

    /**
     * Pick events of a given class from a captured event list.
     *
     * @param \core\event\base[] $events
     * @param string $class Fully-qualified event class name.
     * @return \core\event\base[]
     */
    private function filter_by_class(array $events, string $class): array {
        return array_values(array_filter(
            $events,
            static fn($e) => $e instanceof $class
        ));
    }

    /**
     * The bulk uploader's direct-upload helper must emit one
     * video_uploaded event carrying the new videoassessment_videos.id
     * as the objectid and the original filename in the payload.
     *
     * @covers \videoassessment_bulkupload::video_data_add
     * @covers \mod_videoassessment\event\video_uploaded
     */
    public function test_video_uploaded_fires_from_bulkupload_direct_upload(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $fixture = $this->build_fixture();
        $cm = $fixture['cm'];
        $activity = $fixture['activity'];

        $bulkupload = new \videoassessment_bulkupload($cm->id);
        $events = $this->capture_events_during(function () use ($bulkupload) {
            $bulkupload->video_data_add('uploaded.tmp', 'lesson1.mp4');
        });

        $matched = $this->filter_by_class($events, video_uploaded::class);
        $this->assertCount(
            1,
            $matched,
            'video_data_add() must trigger exactly one video_uploaded event.'
        );
        $event = $matched[0];
        $this->assertSame((int)$activity->id, (int)$event->other['videoassessmentid']);
        $this->assertSame('lesson1.mp4', $event->other['filename']);
        $this->assertGreaterThan(
            0,
            $event->objectid,
            'objectid must be the newly-inserted videoassessment_videos id.'
        );
    }

    /**
     * The bulk uploader's YouTube helper must emit a video_uploaded
     * event using the same shape as the direct-upload path, with the
     * YouTube URL / title sitting in the filename payload.
     *
     * @covers \videoassessment_bulkupload::youtube_video_data_add
     * @covers \mod_videoassessment\event\video_uploaded
     */
    public function test_video_uploaded_fires_from_bulkupload_youtube(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');
        $fixture = $this->build_fixture();
        $cm = $fixture['cm'];
        $activity = $fixture['activity'];

        $bulkupload = new \videoassessment_bulkupload($cm->id);
        $events = $this->capture_events_during(function () use ($bulkupload) {
            $bulkupload->youtube_video_data_add(
                '/',
                'video.mp4',
                'thumb.jpg',
                'Youtube',
                'https://www.youtube.com/watch?v=ABC123'
            );
        });

        $matched = $this->filter_by_class($events, video_uploaded::class);
        $this->assertCount(
            1,
            $matched,
            'youtube_video_data_add() must trigger exactly one video_uploaded event.'
        );
        $event = $matched[0];
        $this->assertSame((int)$activity->id, (int)$event->other['videoassessmentid']);
        $this->assertSame(
            'https://www.youtube.com/watch?v=ABC123',
            $event->other['filename'],
            'YouTube uploads must carry the URL as the filename payload.'
        );
    }

    /**
     * Invoke the production emit_grade_save_event() helper as the
     * view_assess() form handler does and confirm the right event
     * class is fired for each grader type.
     *
     * @param string $gradertype Value passed by the form handler.
     * @param string $expectedclass Event class expected to be triggered.
     * @return void
     */
    private function assert_grade_event_for(string $gradertype, string $expectedclass): void {
        $fixture = $this->build_fixture();
        $context = $fixture['context'];
        $cm = $fixture['cm'];
        $course = $fixture['course'];
        $va = new \mod_videoassessment\va($context, $cm, $course);

        $reflect = new \ReflectionMethod($va, 'emit_grade_save_event');
        $reflect->setAccessible(true);

        $events = $this->capture_events_during(function () use ($reflect, $va, $gradertype) {
            $reflect->invoke($va, 555, 777, $gradertype, 'before');
        });

        $matched = $this->filter_by_class($events, $expectedclass);
        $this->assertCount(
            1,
            $matched,
            "emit_grade_save_event() with gradertype='{$gradertype}' "
                . "must trigger exactly one {$expectedclass} event."
        );
        $event = $matched[0];
        $this->assertSame(555, (int)$event->objectid);
        $this->assertSame(777, (int)$event->relateduserid);
        $this->assertSame($gradertype, $event->other['gradertype']);
        $this->assertSame('before', $event->other['timing']);
    }

    /**
     * gradertype == 'teacher' must fire grade_assigned.
     *
     * @covers \mod_videoassessment\va::emit_grade_save_event
     * @covers \mod_videoassessment\event\grade_assigned
     */
    public function test_teacher_save_fires_grade_assigned(): void {
        $this->assert_grade_event_for('teacher', grade_assigned::class);
    }

    /**
     * gradertype == 'peer' must fire peer_review_submitted.
     *
     * @covers \mod_videoassessment\va::emit_grade_save_event
     * @covers \mod_videoassessment\event\peer_review_submitted
     */
    public function test_peer_save_fires_peer_review_submitted(): void {
        $this->assert_grade_event_for('peer', peer_review_submitted::class);
    }

    /**
     * gradertype == 'self' must also fire peer_review_submitted; the
     * non-teacher branch of emit_grade_save_event() bundles self and
     * peer reviews under the same analytics event because they share
     * the same observer audience (the student's own learning journal).
     *
     * @covers \mod_videoassessment\va::emit_grade_save_event
     * @covers \mod_videoassessment\event\peer_review_submitted
     */
    public function test_self_save_fires_peer_review_submitted(): void {
        $this->assert_grade_event_for('self', peer_review_submitted::class);
    }

    /**
     * Static guard: the view_assess() handler must keep calling the
     * extracted emit_grade_save_event() helper. If a future refactor
     * inlines the dispatch again the per-grader-type analytics events
     * silently regress to "fires only the teacher branch", and this
     * test would not catch it without the call-site check.
     *
     * @covers \mod_videoassessment\va::view_assess
     */
    public function test_view_assess_dispatches_through_helper(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/mod/videoassessment/classes/va.php');
        $this->assertStringContainsString(
            '$this->emit_grade_save_event(',
            $source,
            'view_assess() must continue to delegate grade-event '
                . 'dispatch to emit_grade_save_event() so the per-'
                . 'gradertype branching stays test-covered.'
        );
    }

    /**
     * Static guard: print.php must keep emitting report_viewed at the
     * top of its dispatch flow. The script runs outside any class so
     * it cannot be re-entered from PHPUnit, but the call-site check
     * paired with the event-contract test in {@see event_test} keeps
     * the production wiring honest.
     *
     * @covers \mod_videoassessment\event\report_viewed
     */
    public function test_print_php_emits_report_viewed(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/mod/videoassessment/print.php');
        $this->assertStringContainsString(
            '\\mod_videoassessment\\event\\report_viewed::create',
            $source,
            'print.php must keep firing the report_viewed event so '
                . 'analytics layers can separate report visits from '
                . 'generic activity views.'
        );
        $this->assertMatchesRegularExpression(
            '~report_viewed::create\(.*?\)\s*->\s*trigger\(\)~s',
            $source,
            'print.php must call ->trigger() on the report_viewed '
                . 'event instance so it actually reaches the logstore.'
        );
    }
}
