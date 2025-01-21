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
 * Form for ??? videos for the videoassessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess\form;

defined('MOODLE_INTERNAL') || die();

class video_assoc extends \moodleform {
    private $_name = 'assocform';

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        if (!empty($this->_customdata->cmid)) {
            $mform->setDefault('id', $this->_customdata->cmid);
        }
        $mform->addElement('hidden', 'action', 'videoassoc');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'videoid', '', array('id' => 'id_videoid'));
        $mform->setType('videoid', PARAM_INT);
        $mform->addElement('hidden', 'assocdata', '', array('id' => 'id_assocdata'));
        $mform->setType('assocdata', PARAM_RAW);
        $mform->addElement('hidden', 'timing', 'before', array('id' => 'id_timing'));
        $mform->setType('timing', PARAM_ALPHA);

//         $this->add_action_buttons();
    }
}
