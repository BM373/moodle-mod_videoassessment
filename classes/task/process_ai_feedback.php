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
 * Finishes an AI feedback attempt the learner's browser did not.
 *
 * The browser normally starts processing right after the upload. This
 * task is the safety net for a closed tab or a request that timed out;
 * when the browser already finished, it finds nothing left to do.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_ai_feedback extends \core\task\adhoc_task {
    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->attemptid)) {
            return;
        }
        $attempt = attempt_manager::process_attempt((int)$data->attemptid);
        if ($attempt && (int)$attempt->status === attempt_manager::STATUS_FAILED) {
            mtrace('AI feedback attempt ' . $attempt->id . ' failed: ' . attempt_manager::error_text($attempt, true));
        }
    }
}
