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

namespace mod_videoassessment\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_videoassessment\aifeedback\attempt_manager;

/**
 * Transcribe and analyse an attempt now, while the learner waits.
 *
 * Called by the AI feedback page right after the upload. It returns when
 * the feedback is ready (usually well under a minute), so the learner does
 * not wait for cron.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_ai_feedback extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The attempt to process'),
        ]);
    }

    /**
     * Process the attempt.
     *
     * @param int $attemptid The attempt.
     * @return array The attempt's status afterwards.
     */
    public static function execute(int $attemptid): array {
        ['attemptid' => $attemptid] = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        $attempt = get_ai_feedback_status::require_access($attemptid);

        \core_php_time_limit::raise(300);
        ignore_user_abort(true);
        $attempt = attempt_manager::process_attempt((int)$attempt->id);
        return ['status' => (int)$attempt->status];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, '0 queued, 1 processing, 2 done, 3 failed'),
        ]);
    }
}
