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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoassessment/backup/moodle2/restore_videoassessment_stepslib.php');

class restore_videoassessment_activity_task extends restore_activity_task {
    protected function define_my_steps() {
        $this->add_step(new restore_videoassessment_activity_structure_step('videoassessment_structure', 'videoassessment.xml'));
    }

    protected function define_my_settings() {

    }

    public static function define_decode_contents() {
        $contents = array();

        return $contents;
    }

    public static function define_decode_rules() {
        $rules = array();

        return $rules;
    }

}
