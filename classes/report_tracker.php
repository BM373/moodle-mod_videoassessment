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

namespace mod_videoassessment;

/**
 * Tracks whether a learner has read the latest version of their report.
 *
 * 2026-07 request #1: the scores table labels each learner's report as
 * "New Report Available" until the learner opens it, then as "Report
 * Viewed", so teachers can confirm learners actually read the feedback.
 * A report counts as updated when an assessment the learner can see is
 * saved after their last visit. The learner's own self-assessment does
 * not count, and a teacher assessment held back by "Delayed Teacher
 * Grade" only counts from the moment the learner self-assesses, which
 * is when it becomes visible to them.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_tracker {
    /** @var string DB table holding the last report visit per learner. */
    const TABLE = 'videoassessment_report_views';

    /** @var int No assessment to read yet and the report was never opened. */
    const STATUS_NONE = 0;
    /** @var int The report has new or updated feedback the learner has not opened. */
    const STATUS_NEW = 1;
    /** @var int The learner has opened the report since it last changed. */
    const STATUS_VIEWED = 2;

    /**
     * Record that the learner opened their own report.
     *
     * @param int $vaid videoassessment.id
     * @param int $userid The learner.
     * @param int|null $time Visit time, defaults to now.
     * @return void
     */
    public static function mark_viewed(int $vaid, int $userid, ?int $time = null): void {
        global $DB;

        $time = $time ?? time();
        $id = $DB->get_field(self::TABLE, 'id', ['videoassessment' => $vaid, 'userid' => $userid]);
        if ($id) {
            $DB->set_field(self::TABLE, 'timeviewed', $time, ['id' => $id]);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'videoassessment' => $vaid,
                'userid' => $userid,
                'timeviewed' => $time,
            ]);
        }
    }

    /**
     * Forget the learner's report visits (used when their submission is reset).
     *
     * @param int $vaid videoassessment.id
     * @param int $userid The learner.
     * @return void
     */
    public static function reset(int $vaid, int $userid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['videoassessment' => $vaid, 'userid' => $userid]);
    }

    /**
     * When the learner last opened their report.
     *
     * @param int $vaid videoassessment.id
     * @param int $userid The learner.
     * @return int Unix time, or 0 when never opened.
     */
    public static function get_last_viewed(int $vaid, int $userid): int {
        global $DB;

        return (int) $DB->get_field(self::TABLE, 'timeviewed', ['videoassessment' => $vaid, 'userid' => $userid]);
    }

    /**
     * When feedback the learner can see last changed.
     *
     * @param va $va The activity.
     * @param int $userid The learner.
     * @return int Unix time, or 0 when there is nothing to read yet.
     */
    public static function get_last_updated(va $va, int $userid): int {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT g.id, gi.type, gi.grader, g.timemarked
               FROM {videoassessment_grades} g
               JOIN {videoassessment_grade_items} gi ON gi.id = g.gradeitem
              WHERE g.videoassessment = :va
                AND gi.videoassessment = :va2
                AND gi.gradeduser = :userid",
            ['va' => $va->instance, 'va2' => $va->instance, 'userid' => $userid]
        );

        $agg = $va->get_aggregated_grades($userid);
        $latest = 0;
        foreach ($va->timings as $timing) {
            $selftime = 0;
            $teachertime = 0;
            foreach ($rows as $row) {
                if ($row->type === $timing . 'self') {
                    $selftime = max($selftime, (int) $row->timemarked);
                } else if ((int) $row->grader === $userid) {
                    // Anything else the learner saved about themselves.
                    continue;
                } else if ($row->type === $timing . 'peer') {
                    $latest = max($latest, (int) $row->timemarked);
                } else if ($row->type === $timing . 'teacher') {
                    $teachertime = max($teachertime, (int) $row->timemarked);
                }
                // Class and training assessments are not shown in the learner's report.
            }
            if ($teachertime && !$va->is_teacher_grade_hidden_from_learner($agg, $timing)) {
                if (!empty($va->va->delayedteachergrade) && $va->is_self_assessment_enabled()) {
                    // The teacher's feedback became visible when the learner self-assessed.
                    $teachertime = max($teachertime, $selftime);
                }
                $latest = max($latest, $teachertime);
            }
        }
        return $latest;
    }

    /**
     * Report label state for a learner.
     *
     * @param va $va The activity.
     * @param int $userid The learner.
     * @return int One of the STATUS_* constants.
     */
    public static function get_status(va $va, int $userid): int {
        $updated = self::get_last_updated($va, $userid);
        $viewed = self::get_last_viewed((int) $va->instance, $userid);

        if ($updated > 0 && $viewed < $updated) {
            return self::STATUS_NEW;
        }
        if ($viewed > 0) {
            return self::STATUS_VIEWED;
        }
        return self::STATUS_NONE;
    }
}
