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
 * Tests for the "New Report Available" / "Report Viewed" label.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * 2026-07 request #1: teachers want to confirm that learners read their
 * feedback, and learners want to notice new feedback at a glance.
 *
 * @covers \mod_videoassessment\report_tracker
 */
final class report_tracker_test extends \advanced_testcase {
    /**
     * Create an activity with one learner and one teacher.
     *
     * @param array $settings Activity field overrides.
     * @return array{va:va,student:int,peer:int,teacher:int}
     */
    private function make_activity(array $settings = []): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('videoassessment', $settings + [
            'course' => $course->id,
            'ratingteacher' => 80,
            'ratingself' => 10,
            'ratingpeer' => 10,
            'ratingclass' => 0,
            'delayedteachergrade' => 0,
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $activity->id, $course->id, false, MUST_EXIST);
        $va = new va(\context_module::instance($cm->id), $cm, $course);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $peer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        return ['va' => $va, 'student' => (int) $student->id, 'peer' => (int) $peer->id, 'teacher' => (int) $teacher->id];
    }

    /**
     * Save an assessment row the way view_assess() does.
     *
     * @param va $va Activity.
     * @param int $gradeduser Learner being assessed.
     * @param int $grader Assessor.
     * @param string $gradertype self, peer, teacher, class or training.
     * @param int $timemarked When it was saved.
     */
    private function add_grade(va $va, int $gradeduser, int $grader, string $gradertype, int $timemarked): void {
        global $DB;

        $itemid = $va->get_grade_item('before' . $gradertype, $gradeduser, $grader);
        $DB->insert_record('videoassessment_grades', (object) [
            'videoassessment' => $va->instance,
            'gradeitem' => $itemid,
            'timemarked' => $timemarked,
            'grade' => 70,
            'submissioncomment' => '',
        ]);
        $va->aggregate_grades($gradeduser);
    }

    /**
     * Nothing assessed and nothing opened: no label at all.
     */
    public function test_no_label_without_feedback(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student] = $this->make_activity();

        $this->assertSame(report_tracker::STATUS_NONE, report_tracker::get_status($va, $student));
    }

    /**
     * Teacher feedback makes the report new until the learner opens it.
     */
    public function test_teacher_feedback_is_new_until_viewed(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student, 'teacher' => $teacher] = $this->make_activity();

        $this->add_grade($va, $student, $teacher, 'teacher', 1000);
        $this->assertSame(report_tracker::STATUS_NEW, report_tracker::get_status($va, $student));

        report_tracker::mark_viewed((int) $va->instance, $student, 1001);
        $this->assertSame(report_tracker::STATUS_VIEWED, report_tracker::get_status($va, $student));
    }

    /**
     * Updated feedback (here: a peer assessment saved later) is new again.
     */
    public function test_later_feedback_makes_report_new_again(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student, 'peer' => $peer, 'teacher' => $teacher] = $this->make_activity();

        $this->add_grade($va, $student, $teacher, 'teacher', 1000);
        report_tracker::mark_viewed((int) $va->instance, $student, 1001);
        $this->add_grade($va, $student, $peer, 'peer', 1002);

        $this->assertSame(report_tracker::STATUS_NEW, report_tracker::get_status($va, $student));
        $this->assertSame(1002, report_tracker::get_last_updated($va, $student));
    }

    /**
     * The learner's own self-assessment is not news to them.
     */
    public function test_own_self_assessment_does_not_count(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student] = $this->make_activity();

        $this->add_grade($va, $student, $student, 'self', 1000);

        $this->assertSame(0, report_tracker::get_last_updated($va, $student));
        $this->assertSame(report_tracker::STATUS_NONE, report_tracker::get_status($va, $student));
    }

    /**
     * Class and training assessments are not in the learner's report.
     */
    public function test_class_and_training_do_not_count(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student, 'peer' => $peer] = $this->make_activity();

        $this->add_grade($va, $student, $peer, 'class', 1000);
        $this->add_grade($va, $student, $student, 'training', 1001);

        $this->assertSame(0, report_tracker::get_last_updated($va, $student));
    }

    /**
     * With Delayed Teacher Grade the teacher's feedback only counts once the
     * learner self-assesses, which is when it becomes visible to them.
     */
    public function test_delayed_teacher_grade_counts_from_self_assessment(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student, 'teacher' => $teacher] = $this->make_activity(['delayedteachergrade' => 1]);

        $this->add_grade($va, $student, $teacher, 'teacher', 1000);
        $this->assertSame(0, report_tracker::get_last_updated($va, $student), 'Hidden teacher grade must not count.');

        // The learner opens the (still teacher-less) report, then self-assesses.
        report_tracker::mark_viewed((int) $va->instance, $student, 1001);
        $this->add_grade($va, $student, $student, 'self', 1002);

        $this->assertSame(1002, report_tracker::get_last_updated($va, $student));
        $this->assertSame(report_tracker::STATUS_NEW, report_tracker::get_status($va, $student));
    }

    /**
     * With Self weighting 0% the delay is off, so the teacher grade counts at once.
     */
    public function test_delay_ignored_when_self_weighting_is_zero(): void {
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student, 'teacher' => $teacher] = $this->make_activity([
            'delayedteachergrade' => 1,
            'ratingself' => 0,
            'ratingteacher' => 85,
            'ratingpeer' => 15,
        ]);

        $this->add_grade($va, $student, $teacher, 'teacher', 1000);

        $this->assertSame(1000, report_tracker::get_last_updated($va, $student));
    }

    /**
     * Visits are upserted per learner and cleared by reset().
     */
    public function test_mark_viewed_upserts_and_reset_clears(): void {
        global $DB;
        $this->resetAfterTest();
        ['va' => $va, 'student' => $student] = $this->make_activity();
        $vaid = (int) $va->instance;

        report_tracker::mark_viewed($vaid, $student, 10);
        report_tracker::mark_viewed($vaid, $student, 20);
        $this->assertSame(1, $DB->count_records(report_tracker::TABLE, ['videoassessment' => $vaid, 'userid' => $student]));
        $this->assertSame(20, report_tracker::get_last_viewed($vaid, $student));

        report_tracker::reset($vaid, $student);
        $this->assertSame(0, report_tracker::get_last_viewed($vaid, $student));
    }
}
