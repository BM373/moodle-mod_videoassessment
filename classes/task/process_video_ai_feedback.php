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

use mod_videoassessment\aifeedback\attempt_manager;

/**
 * Sends the sound of a learner's submitted video for AI feedback.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_video_ai_feedback extends \core\task\adhoc_task {
    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $va = $DB->get_record('videoassessment', ['id' => $data->videoassessment ?? 0]);
        if (!$va) {
            return;
        }
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
        $manager = new attempt_manager($va, \context_module::instance($cm->id));
        $attempt = $manager->process_video((int)$data->videoid, (int)$data->userid);
        if ($attempt && (int)$attempt->status === attempt_manager::STATUS_FAILED) {
            mtrace('AI feedback on video ' . $data->videoid . ' failed: ' . attempt_manager::error_text($attempt, true));
        }
    }
}
