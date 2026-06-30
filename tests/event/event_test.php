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
 * Unit tests for the four new mod_videoassessment events.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\event;

/**
 * Unit tests for the four new mod_videoassessment events introduced
 * by item #10 of the 2026-04 fix programme.
 *
 * The plugin used to emit only `course_module_viewed` and
 * `course_module_instance_list_viewed`, which made it hard for
 * institutional analytics layers to track meaningful student activity
 * (uploads, peer reviews, grading, report visits). This test fixes
 * the contract for each new event class: it must extend `\core\event\base`,
 * declare the right `crud` / `edulevel`, accept the expected snapshot,
 * and produce a non-empty localised name and description.
 */
final class event_test extends \advanced_testcase {
    /**
     * Build a stub course-level context that the event tests can attach to.
     *
     * The events do not enforce a module-level context, so to keep the
     * test independent of the videoassessment activity generator (and
     * therefore independent of the install.xml schema details), we use
     * a freshly-created course's context_course.
     *
     * @return array{course:object,vaid:int,context:\context_course}
     */
    private function build_fixture(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        // Stand-in videoassessment id; the event payload only requires
        // an integer here for the description templating.
        $vaid = 4242;
        return compact('course', 'vaid', 'context');
    }

    /**
     * Confirm video_uploaded carries the expected metadata and triggers cleanly.
     *
     * @covers \mod_videoassessment\event\video_uploaded
     */
    public function test_video_uploaded_event(): void {
        ['vaid' => $vaid, 'context' => $context] = $this->build_fixture();
        $event = video_uploaded::create([
            'context' => $context,
            'objectid' => 42,
            'other' => ['videoassessmentid' => $vaid, 'filename' => 'sample.mp4'],
        ]);
        $this->assertInstanceOf('\\core\\event\\base', $event);
        $this->assertSame('videoassessment_videos', $event->objecttable);
        $this->assertSame('c', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_PARTICIPATING, $event->edulevel);
        $this->assertNotEmpty($event->get_name());
        $this->assertNotEmpty($event->get_description());
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(video_uploaded::class, $events[0]);
    }

    /**
     * Confirm peer_review_submitted carries the expected metadata and triggers cleanly.
     *
     * @covers \mod_videoassessment\event\peer_review_submitted
     */
    public function test_peer_review_submitted_event(): void {
        ['vaid' => $vaid, 'context' => $context] = $this->build_fixture();
        $event = peer_review_submitted::create([
            'context' => $context,
            'objectid' => 1001,
            'relateduserid' => 7,
            'other' => ['videoassessmentid' => $vaid, 'timing' => 'before'],
        ]);
        $this->assertInstanceOf('\\core\\event\\base', $event);
        $this->assertSame('videoassessment_grades', $event->objecttable);
        $this->assertSame('c', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_PARTICIPATING, $event->edulevel);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
    }

    /**
     * Confirm grade_assigned carries the expected metadata and triggers cleanly.
     *
     * @covers \mod_videoassessment\event\grade_assigned
     */
    public function test_grade_assigned_event(): void {
        ['vaid' => $vaid, 'context' => $context] = $this->build_fixture();
        $event = grade_assigned::create([
            'context' => $context,
            'objectid' => 1002,
            'relateduserid' => 7,
            'other' => ['videoassessmentid' => $vaid, 'gradertype' => 'teacher', 'timing' => 'before'],
        ]);
        $this->assertSame('videoassessment_grades', $event->objecttable);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_TEACHING, $event->edulevel);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
    }

    /**
     * Confirm report_viewed carries the expected metadata and triggers cleanly.
     *
     * @covers \mod_videoassessment\event\report_viewed
     */
    public function test_report_viewed_event(): void {
        ['vaid' => $vaid, 'context' => $context] = $this->build_fixture();
        $event = report_viewed::create([
            'context' => $context,
            'objectid' => $vaid,
            'relateduserid' => 7,
            'other' => ['videoassessmentid' => $vaid],
        ]);
        $this->assertSame('videoassessment', $event->objecttable);
        $this->assertSame('r', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_PARTICIPATING, $event->edulevel);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
    }
}
