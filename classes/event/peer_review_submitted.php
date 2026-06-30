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

namespace mod_videoassessment\event;

/**
 * Event triggered when a peer submits a rubric review for a fellow student.
 *
 * Item #10 of the 2026-04 fix programme. The review may be a self,
 * peer or class-style assessment captured before, during or after a
 * given timing in the rubric workflow.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array{videoassessmentid:int,timing?:string,gradertype?:string} $other
 *      Additional event data: `videoassessmentid`, `timing` and `gradertype`.
 */
class peer_review_submitted extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'videoassessment_grades';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Localised event name shown in the log report.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_peer_review_submitted', 'mod_videoassessment');
    }

    /**
     * Localised description used by the log report.
     *
     * @return string
     */
    public function get_description() {
        $vaid = (int)($this->other['videoassessmentid'] ?? 0);
        $target = (int)($this->relateduserid ?? 0);
        return "The user with id '{$this->userid}' submitted a peer review"
            . " for the user with id '{$target}' in the videoassessment activity"
            . " with id '{$vaid}'.";
    }
}
