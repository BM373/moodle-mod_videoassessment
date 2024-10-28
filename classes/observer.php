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
 * Event observers used in forum.
 *
 * @package    mod_videoassessment
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_forum.
 */
class mod_videoassessment_observer {

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'videoassessment') {
            // Include the videoassessment library to make use of the forum_instance_created function.
            require_once($CFG->dirroot . '/mod/videoassessment/lib.php');

            $va = $event->get_record_snapshot('videoassessment', $event->other['instanceid']);
            videoassessment_convert_video($event->get_context(), $va);
        }
    }
    
    public static function course_module_updated(\core\event\course_module_updated $event) {
        global $CFG;
    
        if ($event->other['modulename'] === 'videoassessment') {
            // Include the videoassessment library to make use of the forum_instance_created function.
            require_once($CFG->dirroot . '/mod/videoassessment/lib.php');

            $va = $event->get_record_snapshot('videoassessment', $event->other['instanceid']);
            videoassessment_convert_video($event->get_context(), $va);
        }
    }
}
