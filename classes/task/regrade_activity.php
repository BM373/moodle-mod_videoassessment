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

namespace mod_videoassessment\task;

use mod_videoassessment\va;

/**
 * Recalculate one activity's totals and send them to the gradebook.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regrade_activity extends \core\task\adhoc_task {
    /**
     * Queue the activities that use Delayed Teacher Grade.
     *
     * An activity with no weights at all cannot have a total, and a recalculation would fail on it.
     *
     * @return void
     */
    public static function queue_delayed_activities(): void {
        global $DB;

        $ids = $DB->get_fieldset_select(
            'videoassessment',
            'id',
            'delayedteachergrade = 1 AND ratingteacher + ratingself + ratingpeer + ratingclass > 0'
        );
        foreach ($ids as $id) {
            $task = new self();
            $task->set_custom_data(['videoassessment' => (int) $id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $id = $this->get_custom_data()->videoassessment ?? 0;
        $cm = get_coursemodule_from_instance('videoassessment', $id);
        if (!$cm) {
            return;
        }
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        (new va(\context_module::instance($cm->id), $cm, $course))->regrade();
    }
}
