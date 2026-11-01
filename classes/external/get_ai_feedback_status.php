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
 * Current status of an AI feedback attempt, for the page to poll.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ai_feedback_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The attempt'),
        ]);
    }

    /**
     * Read the status.
     *
     * @param int $attemptid The attempt.
     * @return array
     */
    public static function execute(int $attemptid): array {
        ['attemptid' => $attemptid] = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        $attempt = self::require_access($attemptid);
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

    /**
     * Load an attempt the current user may act on.
     *
     * Learners reach their own attempts; anyone else needs the grading
     * capability in the activity.
     *
     * @param int $attemptid The attempt.
     * @return \stdClass The attempt record.
     */
    public static function require_access(int $attemptid): \stdClass {
        global $DB, $USER;
        $attempt = $DB->get_record(attempt_manager::TABLE, ['id' => $attemptid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('videoassessment', $attempt->videoassessment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        if ((int)$attempt->userid === (int)$USER->id) {
            require_capability('mod/videoassessment:submit', $context);
        } else {
            require_capability('mod/videoassessment:grade', $context);
        }
        return $attempt;
    }
}
