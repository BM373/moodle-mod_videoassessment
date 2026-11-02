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
 * Tests for the 2026-07 visibility rules and the screens that apply them.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Covers request #3 (no self-assessment when Self = 0%), request #4
 * (Delayed Teacher Grade with Self = 0%) and how the scores table,
 * report, upload and assess screens apply them together with the
 * report label (#1) and the video lock (#2).
 *
 * @covers \mod_videoassessment\va
 * @covers \mod_videoassessment\grade_table
 */
final class grade_visibility_test extends \advanced_testcase {
    /** @var int */
    private $student;
    /** @var int */
    private $other;
    /** @var int */
    private $teacher;

    /**
     * Create an activity with two learners and a teacher.
     *
     * @param array $settings Activity field overrides.
     * @return va
     */
    private function make_activity(array $settings = []): va {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('videoassessment', $settings + [
            'course' => $course->id,
            'ratingteacher' => 80,
            'ratingself' => 10,
            'ratingpeer' => 10,
            'ratingclass' => 0,
            'delayedteachergrade' => 1,
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $activity->id, $course->id, false, MUST_EXIST);
        $this->student = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->other = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->teacher = (int) $this->getDataGenerator()->create_and_enrol($course, 'editingteacher')->id;

        $PAGE->set_url('/mod/videoassessment/view.php', ['id' => $cm->id]);
        return new va(\context_module::instance($cm->id), $cm, $course);
    }

    /**
     * Save an assessment row the way view_assess() does.
     *
     * @param va $va Activity.
     * @param int $gradeduser Learner.
     * @param int $grader Assessor.
     * @param string $gradertype self, peer or teacher.
     * @param int $grade Score.
     */
    private function add_grade(va $va, int $gradeduser, int $grader, string $gradertype, int $grade = 90): void {
        global $DB;

        $itemid = $va->get_grade_item('before' . $gradertype, $gradeduser, $grader);
        $DB->insert_record('videoassessment_grades', (object) [
            'videoassessment' => $va->instance,
            'gradeitem' => $itemid,
            'timemarked' => time(),
            'grade' => $grade,
            'submissioncomment' => '',
        ]);
        $va->aggregate_grades($gradeduser);
    }

    /**
     * Associate an external-link video with a learner.
     *
     * @param va $va Activity.
     * @param int $userid Learner.
     */
    private function add_link(va $va, int $userid): void {
        global $DB;

        $videoid = $DB->insert_record('videoassessment_videos', (object) [
            'videoassessment' => $va->instance,
            'filepath' => '/',
            'filename' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnailname' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/1.jpg',
            'tmpname' => 'Youtube',
            'originalname' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('videoassessment_video_assocs', (object) [
            'videoassessment' => $va->instance,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $userid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Call a private va method.
     *
     * @param va $va Activity.
     * @param string $method Method name.
     * @return mixed
     */
    private function call(va $va, string $method) {
        $reflection = new \ReflectionMethod(va::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($va);
    }

    /**
     * Aggregated grade record with the given per-type grades (-1 = none).
     *
     * @param int $self Self grade.
     * @param int $peer Peer grade.
     * @param int $teacher Teacher grade.
     * @return \stdClass
     */
    private static function agg(int $self, int $peer, int $teacher): \stdClass {
        return (object) [
            'gradebeforeself' => (string) $self,
            'gradebeforepeer' => (string) $peer,
            'gradebeforeteacher' => (string) $teacher,
            'gradebeforeclass' => '-1',
        ];
    }

    /**
     * Request #3: Self weighting 0% switches self-assessment off.
     */
    public function test_self_assessment_enabled_follows_weighting(): void {
        $this->resetAfterTest();

        $this->assertTrue($this->make_activity()->is_self_assessment_enabled());
        $this->assertFalse($this->make_activity(['ratingself' => 0, 'ratingteacher' => 90])->is_self_assessment_enabled());
    }

    /**
     * The teacher grade is only held back while a self-assessment is still expected.
     */
    public function test_teacher_grade_hidden_only_while_self_assessment_pending(): void {
        $this->resetAfterTest();

        $delayed = $this->make_activity();
        $this->assertTrue($delayed->is_teacher_grade_hidden_from_learner(self::agg(-1, 80, 90)));
        $this->assertFalse($delayed->is_teacher_grade_hidden_from_learner(self::agg(70, 80, 90)));

        $notdelayed = $this->make_activity(['delayedteachergrade' => 0]);
        $this->assertFalse($notdelayed->is_teacher_grade_hidden_from_learner(self::agg(-1, 80, 90)));

        $noself = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 85, 'ratingpeer' => 15]);
        $this->assertFalse($noself->is_teacher_grade_hidden_from_learner(self::agg(-1, 80, 90)));
    }

    /**
     * Request #4: with Self = 0% the total follows the teacher's assessment.
     */
    public function test_total_visibility_rules(): void {
        $this->resetAfterTest();

        $withself = $this->make_activity();
        $this->assertFalse($withself->is_total_visible(self::agg(-1, 80, 90)));
        $this->assertTrue($withself->is_total_visible(self::agg(70, -1, -1)));

        $noself = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 85, 'ratingpeer' => 15]);
        $this->assertFalse($noself->is_total_visible(self::agg(-1, 100, -1)), 'Peer score alone gives no total.');
        $this->assertFalse($noself->is_total_visible(self::agg(100, 96, -1)), 'A stray self score gives no total.');
        $this->assertTrue($noself->is_total_visible(self::agg(-1, 91, 91)));

        $peeronly = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 0, 'ratingpeer' => 100]);
        $this->assertFalse($peeronly->is_total_visible(self::agg(-1, -1, -1)));
        $this->assertTrue($peeronly->is_total_visible(self::agg(-1, 80, -1)));
    }

    /**
     * Request #3: the learner's own row has no self-assessment button at 0%.
     */
    public function test_self_table_has_no_self_assessment_button_at_zero(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 85, 'ratingpeer' => 15]);
        $this->add_link($va, $this->student);
        $this->setUser($this->student);
        $html = (new grade_table($va))->print_self_grade_table();
        $this->assertStringNotContainsString('button-firstassess', $html);
        $this->assertStringContainsString('videopreview.php', $html);

        $va = $this->make_activity();
        $this->add_link($va, $this->student);
        $this->setUser($this->student);
        $this->assertStringContainsString('button-firstassess', (new grade_table($va))->print_self_grade_table());
    }

    /**
     * Request #4: with Self = 0% and delayed grading the learner sees the
     * teacher score and the total as soon as the teacher has assessed.
     */
    public function test_learner_sees_teacher_score_and_total_when_self_is_zero(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 85, 'ratingpeer' => 15]);
        $this->add_grade($va, $this->student, $this->other, 'peer', 91);
        $this->setUser($this->student);
        $table = new grade_table($va);
        $table->print_self_grade_table();
        $this->assertSame('-', $table->data[2][6], 'No total before the teacher has assessed.');

        $this->add_grade($va, $this->student, $this->teacher, 'teacher', 91);
        $table = new grade_table($va);
        $table->print_self_grade_table();
        $this->assertEquals(91, $table->data[2][5]);
        $this->assertEquals(91, $table->data[2][6]);
    }

    /**
     * With self-assessment in use the delay still hides the teacher score.
     */
    public function test_delay_still_applies_with_self_assessment(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->add_grade($va, $this->student, $this->teacher, 'teacher', 91);
        $this->setUser($this->student);
        $table = new grade_table($va);
        $table->print_self_grade_table();

        $this->assertSame('-', $table->data[2][5]);
        $this->assertSame('-', $table->data[2][6]);
    }

    /**
     * Requests #1 and #2 in the teacher's table: report labels, and the
     * reset button replacing the re-upload button once assessed.
     */
    public function test_teacher_table_labels_and_reset_button(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 0]);
        $this->add_link($va, $this->student);
        $this->add_link($va, $this->other);
        $this->add_grade($va, $this->student, $this->teacher, 'teacher');
        $this->setUser($this->teacher);

        $html = (new grade_table($va))->print_teacher_grade_table();
        $this->assertSame(1, substr_count($html, 'value="resetsubmission"'), 'Only the assessed learner can be reset.');
        $this->assertSame(1, substr_count($html, 'action=upload'), 'Only the unassessed learner can be re-linked.');
        $this->assertStringContainsString('videoassessment-report-new', $html);
        $this->assertStringNotContainsString('videoassessment-report-viewed', $html);

        report_tracker::mark_viewed((int) $va->instance, $this->student);
        $html = (new grade_table($va))->print_teacher_grade_table();
        $this->assertStringContainsString('videoassessment-report-viewed', $html);
        $this->assertStringNotContainsString('videoassessment-report-new', $html);
    }

    /**
     * Request #2: once assessed, the learner loses the change buttons.
     */
    public function test_learner_loses_change_buttons_once_assessed(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->add_link($va, $this->student);
        $this->setUser($this->student);
        $this->assertStringContainsString('action=upload', (new grade_table($va))->print_self_grade_table());

        $this->add_grade($va, $this->student, $this->teacher, 'teacher');
        $html = (new grade_table($va))->print_self_grade_table();
        $this->assertStringNotContainsString('action=upload', $html);
        $this->assertStringNotContainsString('value="resetsubmission"', $html);
    }

    /**
     * Request #2: the upload page refuses an assessed learner.
     */
    public function test_upload_page_refuses_assessed_learner(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->add_link($va, $this->student);
        $this->add_grade($va, $this->student, $this->teacher, 'teacher');

        $this->setUser($this->student);
        $html = $this->call($va, 'view_upload_video');
        $this->assertStringContainsString(get_string('videolocked', 'videoassessment'), $html);
        $this->assertStringNotContainsString('name="upload"', $html, 'The upload form must not be offered.');

        $this->setUser($this->teacher);
        $_GET['user'] = $this->student;
        try {
            $html = $this->call($va, 'view_upload_video');
        } finally {
            unset($_GET['user']);
        }
        $this->assertStringContainsString(get_string('videolockedteacher', 'videoassessment'), $html);
    }

    /**
     * Request #3: even a direct URL does not open a self-assessment at 0%.
     */
    public function test_assess_page_refuses_self_assessment_at_zero(): void {
        global $DB;
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingself' => 0, 'ratingteacher' => 85, 'ratingpeer' => 15]);
        $this->setUser($this->student);
        $_GET['userid'] = $this->student;
        try {
            $this->call($va, 'guard_assess_request');
            $this->fail('Expected a redirect.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        } finally {
            unset($_GET['userid']);
        }
        $this->assertFalse($DB->record_exists('videoassessment_grade_items', ['gradeduser' => $this->student]));
    }

    /**
     * Request #2: an assessment posted for a video that was replaced while
     * the form was open is dropped instead of being saved.
     */
    public function test_assessment_for_replaced_video_is_not_saved(): void {
        global $DB;
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->setUser($this->teacher);
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($va->context, 'mod_videoassessment', 'beforeteacher');
        $this->add_link($va, $this->student);

        $_GET['userid'] = $this->student;
        $_POST['assessedvideos'] = '999999-0';
        try {
            $this->call($va, 'guard_assess_request');
            $this->fail('Expected a redirect.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        } finally {
            unset($_GET['userid'], $_POST['assessedvideos']);
        }
        $this->assertFalse($va->has_started_assessment($this->student));
        $this->assertSame(0, $DB->count_records('videoassessment_grades'));

        // Control: with the current video's signature the guard lets the
        // request through, and the form carries that signature.
        $_GET['userid'] = $this->student;
        $_POST['assessedvideos'] = $va->get_video_signature($this->student);
        try {
            $this->call($va, 'guard_assess_request');
            $html = $this->call($va, 'view_assess');
        } finally {
            unset($_GET['userid'], $_POST['assessedvideos']);
        }
        $this->assertStringContainsString(
            'name="assessedvideos" type="hidden" value="' . $va->get_video_signature($this->student) . '"',
            $html
        );
    }

    /**
     * A learner may open only their own report; opening it is recorded.
     */
    public function test_report_access_and_view_recording(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 0]);
        $this->add_grade($va, $this->student, $this->teacher, 'teacher');
        $this->setUser($this->student);

        $_GET['userid'] = $this->other;
        try {
            $this->call($va, 'view_report');
            $this->fail('A learner must not read another learner\'s report.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        } finally {
            unset($_GET['userid']);
        }
        $this->assertSame(report_tracker::STATUS_NEW, report_tracker::get_status($va, $this->student));

        $sink = $this->redirectEvents();
        $_GET['userid'] = $this->student;
        try {
            $this->call($va, 'view_report');
        } finally {
            unset($_GET['userid']);
        }
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(report_tracker::STATUS_VIEWED, report_tracker::get_status($va, $this->student));
        $viewed = array_filter($events, fn($event) => $event instanceof event\report_viewed);
        $this->assertCount(1, $viewed);
        $this->assertEquals($this->student, reset($viewed)->relateduserid);
    }

    /**
     * Delayed Teacher Grade: the report hides the teacher's score and the
     * total from the learner until they self-assess.
     */
    public function test_report_hides_delayed_teacher_score_from_learner(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->setUser($this->teacher);
        $rubricgenerator = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        foreach (['beforepeer', 'beforeteacher'] as $area) {
            $rubricgenerator->get_test_rubric($va->context, 'mod_videoassessment', $area);
        }
        $this->add_grade($va, $this->student, $this->other, 'peer', 55);
        $this->add_grade($va, $this->student, $this->teacher, 'teacher', 93);

        $this->setUser($this->student);
        $_GET['userid'] = $this->student;
        try {
            $html = $this->call($va, 'view_report');
        } finally {
            unset($_GET['userid']);
        }
        $this->assertStringContainsString('rubrictext-peer', $html);
        $this->assertStringNotContainsString('rubrictext-teacher', $html);
        $this->assertStringNotContainsString('comment-score', $html);

        // The teacher still sees everything.
        $this->setUser($this->teacher);
        $_GET['userid'] = $this->student;
        try {
            $html = $this->call($va, 'view_report');
        } finally {
            unset($_GET['userid']);
        }
        $this->assertStringContainsString('rubrictext-teacher', $html);
        $this->assertStringContainsString('comment-score', $html);
    }
}
