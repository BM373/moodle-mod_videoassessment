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

namespace mod_videoassessment\form;

use mod_videoassessment\va;

defined('MOODLE_INTERNAL') || die();

/**
 * Form for sorting videos for the videoassessment module.
 *
 * This form provides sorting options for video submissions including
 * ID-based, name-based, and manual sorting methods.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_class extends \moodleform {

    /**
     * Sort videos by ID.
     *
     * @var int
     */
    const SORT_ID = 1;

    /**
     * Sort videos by name.
     *
     * @var int
     */
    const SORT_NAME = 2;

    /**
     * Sort videos manually.
     *
     * @var int
     */
    const SORT_MANUALLY = 3;

    /**
     * Define the form structure and elements.
     *
     * Creates sorting options and group selection elements
     * for organizing video submissions.
     *
     * @return void
     */
    public function definition() {
        global $DB, $OUTPUT;

        $mform = $this->_form;
        /* @var $va \mod_videoassessment\va */
        $va = $this->_customdata->va;

        $attrs = $mform->getAttributes();
        $attrs['class'] .= ' sort-form';
        $mform->setAttributes($attrs);
        $mform->addElement('hidden', 'id', $va->cm->id);
        $mform->setType('id', PARAM_INT);

        $sortoptions = array(
            self::SORT_ID => get_string('sortid', 'videoassessment'),
            self::SORT_NAME => get_string('sortname', 'videoassessment'),
            self::SORT_MANUALLY => get_string('sortmanually', 'videoassessment'),
        );
        $mform->addElement('select', 'sortby', get_string('sortby', 'videoassessment'), $sortoptions, array('id' => 'sortby', 'data-load' => 0));
        $mform->setType('sortby', PARAM_INT);
        $mform->setDefault('sortby', $this->_customdata->sortby);

        $groupoptions = array(
            0 => get_string('allparticipants', 'videoassessment'),
        );
        foreach ($this->_customdata->groups as $k => $group) {
            $groupoptions[$k] = $group->name;
        }
        $mform->addElement('select', 'groupid', get_string('groupsseparate', 'group'), $groupoptions, array('id' => 'separate-group'));
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', $this->_customdata->groupid);

        $this->add_action_buttons(false, va::str('save'));
    }

    /**
     * Validate form data for sorting options.
     *
     * Performs basic validation on the sorting form data
     * and returns any validation errors.
     *
     * @param array $data Form data to validate
     * @param array $files Uploaded files array
     * @return array Array of validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
