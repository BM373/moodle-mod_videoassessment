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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_videoassessment_rubric_form_duplicate extends moodleform {
    /**
     * Define the form elements for duplication of a teacher's rubric.
     *
     * Sets up the form fields for duplicating a teacher's rubric
     * including hidden fields for instance ID and context ID.
     *
     * @return void
     */
    public function definition() {

        $dform = $this->_form;
        $areas = $this->_customdata['areas'];

        $dform->addElement('hidden', 'id');
        $dform->setType('id', PARAM_INT);

        $dform->addElement('hidden', 'contextid');
        $dform->setType('contextid', PARAM_INT);

        $firstarea = true;

        foreach ($areas as $areaid => $areaname) {
            if ($firstarea) {
                $label = get_string('duplicatefor', 'videoassessment');
                $firstarea = false;
            } else {
                $label = '';
            }

            $dform->addElement('checkbox', "areas[$areaid]", $label, $areaname);
        }

        $this->add_action_buttons(null, get_string('duplicaterubric', 'videoassessment'));
    }

    /**
     * Validate the form data for duplication of a teacher's rubric.
     *
     * Checks if the required grading areas are selected and validates
     * the form data for proper duplication of the rubric.
     *
     * @param array $data Form data to validate
     * @param array $files Form files to validate
     * @return array Validation errors
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $areas = $this->_customdata['areas'];
        $areaids = array_keys($areas);

        if (!$data['areas']) {
            $errors['areas[' . $areaids[0] . ']'] = get_string('pleasechoosegradingareas', 'videoassessment');
        } else {
            $areaids = implode(', ', array_keys($data['areas']));
            $areadefinitions = $DB->get_records_sql(
                'SELECT areaid,timecreated FROM {grading_definitions} WHERE areaid IN (:areaids)',
                ['areaids' => $areaids]
            );
            $areasgrading = $DB->get_records('grading_areas', ['contextid' => $data['contextid']]);
            if (is_array($areasgrading)) {
                foreach ($areasgrading as $area) {
                    if ($area->areaname == 'beforeteacher') {
                        $areateacherid = $area->id;
                    }
                }
            }
            $gradingdefinitionteacher = $DB->get_record('grading_definitions', ['areaid' => $areateacherid]);
            if (!empty($areadefinitions) && isset($gradingdefinitionteacher)) {
                foreach ($areadefinitions as $area) {
                    if ($gradingdefinitionteacher->timecreated == $area->timecreated) {
                        $errors['areas[' . $area->areaid . ']'] = get_string('gradingareadefined', 'videoassessment');
                    }
                }
            }
        }

        return $errors;
    }
}
