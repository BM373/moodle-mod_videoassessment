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
 * Form for duplication of a teacher's rubric to another from a particular instance of videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

class mod_videoassessment_rubric_form_duplicate extends moodleform {

    public function definition() {

        $dform = $this->_form;
        $areas = $this->_customdata['areas'];

        $dform->addElement('hidden', 'id');
        $dform->setType('id', PARAM_INT);

        $dform->addElement('hidden', 'contextid');
        $dform->setType('contextid', PARAM_INT);

        $firstArea = true;

        foreach ($areas as $areaId => $areaName) {
            if ($firstArea) {
                $label = get_string('duplicatefor', 'videoassessment');
                $firstArea = false;
            } else {
                $label = '';
            }

            $dform->addElement('checkbox', "areas[$areaId]", $label, $areaName);
        }

        $this->add_action_buttons(null, get_string('duplicaterubric', 'videoassessment'));
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $areas = $this->_customdata['areas'];
        $areaIds = array_keys($areas);

        if (!$data['areas']) {
            $errors['areas[' . $areaIds[0] . ']'] = get_string('pleasechoosegradingareas', 'videoassessment');
        }
        else {
            $areaIds = implode(', ', array_keys($data['areas']));
            $areaDefinitions = $DB->get_records_sql('SELECT areaid,timecreated FROM {grading_definitions} WHERE areaid IN (:areaids)', ['areaids' => $areaIds]);
            $areasGrading = $DB->get_records('grading_areas', array('contextid' => $data['contextid']));
            if (is_array($areasGrading)) {
                foreach ($areasGrading as $area) {
                    if ($area->areaname == 'beforeteacher') {
                        $areaTeacherId = $area->id;
                    }
                }
            }
            $gradingDefinitionTeacher = $DB->get_record('grading_definitions', array('areaid' => $areaTeacherId));
            if (!empty($areaDefinitions) && isset($gradingDefinitionTeacher)) {
                foreach ($areaDefinitions as $area) {
                    if($gradingDefinitionTeacher->timecreated == $area->timecreated){
                        $errors['areas[' . $area->areaid . ']'] = get_string('gradingareadefined', 'videoassessment');
                    }
                }
            }
        }

        return $errors;
    }
}