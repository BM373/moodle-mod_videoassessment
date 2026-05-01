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
 * Test generator for mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test generator for mod_videoassessment.
 *
 * Used by Behat (`Given the following "mod_videoassessment > activity"
 * exist:`) and by PHPUnit tests that need a real videoassessment
 * activity instance.
 *
 * @package mod_videoassessment
 */
class mod_videoassessment_generator extends testing_module_generator {

    /**
     * Default form data for create_instance(). Mirrors the activity
     * form's defaults so create_instance() can be called with just
     * `['course' => $courseid]`.
     *
     * @return array Defaults for every required field on the
     *               videoassessment activity form.
     */
    protected function get_default_record() {
        return [
            'name' => 'Test Video Assessment',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'beforelabel' => 'Before',
            'afterlabel' => 'After',
            'allowstudentupload' => 1,
            'allowyoutube' => 1,
            'allowvideoupload' => 1,
            'allowvideorecord' => 1,
            'maxbytes' => 0,
            'grade' => 100,
        ];
    }

    /**
     * Create a new videoassessment activity instance.
     *
     * @param array|stdClass|null $record Field overrides.
     * @param array|null $options Generator options forwarded to the parent.
     * @return stdClass Newly created activity record (with ->cmid).
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) ($record ?? []);
        $record = array_merge($this->get_default_record(), $record);
        return parent::create_instance($record, $options);
    }
}
