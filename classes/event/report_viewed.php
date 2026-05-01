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
 * Event triggered when a learner or teacher opens a Video Assessment report.
 *
 * Item #10 of the 2026-04 fix programme. Distinct from the existing
 * ``course_module_viewed`` event because that one fires on the activity
 * landing page; this one fires only when the rubric / report screen is
 * actually rendered, so analytics can tell viewing the activity apart
 * from reading the feedback report.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array{videoassessmentid:int} $other Additional event data.
 */
class report_viewed extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'videoassessment';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Localised event name shown in the log report.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_report_viewed', 'mod_videoassessment');
    }

    /**
     * Localised description used by the log report.
     *
     * @return string
     */
    public function get_description() {
        $vaid = (int)($this->other['videoassessmentid'] ?? 0);
        $target = (int)($this->relateduserid ?? $this->userid);
        return "The user with id '{$this->userid}' viewed the Video Assessment report"
            . " for the user with id '{$target}' in the videoassessment activity"
            . " with id '{$vaid}'.";
    }
}
