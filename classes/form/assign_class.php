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
 * Form for sorting videos for the videoassessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess\form;

use \videoassess\va;

defined('MOODLE_INTERNAL') || die();

class assign_class extends \moodleform {

    CONST SORT_ID = 1;
    CONST SORT_NAME = 2;
    CONST SORT_MANUALLY = 3;

    public function definition() {
        global $DB, $OUTPUT;

        $mform = $this->_form;
        /* @var $va \videoassess\va */
        $va = $this->_customdata->va;

        $attrs = $mform->getAttributes();
        $attrs['class'] .= ' sort-form';
        $mform->setAttributes($attrs);
        $mform->addElement('hidden', 'id', $va->cm->id);
        $mform->setType('id', PARAM_INT);

        $sort_options = array(
            self::SORT_ID => get_string('sortid', 'videoassessment'),
            self::SORT_NAME => get_string('sortname', 'videoassessment'),
            self::SORT_MANUALLY => get_string('sortmanually', 'videoassessment')
        );
        $mform->addElement('select', 'sortby', get_string('sortby', 'videoassessment'), $sort_options, array('id' => 'sortby', 'data-load' => 0));
        $mform->setType('sortby', PARAM_INT);
        $mform->setDefault('sortby', $this->_customdata->sortby);

        $group_options = array(
            0 => get_string('allparticipants', 'videoassessment')
        );
        foreach ($this->_customdata->groups as $k => $group) {
            $group_options[$k] = $group->name;
        }
        $mform->addElement('select', 'groupid', get_string('groupsseparate', 'group'), $group_options, array('id' => 'separate-group'));
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', $this->_customdata->groupid);

        $this->add_action_buttons(false, va::str('save'));
    }

    /**
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}