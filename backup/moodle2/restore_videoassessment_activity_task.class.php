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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoassessment/backup/moodle2/restore_videoassessment_stepslib.php');

/**
 * Restore task for the Video Assessment activity.
 *
 * Defines restore steps and settings for the `mod_videoassessment` module.
 *
 * @package   mod_videoassessment
 * @copyright 2024 Don Hinkleman (hinkelman@mac.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videoassessment_activity_task extends restore_activity_task {
    /**
     * Define module-specific restore steps.
     *
     * Adds the structure step that reads `videoassessment.xml`.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_videoassessment_activity_structure_step('videoassessment_structure', 'videoassessment.xml'));
    }

    /**
     * Define module-specific restore settings.
     *
     * Currently empty as no special settings are needed.
     *
     * @return void
     */
    protected function define_my_settings() {

    }

    /**
     * Define content decoding rules for restore.
     *
     * Returns empty array as there are no special content links to decode.
     *
     * @return array List of content decoding rules
     */
    public static function define_decode_contents() {
        $contents = array();

        return $contents;
    }

    /**
     * Define URL decoding rules for restore.
     *
     * Returns empty array as there are no special URL patterns to decode.
     *
     * @return array List of URL decoding rules
     */
    public static function define_decode_rules() {
        $rules = array();

        return $rules;
    }

}
