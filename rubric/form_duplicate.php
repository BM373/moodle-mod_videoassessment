<?php
/**
 * @author Le Xuan Anh
 * Version2
 *
 * Duplicate Form
 *
 * Created at 2015/03/08
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
            $areaDefinitions = $DB->get_records_sql('SELECT areaid,timecreated FROM {grading_definitions} WHERE areaid IN (' . implode(', ', array_keys($data['areas'])) . ')');
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