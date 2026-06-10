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
 * Integration tests for the random peer-assignment persistence layer.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Item #5 follow-up (2026-06 feedback round).
 *
 * The in-memory algorithm (see {@see peer_assignment_test}) guarantees a
 * max-min <= 1 chosen-count spread, but the customers still observed
 * unequal "(N)" counts on the peers page. Two persistence-layer causes:
 *
 * - Re-running the random assignment only deleted rows belonging to the
 *   users in the NEW mapping, so rows created by older runs (teachers
 *   that pre-dated the role filter, unenrolled users, members of since-
 *   deleted groups) survived forever and kept distorting the counts.
 * - In group mode the per-user delete executed once per group, so a
 *   student belonging to two groups had their first group's rows wiped
 *   when the second group was processed.
 *
 * The fix wipes the activity's whole peer table once per randomisation
 * run and inserts fresh rows (skipping duplicates across overlapping
 * groups). These tests drive the production persistence path via
 * reflection on va::randomize_peer_assignments().
 */
final class peer_persistence_test extends \advanced_testcase {
    /**
     * Create a course + activity and return the va instance plus ids.
     *
     * @param int $usedpeers Number of peers each student must assess.
     * @return array{va:va,activity:\stdClass,course:\stdClass}
     */
    private function make_activity(int $usedpeers): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(
            'videoassessment',
            ['course' => $course->id, 'usedpeers' => $usedpeers]
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
        return compact('va', 'activity', 'course');
    }

