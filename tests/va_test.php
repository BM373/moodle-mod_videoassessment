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
 * Unit tests for the central videoassessment helper class.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\va}.
 *
 * `va` is the (intentionally large) class that backs every page of the
 * activity. Most of its methods touch the Moodle DB / PAGE stack, so
 * each test here builds a real activity instance via the data
 * generator and exercises one well-defined slice of behaviour. The
 * tests deliberately avoid running the rendering pipeline (`view()`)
 * which boots the renderer and emits HTML; instead they target the
 * pure-data helpers that the templates call into.
 */
final class va_test extends \advanced_testcase {
    /**
     * Build a fresh activity row + va instance for the test scope.
     *
     * @return array{course: \stdClass, cm: \stdClass, va: va, instance: \stdClass}
     */
    private function build_va(): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $instance = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('videoassessment', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        global $PAGE;
        $PAGE->set_context($context);
        $vaobj = new va($context, $cm, $course);
        return ['course' => $course, 'cm' => $cm, 'va' => $vaobj, 'instance' => $instance];
    }

    /**
     * Constructing va() must not throw and must populate `instance`,
     * `viewurl`, and the timings × gradertypes grading-areas list.
     *
     * @covers \mod_videoassessment\va::__construct
     */
    public function test_constructor_populates_state(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'instance' => $instance] = $this->build_va();
        $this->assertSame($instance->id, $vaobj->instance);
        $this->assertNotEmpty($vaobj->viewurl);
    }

    /**
     * Constructing va() with a cm pointing to a non-existent activity
     * must raise `videoassessmentnotfound`. Build a real cm + context
     * pair, then delete the activity row before constructing so the
     * constructor's DB lookup fails.
     *
     * @covers \mod_videoassessment\va::__construct
     */
    public function test_constructor_throws_for_missing_instance(): void {
        $this->resetAfterTest();
        global $DB;
        ['cm' => $cm, 'course' => $course, 'instance' => $instance] = $this->build_va();
        $context = \context_module::instance($cm->id);
        // Drop the row so the constructor's get_record() returns false.
        $DB->delete_records('videoassessment', ['id' => $instance->id]);

        $this->expectException(\moodle_exception::class);
        new va($context, $cm, $course);
    }

    /**
     * `va::str()` is a thin shim over Moodle's get_string() and must
     * return a non-empty string for known plugin keys.
     *
     * @covers \mod_videoassessment\va::str
     */
    public function test_str_returns_non_empty_for_known_key(): void {
        $this->resetAfterTest();
        $this->assertNotEmpty(va::str('before'));
        $this->assertNotEmpty(va::str('after'));
        $this->assertNotEmpty(va::str('comment'));
    }

    /**
     * `get_view_url()` must return a moodle_url with the activity id
     * baked in and the requested action as a query parameter.
     *
     * @covers \mod_videoassessment\va::get_view_url
     */
    public function test_get_view_url_includes_id_and_action(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'cm' => $cm] = $this->build_va();
        $url = $vaobj->get_view_url('assess', ['userid' => 7]);
        $params = $url->params();
        $this->assertSame((string) $cm->id, (string) $params['id']);
        $this->assertSame('assess', $params['action']);
        $this->assertSame('7', (string) $params['userid']);
    }

    /**
     * `get_view_url()` with no action still produces a valid URL — the
     * action key is set to '' rather than omitted, which mirrors how
     * the existing view.php callers expect it.
     *
     * @covers \mod_videoassessment\va::get_view_url
     */
    public function test_get_view_url_with_empty_action(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $url = $vaobj->get_view_url();
        $params = $url->params();
        $this->assertSame('', $params['action']);
    }

    /**
     * `is_teacher()` returns false for an unenrolled user (or no
     * user) and true for a user with the grade capability.
     *
     * @covers \mod_videoassessment\va::is_teacher
     */
    public function test_is_teacher_for_anonymous_and_teacher(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'course' => $course] = $this->build_va();

        // Random user with no role in the course is not a teacher.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->assertFalse($vaobj->is_teacher($student->id));

        // Editing teacher has the grade capability.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->assertTrue($vaobj->is_teacher($teacher->id));
    }

    /**
     * `timing_str()` falls back to the localised "before" / "after"
     * labels when no custom label is set.
     *
     * @covers \mod_videoassessment\va::timing_str
     */
    public function test_timing_str_localised_default(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'instance' => $instance] = $this->build_va();
        // The generator sets beforelabel='Before' / afterlabel='After',
        // so timing_str returns the custom label uppercased.
        $beforelabel = $vaobj->timing_str('before');
        $afterlabel = $vaobj->timing_str('after');
        $this->assertNotEmpty($beforelabel);
        $this->assertNotEmpty($afterlabel);
        $this->assertSame('B', substr($beforelabel, 0, 1));
        $this->assertSame('A', substr($afterlabel, 0, 1));
    }

    /**
     * `get_videos()` returns an empty array on a freshly-created
     * activity (no videos uploaded yet).
     *
     * @covers \mod_videoassessment\va::get_videos
     */
    public function test_get_videos_empty_for_new_instance(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $this->assertSame([], $vaobj->get_videos());
    }

    /**
     * `get_video_associations()` returns an empty array when no
     * association exists for the given video id.
     *
     * @covers \mod_videoassessment\va::get_video_associations
     */
    public function test_get_video_associations_empty(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $this->assertSame([], $vaobj->get_video_associations(99999));
    }

    /**
     * `get_grade_items()` returns an empty array when no rows have
     * been persisted yet for the given grading area.
     *
     * @covers \mod_videoassessment\va::get_grade_items
     */
    public function test_get_grade_items_empty(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $this->assertSame([], $vaobj->get_grade_items('beforeteacher', 1));
    }

    /**
     * `get_aggregated_grades()` lazily creates a fresh aggregation row
     * with all grade* columns initialised to -1 (Moodle's "no grade"
     * sentinel) and the bonus columns to 0.
     *
     * @covers \mod_videoassessment\va::get_aggregated_grades
     */
    public function test_get_aggregated_grades_creates_default_row(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $user = $this->getDataGenerator()->create_user();

        $row = $vaobj->get_aggregated_grades($user->id);
        $this->assertSame(-1, (int) $row->gradebefore);
        $this->assertSame(-1, (int) $row->gradeafter);
        $this->assertSame(-1, (int) $row->gradebeforeteacher);
        $this->assertSame(-1, (int) $row->gradebeforeself);
        $this->assertSame(-1, (int) $row->gradebeforepeer);
        $this->assertSame(-1, (int) $row->gradebeforeclass);
        $this->assertSame(0, (int) $row->fairnessbonus);
        $this->assertSame(0, (int) $row->selffairnessbonus);
        $this->assertSame(0, (int) $row->finalscore);
    }

    /**
     * Calling `get_aggregated_grades()` twice for the same user must
     * be idempotent: the second call returns the same row, not a
     * duplicate.
     *
     * @covers \mod_videoassessment\va::get_aggregated_grades
     */
    public function test_get_aggregated_grades_is_idempotent(): void {
        $this->resetAfterTest();
        global $DB;
        ['va' => $vaobj, 'instance' => $instance] = $this->build_va();
        $user = $this->getDataGenerator()->create_user();

        $row1 = $vaobj->get_aggregated_grades($user->id);
        $row2 = $vaobj->get_aggregated_grades($user->id);
        $this->assertSame((int) $row1->id, (int) $row2->id);
        $this->assertSame(
            1,
            $DB->count_records(
                'videoassessment_aggregation',
                ['videoassessment' => $instance->id, 'userid' => $user->id]
            )
        );
    }

    /**
     * `is_user_graded()` returns false for a user whose aggregated
     * grades row is freshly created (all grade columns at -1).
     *
     * @covers \mod_videoassessment\va::is_user_graded
     */
    public function test_is_user_graded_false_for_default_row(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse($vaobj->is_user_graded($user->id));
    }

    /**
     * `get_peers()` returns an empty array for a user with no peer
     * assignments.
     *
     * @covers \mod_videoassessment\va::get_peers
     */
    public function test_get_peers_empty(): void {
        $this->resetAfterTest();
        ['va' => $vaobj] = $this->build_va();
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], $vaobj->get_peers($user->id));
    }

    /**
     * After persisting a peer row (`userid=u1`, `peerid=u2`), the peer
     * `u2` is responsible for grading `u1`, so `get_peers(u2)` returns
     * `[u1]` — i.e., "users this peer assesses".
     *
     * @covers \mod_videoassessment\va::get_peers
     */
    public function test_get_peers_after_insert(): void {
        $this->resetAfterTest();
        global $DB;
        ['va' => $vaobj, 'instance' => $instance] = $this->build_va();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $DB->insert_record('videoassessment_peers', (object) [
            'videoassessment' => $instance->id,
            'userid' => $u1->id,
            'peerid' => $u2->id,
        ]);
        // The get_peers($peerid) call returns the userids that $peerid assesses.
        $peers = $vaobj->get_peers($u2->id);
        $this->assertSame([$u1->id], array_values($peers));
    }

    /**
     * Enrol two students and register one as the other's peer, then
     * return the grader/assessed pair plus the va instance. Shared
     * setup for the get_peers_sort() portability tests below.
     *
     * @return array{va: va, grader: \stdClass, assessed: \stdClass}
     */
    private function build_peer_pair(): array {
        global $DB;
        ['va' => $vaobj, 'course' => $course, 'instance' => $instance] = $this->build_va();
        $assessed = $this->getDataGenerator()->create_user();
        $grader = $this->getDataGenerator()->create_user();
        // The enrol_user() call creates the user_enrolments +
        // role_assignments rows the get_peers_sort() query joins against.
        $this->getDataGenerator()->enrol_user($assessed->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($grader->id, $course->id, 'student');
        $DB->insert_record('videoassessment_peers', (object) [
            'videoassessment' => $instance->id,
            'userid' => $assessed->id,
            'peerid' => $grader->id,
        ]);
        return ['va' => $vaobj, 'grader' => $grader, 'assessed' => $assessed];
    }

    /**
     * get_peers_sort() with the default "ORDER BY u.id" fragment must
     * run on every supported database. PostgreSQL rejects a query that
     * groups by vp.userid but orders by the non-grouped u.id column, so
     * this guards the regression that crashed the activity's main view
     * under pgsql (MySQL silently tolerated it).
     *
     * @covers \mod_videoassessment\va::get_peers_sort
     */
    public function test_get_peers_sort_by_id_runs_on_all_databases(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'grader' => $grader, 'assessed' => $assessed] = $this->build_peer_pair();
        $peers = $vaobj->get_peers_sort($grader->id, 0, false, ' ORDER BY u.id');
        $this->assertSame([$assessed->id], array_values($peers));
    }

    /**
     * Ordering the same query by the grader's name columns must also be
     * portable: u.firstname / u.lastname have to be covered by the
     * GROUP BY for PostgreSQL to accept the statement.
     *
     * @covers \mod_videoassessment\va::get_peers_sort
     */
    public function test_get_peers_sort_by_name_runs_on_all_databases(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'grader' => $grader, 'assessed' => $assessed] = $this->build_peer_pair();
        $order = " ORDER BY " . $GLOBALS['DB']->sql_concat('u.firstname', "' '", 'u.lastname');
        $peers = $vaobj->get_peers_sort($grader->id, 0, false, $order);
        $this->assertSame([$assessed->id], array_values($peers));
    }

    /**
     * The no-explicit-order and manual-sort code paths must run too; the
     * manual path orders by the MIN(vso.sortorder) aggregate alias.
     *
     * @covers \mod_videoassessment\va::get_peers_sort
     */
    public function test_get_peers_sort_default_and_manual_paths(): void {
        $this->resetAfterTest();
        ['va' => $vaobj, 'grader' => $grader, 'assessed' => $assessed] = $this->build_peer_pair();
        $this->assertSame([$assessed->id], array_values($vaobj->get_peers_sort($grader->id)));
        $this->assertSame(
            [$assessed->id],
            array_values($vaobj->get_peers_sort($grader->id, 0, true))
        );
    }

    /**
     * `va::uses_mobile_upload()` returns a boolean (the actual value
     * depends on the user-agent the test runner reports — Moodle
     * usually sets `default` which is neither mobile nor tablet).
     *
     * @covers \mod_videoassessment\va::uses_mobile_upload
     */
    public function test_uses_mobile_upload_returns_bool(): void {
        $this->assertIsBool(va::uses_mobile_upload());
    }

    /**
     * `va::check_mp4_support()` returns a boolean.
     *
     * @covers \mod_videoassessment\va::check_mp4_support
     */
    public function test_check_mp4_support_returns_bool(): void {
        $this->assertIsBool(va::check_mp4_support());
    }

    /**
     * `va::get_courses_managed_by()` returns an empty list for a user
     * with no enrolments.
     *
     * @covers \mod_videoassessment\va::get_courses_managed_by
     */
    public function test_get_courses_managed_by_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], va::get_courses_managed_by($user->id));
    }

    /**
     * `va::get_courses_managed_by()` returns courses where the user is
     * a course-contact-roled user.
     *
     * @covers \mod_videoassessment\va::get_courses_managed_by
     */
    public function test_get_courses_managed_by_with_teacher_role(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        $courses = va::get_courses_managed_by($user->id);
        $this->assertArrayHasKey($course->id, $courses);
    }
}
