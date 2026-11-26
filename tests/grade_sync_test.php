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
 * Tests for how the total reaches the report, the print page and Moodle's gradebook.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/videoassessment/lib.php');

/**
 * Grading E2E on the demo site, 2026-11: the report said "90 + 0 + 0 = 0", the
 * gradebook kept an old grade when the total fell to 0, and it showed the score
 * that Delayed Teacher Grade hides everywhere else.
 *
 * @covers \mod_videoassessment\va
 * @covers \mod_videoassessment\print_page
 * @covers \mod_videoassessment\task\regrade_activity
 */
final class grade_sync_test extends \advanced_testcase {
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
            'ratingself' => 20,
            'ratingpeer' => 0,
            'ratingclass' => 0,
            'delayedteachergrade' => 0,
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $activity->id, $course->id, false, MUST_EXIST);
        $this->student = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->other = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->teacher = (int) $this->getDataGenerator()->create_and_enrol($course, 'editingteacher')->id;

        $PAGE->set_url('/mod/videoassessment/view.php', ['id' => $cm->id]);
        return new va(\context_module::instance($cm->id), $cm, $course);
    }

    /**
     * Save a new assessment the way view_assess() does.
     *
     * @param va $va Activity.
     * @param int $grader Assessor.
     * @param string $gradertype self, peer, teacher or class.
     * @param int $grade Score.
     */
    private function assess(va $va, int $grader, string $gradertype, int $grade): void {
        global $DB;

        $itemid = $va->get_grade_item('before' . $gradertype, $this->student, $grader);
        $DB->insert_record('videoassessment_grades', (object) [
            'videoassessment' => $va->instance,
            'gradeitem' => $itemid,
            'timemarked' => time(),
            'grade' => $grade,
            'submissioncomment' => '',
        ]);
        $va->aggregate_grades($this->student);
    }

    /**
     * Change a saved assessment the way view_assess() does.
     *
     * @param va $va Activity.
     * @param int $grader Assessor.
     * @param string $gradertype self, peer, teacher or class.
     * @param int $grade New score.
     */
    private function reassess(va $va, int $grader, string $gradertype, int $grade): void {
        global $DB;

        $itemid = $va->get_grade_item('before' . $gradertype, $this->student, $grader);
        $DB->set_field('videoassessment_grades', 'grade', $grade, ['gradeitem' => $itemid]);
        $va->aggregate_grades($this->student);
    }

    /**
     * The learner's row in Moodle's gradebook, if there is one.
     *
     * @param va $va Activity.
     * @return \grade_grade|null
     */
    private function gradebook(va $va): ?\grade_grade {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'videoassessment',
            'iteminstance' => $va->instance,
            'itemnumber' => 0,
            'courseid' => $va->course->id,
        ]);
        if (!$item) {
            return null;
        }
        return \grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->student]) ?: null;
    }

    /**
     * The report page, as the teacher sees it.
     *
     * @param va $va Activity.
     * @return string HTML
     */
    private function report_html(va $va): string {
        $method = new \ReflectionMethod(va::class, 'view_report');
        $method->setAccessible(true);
        $_GET['userid'] = $this->student;
        try {
            return $method->invoke($va);
        } finally {
            unset($_GET['userid']);
        }
    }

    /**
     * The print page, as the teacher sees it.
     *
     * @param va $va Activity.
     * @return string HTML
     */
    private function print_html(va $va): string {
        $page = new print_page($va);
        $method = new \ReflectionMethod(print_page::class, 'rubric_report');
        $method->setAccessible(true);
        $_GET['userid'] = $this->student;
        ob_start();
        try {
            $method->invoke($page);
            return ob_get_contents();
        } finally {
            ob_end_clean();
            unset($_GET['userid']);
        }
    }

    /**
     * Bonus settings and what the total must then show.
     *
     * @return array
     */
    public static function bonus_settings(): array {
        return [
            'no bonus' => [0, 0, []],
            'peer bonus' => [1, 0, ['peerfairnessbonus', 'finalscore']],
            'self bonus' => [0, 1, ['selffairnessbonus', 'finalscore']],
            'both bonuses' => [1, 1, ['selffairnessbonus', 'peerfairnessbonus', 'finalscore']],
        ];
    }

    /**
     * Build an activity with a teacher assessment, logged in as the teacher.
     *
     * @param int $peerbonus Peer fairness bonus setting.
     * @param int $selfbonus Self fairness bonus setting.
     * @return va
     */
    private function make_assessed_activity(int $peerbonus, int $selfbonus): va {
        $va = $this->make_activity(['fairnessbonus' => $peerbonus, 'selffairnessbonus' => $selfbonus]);
        $this->setUser($this->teacher);
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($va->context, 'mod_videoassessment', 'beforeteacher');
        $this->assess($va, $this->teacher, 'teacher', 90);
        return $va;
    }

    /**
     * Check that the total shows exactly the expected parts.
     *
     * @param string $html Page.
     * @param string[] $expected String identifiers that must appear.
     */
    private function assert_total_parts(string $html, array $expected): void {
        $this->assertStringContainsString(va::str('totalscore'), $html);
        foreach (['selffairnessbonus', 'peerfairnessbonus', 'finalscore'] as $part) {
            if (in_array($part, $expected)) {
                $this->assertStringContainsString(va::str($part), $html);
            } else {
                $this->assertStringNotContainsString(va::str($part), $html);
            }
        }
    }

    /**
     * The report shows a bonus and a final score only where a bonus is in use.
     *
     * @dataProvider bonus_settings
     * @param int $peerbonus Peer fairness bonus setting.
     * @param int $selfbonus Self fairness bonus setting.
     * @param string[] $expected String identifiers that must appear.
     */
    public function test_report_total(int $peerbonus, int $selfbonus, array $expected): void {
        $this->resetAfterTest();

        $va = $this->make_assessed_activity($peerbonus, $selfbonus);
        $this->assert_total_parts($this->report_html($va), $expected);
    }

    /**
     * The print page shows the same parts as the report.
     *
     * @dataProvider bonus_settings
     * @param int $peerbonus Peer fairness bonus setting.
     * @param int $selfbonus Self fairness bonus setting.
     * @param string[] $expected String identifiers that must appear.
     */
    public function test_print_total(int $peerbonus, int $selfbonus, array $expected): void {
        $this->resetAfterTest();

        $va = $this->make_assessed_activity($peerbonus, $selfbonus);
        $this->assert_total_parts($this->print_html($va), $expected);
    }

    /**
     * The gradebook follows a total that falls all the way to 0.
     */
    public function test_gradebook_follows_total_down_to_zero(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingteacher' => 100, 'ratingself' => 0]);
        $this->assess($va, $this->teacher, 'teacher', 50);
        $this->assertEquals(50, $this->gradebook($va)->finalgrade);

        $this->reassess($va, $this->teacher, 'teacher', 0);
        $this->assertSame(0, (int) $va->get_aggregated_grades($this->student)->gradebefore);
        $this->assertEquals(0, $this->gradebook($va)->finalgrade);
        $this->assertNotNull($this->gradebook($va)->finalgrade);
    }

    /**
     * A first assessment of 0 is a grade of 0, not an empty grade.
     */
    public function test_gradebook_records_a_first_assessment_of_zero(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingteacher' => 100, 'ratingself' => 0]);
        $this->assess($va, $this->teacher, 'teacher', 0);
        $this->assertNotNull($this->gradebook($va));
        $this->assertNotNull($this->gradebook($va)->finalgrade);
        $this->assertEquals(0, $this->gradebook($va)->finalgrade);
    }

    /**
     * A learner nobody has assessed gets no grade from a recalculation.
     */
    public function test_regrade_gives_no_grade_without_an_assessment(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $va->regrade();
        $grade = $this->gradebook($va);
        $this->assertTrue($grade === null || $grade->finalgrade === null);
    }

    /**
     * An assessment in an area with no weight is not in the total, so it is not a grade of 0.
     */
    public function test_assessment_without_weight_gives_no_grade(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['ratingteacher' => 100, 'ratingself' => 0]);
        $this->assess($va, $this->other, 'class', 90);
        $grade = $this->gradebook($va);
        $this->assertTrue($grade === null || $grade->finalgrade === null);
    }

    /**
     * When the only assessment stops counting, the old grade must not stay behind.
     */
    public function test_gradebook_clears_when_assessment_loses_its_weight(): void {
        global $DB;
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->assess($va, $this->student, 'self', 100);
        $this->assertEquals(20, $this->gradebook($va)->finalgrade);

        $DB->update_record('videoassessment', (object) ['id' => $va->instance, 'ratingteacher' => 100, 'ratingself' => 0]);
        $va = new va($va->context, $va->cm, $va->course);
        $va->regrade();
        $this->assertNull($this->gradebook($va)->finalgrade);
    }

    /**
     * Delayed Teacher Grade: the learner's gradebook hides the grade until they
     * have self-assessed, and the teacher's value stays there all along.
     */
    public function test_delayed_grade_hidden_in_gradebook_until_self_assessment(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 1]);
        $this->assess($va, $this->teacher, 'teacher', 90);
        $grade = $this->gradebook($va);
        $this->assertEquals(72, $grade->finalgrade);
        $this->assertTrue($grade->is_hidden());

        $this->assess($va, $this->student, 'self', 50);
        $grade = $this->gradebook($va);
        $this->assertEquals(82, $grade->finalgrade);
        $this->assertFalse($grade->is_hidden());
    }

    /**
     * Without the setting nothing is hidden.
     */
    public function test_grade_not_hidden_without_delay(): void {
        $this->resetAfterTest();

        $va = $this->make_activity();
        $this->assess($va, $this->teacher, 'teacher', 90);
        $this->assertFalse($this->gradebook($va)->is_hidden());
    }

    /**
     * With a Self weighting of 0% there is no self-assessment to wait for.
     */
    public function test_delay_does_not_hide_when_self_weighting_is_zero(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 1, 'ratingteacher' => 100, 'ratingself' => 0]);
        $this->assess($va, $this->teacher, 'teacher', 90);
        $this->assertFalse($this->gradebook($va)->is_hidden());
    }

    /**
     * Save the activity settings the way the form does.
     *
     * @param va $va Activity.
     * @param array $changes Fields to change.
     */
    private function save_settings(va $va, array $changes): void {
        global $DB;

        $record = $DB->get_record('videoassessment', ['id' => $va->instance], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->gradingduedate = 0;
        foreach ($changes as $field => $value) {
            $record->$field = $value;
        }
        videoassessment_update_instance($record, null);
    }

    /**
     * Turning the setting off or on applies to the grades already there.
     */
    public function test_changing_the_setting_applies_to_existing_grades(): void {
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 1]);
        $this->assess($va, $this->teacher, 'teacher', 90);
        $this->assertTrue($this->gradebook($va)->is_hidden());

        $this->save_settings($va, ['delayedteachergrade' => 0]);
        $this->assertFalse($this->gradebook($va)->is_hidden());

        $this->save_settings($va, ['delayedteachergrade' => 1]);
        $this->assertTrue($this->gradebook($va)->is_hidden());
    }

    /**
     * The upgrade queues the activities that use the setting and have weights.
     */
    public function test_queue_delayed_activities(): void {
        global $DB;
        $this->resetAfterTest();

        $delayed = $this->make_activity(['delayedteachergrade' => 1]);
        $this->make_activity();
        $noweights = $this->make_activity(['delayedteachergrade' => 1]);
        $DB->update_record('videoassessment', (object) [
            'id' => $noweights->instance, 'ratingteacher' => 0, 'ratingself' => 0, 'ratingpeer' => 0, 'ratingclass' => 0,
        ]);

        task\regrade_activity::queue_delayed_activities();

        $queued = \core\task\manager::get_adhoc_tasks(task\regrade_activity::class);
        $this->assertCount(1, $queued);
        $this->assertEquals($delayed->instance, reset($queued)->get_custom_data()->videoassessment);
    }

    /**
     * Grades from before this fix get hidden by the task the upgrade queues.
     */
    public function test_task_hides_grades_from_before_the_fix(): void {
        global $DB;
        $this->resetAfterTest();

        $va = $this->make_activity(['delayedteachergrade' => 1]);
        $this->assess($va, $this->teacher, 'teacher', 90);
        $DB->set_field('grade_grades', 'hidden', 0, ['id' => $this->gradebook($va)->id]);
        $this->assertFalse($this->gradebook($va)->is_hidden());

        $task = new task\regrade_activity();
        $task->set_custom_data(['videoassessment' => $va->instance]);
        $task->execute();

        $this->assertTrue($this->gradebook($va)->is_hidden());
        $this->assertEquals(72, $this->gradebook($va)->finalgrade);
    }

    /**
     * An activity deleted before the task runs is not an error.
     */
    public function test_task_ignores_a_deleted_activity(): void {
        $this->resetAfterTest();
        $this->expectNotToPerformAssertions();

        $task = new task\regrade_activity();
        $task->set_custom_data(['videoassessment' => -1]);
        $task->execute();
    }
}