    /**
     * Enrol $count students into the course and return their ids.
     *
     * @param \stdClass $course Course record.
     * @param int $count Number of students.
     * @return int[] User ids.
     */
    private function enrol_students(\stdClass $course, int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
            $ids[] = (int) $user->id;
        }
        return $ids;
    }

    /**
     * Invoke the private randomize_peer_assignments() production method.
     *
     * @param va $va Activity wrapper.
     * @param string $peermode 'class' or 'group'.
     */
    private function randomize(va $va, string $peermode): void {
        $reflect = new \ReflectionMethod($va, 'randomize_peer_assignments');
        $reflect->setAccessible(true);
        $reflect->invoke($va, $peermode);
    }

    /**
     * Fetch all peer rows for an activity.
     *
     * @param int $instanceid videoassessment.id
     * @return \stdClass[]
     */
    private function rows(int $instanceid): array {
        global $DB;
        return array_values($DB->get_records(
            'videoassessment_peers',
            ['videoassessment' => $instanceid]
        ));
    }

    /**
     * A whole-class randomisation must wipe every stale row first:
     * rows pointing at a teacher (created before the role filter
     * existed) and rows pointing at long-gone users must not survive
     * a re-run.
     *
     * @covers \mod_videoassessment\va::randomize_peer_assignments
     */
    public function test_stale_rows_are_cleared_on_reassignment(): void {
        global $DB;
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity(2);
        $students = $this->enrol_students($course, 6);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Simulate leftovers from a pre-fix run: the teacher chosen as
        // a peer six times, plus a row for a user id that no longer
        // exists at all.
        foreach (range(1, 6) as $i) {
            $DB->insert_record('videoassessment_peers', (object) [
                'videoassessment' => $activity->id,
                'userid' => $students[$i % count($students)],
                'peerid' => $teacher->id,
            ]);
        }
        $DB->insert_record('videoassessment_peers', (object) [
            'videoassessment' => $activity->id,
            'userid' => 999999,
            'peerid' => $students[0],
        ]);

        $this->randomize($va, 'class');

        $rows = $this->rows((int) $activity->id);
        $userids = array_map(static fn($r) => (int) $r->userid, $rows);
        $peerids = array_map(static fn($r) => (int) $r->peerid, $rows);

        $this->assertNotContains((int) $teacher->id, $peerids, 'Stale teacher rows must be wiped.');
        $this->assertNotContains((int) $teacher->id, $userids, 'The teacher must not receive assignments.');
        $this->assertNotContains(999999, $userids, 'Rows for unknown users must be wiped.');
    }

    /**
     * Whole-class mode with a divisible configuration (6 students x 2
     * peers) must produce a perfectly equal distribution: every student
     * has exactly 2 rows as the assessed user AND appears exactly 2
     * times as a peer. This is the exact complaint from the customer
     * spreadsheet ("each student should be (2)").
     *
     * @covers \mod_videoassessment\va::randomize_peer_assignments
     */
    public function test_class_mode_distribution_is_exact(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity(2);
        $students = $this->enrol_students($course, 6);

        $this->randomize($va, 'class');

        $rows = $this->rows((int) $activity->id);
        $this->assertCount(12, $rows, '6 students x 2 peers must persist exactly 12 rows.');

        $asuser = array_fill_keys($students, 0);
        $aspeer = array_fill_keys($students, 0);
        foreach ($rows as $row) {
            $this->assertNotSame((int) $row->userid, (int) $row->peerid, 'No self-assignment.');
            $asuser[(int) $row->userid]++;
            $aspeer[(int) $row->peerid]++;
        }
        foreach ($students as $sid) {
            $this->assertSame(2, $asuser[$sid], "Student {$sid} must have exactly 2 peers.");
            $this->assertSame(2, $aspeer[$sid], "Student {$sid} must be chosen exactly 2 times.");
        }
    }

    /**
     * A user enrolled as BOTH student and teacher must be excluded from
     * the assignment entirely (multi-role holders are educators).
     *
     * @covers \mod_videoassessment\va::randomize_peer_assignments
     */
    public function test_dual_role_user_is_excluded(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity(2);
        $this->enrol_students($course, 5);

        $dual = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($dual->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($dual->id, $course->id, 'teacher');

        $this->randomize($va, 'class');

        foreach ($this->rows((int) $activity->id) as $row) {
            $this->assertNotSame((int) $dual->id, (int) $row->userid, 'Dual-role user must get no peers.');
            $this->assertNotSame((int) $dual->id, (int) $row->peerid, 'Dual-role user must not be a peer.');
        }
    }

    /**
     * Group mode: a student belonging to two groups must keep the
     * assignments from BOTH groups. Under the old per-user delete the
     * second group's processing wiped the rows created for the first
     * group.
     *
     * @covers \mod_videoassessment\va::randomize_peer_assignments
     */
    public function test_group_mode_multi_group_member_keeps_all_rows(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity(2);
        $students = $this->enrol_students($course, 7);

        // Group A: s0..s3, group B: s3..s6 — s3 sits in both.
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        foreach ([0, 1, 2, 3] as $i) {
            groups_add_member($groupa, $students[$i]);
        }
        foreach ([3, 4, 5, 6] as $i) {
            groups_add_member($groupb, $students[$i]);
        }

        $this->randomize($va, 'group');

        $shared = $students[3];
        $count = 0;
        foreach ($this->rows((int) $activity->id) as $row) {
            if ((int) $row->userid === $shared) {
                $count++;
            }
        }
        $this->assertSame(
            4,
            $count,
            'A student in two groups must keep 2 peers per group (4 rows), '
                . 'not lose the first group\'s rows to the second group\'s delete.'
        );
    }

    /**
     * Re-running the randomisation must fully replace the table: the
     * row count stays constant and no (userid, peerid) pair appears
     * twice.
     *
     * @covers \mod_videoassessment\va::randomize_peer_assignments
     */
    public function test_rerun_replaces_rows_without_duplicates(): void {
        $this->resetAfterTest();
        ['va' => $va, 'activity' => $activity, 'course' => $course] = $this->make_activity(2);
        $this->enrol_students($course, 6);

        $this->randomize($va, 'class');
        $this->randomize($va, 'class');

        $rows = $this->rows((int) $activity->id);
        $this->assertCount(12, $rows, 'Re-running must not accumulate rows.');

        $pairs = array_map(
            static fn($r) => $r->userid . ':' . $r->peerid,
            $rows
        );
        $this->assertSame(
            count($pairs),
            count(array_unique($pairs)),
            'No (userid, peerid) pair may be persisted twice.'
        );
    }
}
