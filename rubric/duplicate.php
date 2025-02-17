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
 * Allows duplication of a teacher's rubric to another from a particular instance of videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

global $CFG, $DB, $OUTPUT, $PAGE;

require_once('../../../config.php');
require_once($CFG->dirroot.'/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
require_once($CFG->dirroot . '/mod/videoassessment/rubric/form_duplicate.php');

$cmid = optional_param('id', null, PARAM_INT);
$cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);

$course = $DB->get_record('course', ['id' => $cm->course]);
$context = \context_module::instance($cm->id);
$areas = []; // Array Id of Class, Peer, Self in Grading_Areas's Table
$definitionIds = []; // Array Id Definition in grading_definitions's table
$criteriaIds = []; // Array Id Criteria in gradingform_rubric_criteria's table

// Get data from videoassessment table
$currentVideoAssessment = $DB->get_record('videoassessment', ['id' => $cm->instance], 'training');
// Get data from grading_areas table
$areasGrading = $DB->get_records('grading_areas', ['contextid' => $context->id]);

if (is_array($areasGrading)) {
    foreach ($areasGrading as $area) {
        if ($area->areaname == 'beforeteacher') {
            $areaTeacherId = $area->id;
        } else {
            if ($currentVideoAssessment->training == 0) {
                if ($area->areaname != 'beforetraining') {
                    $areas[$area->id] = get_string($area->areaname, 'videoassessment');
                }
            } else {
                $areas[$area->id] = get_string($area->areaname, 'videoassessment');
            }
        }
    }
}

$gradingDefinitionTeacher = $DB->get_record('grading_definitions', ['areaid' => $areaTeacherId]);
// Check exist teacher's rubric
if (!$gradingDefinitionTeacher) {
    redirect(new \moodle_url('/grade/grading/manage.php', ['areaid' => $areaTeacherId]), get_string('pleasedefinerubricforteacher', 'videoassessment'));
}
// Get information of Rubric
$manager = get_grading_manager($areaTeacherId);
// Get the currently active method
$method = $manager->get_active_method();

$PAGE->requires->css(new \moodle_url('/mod/videoassessment/duplicate.css'));

$url = new moodle_url('/mod/videoassessment/rubric/duplicate.php', ['id' => $cmid]);
$PAGE->set_url($url);
$PAGE->add_body_class('duplicate-page');

// Set default data for duplicate form
$data = new stdClass;
$data->id = $cmid;
$data->contextid = $context->id;

// Create form duplicate
$dForm = new \mod_videoassessment_rubric_form_duplicate('', ['areas' => $areas]);
$dForm->set_data($data);

// Post form
if ($data = $dForm->get_data()) {
    /**
     * Check rubric in grading_definitions
     */
    $areas = $data->areas;
    $inAreaIds = implode(',', array_keys($areas));
    $areaDefinitions = $DB->get_records_sql(
        'SELECT areaid FROM {grading_definitions} WHERE areaid IN (:areateacherid, :inareaids)',
        [
            'areateacherid' => $areaTeacherId,
            'inareaids' => $inAreaIds,
        ]
    );

    /**
     * Insert to grading_definitions table
     * Get id definitions of new record after insert
     */
    // $gradingDefinitionOther : Object use insert data to grading_definitions table (data: peer, self, class)
    $transaction = $DB->start_delegated_transaction();

    try {
        $gradingDefinitionOther = clone $gradingDefinitionTeacher;

        foreach ($areas as $areaId => $val) {
            $areaDefinition = $DB->get_record('grading_definitions', ['areaid'=>$areaId]);
            if(isset($areaDefinition)){
                $DB->delete_records('grading_definitions', ['areaid'=>$areaId]);
            }
            $gradingDefinitionOther->areaid = $areaId;
            $definitionIds[] = $DB->insert_record('grading_definitions', $gradingDefinitionOther);
        }

        if (is_null($definitionIds)) {
            redirect(new \moodle_url('/mod/videoassessment/view.php', ['id' => $PAGE->cm->id]), get_string('duplicateerrors', 'videoassessment'));
        }
        /**
         * Insert to gradingForm_rubric_criteria table
         * Get ids criteria of new record after insert
         */
        $gradingsFormCriteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $gradingDefinitionTeacher->id]);

        if (is_array($gradingsFormCriteria)) {
            foreach ($definitionIds as $definitionId) {
                foreach ($gradingsFormCriteria as $gradingFormCriteria) {
                    $gradingFormCriteriaOther = clone $gradingFormCriteria;
                    $gradingFormCriteriaOther->definitionid = $definitionId;

                    $criteriaId = $DB->insert_record('gradingform_rubric_criteria', $gradingFormCriteriaOther);

                    $gradingRubricLevels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $gradingFormCriteria->id]);

                    foreach ($gradingRubricLevels as $gradingRubricLevel) {
                        $gradingRubricLevelTeacherOther = clone $gradingRubricLevel;
                        $gradingRubricLevelTeacherOther->criterionid = $criteriaId;
                        $result = $DB->insert_record('gradingform_rubric_levels', $gradingRubricLevelTeacherOther);
                    }
                }
            }
        }

        $transaction->allow_commit();
        redirect(new \moodle_url('/mod/videoassessment/view.php', ['id' => $PAGE->cm->id]), get_string('duplicatesuccess', 'videoassessment'));
    } catch (Exception $e) {
        $transaction->rollback($e);
    }
} else { //Default page
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('duplicaterubric', 'videoassessment'));
    echo '<h3>' . $gradingDefinitionTeacher->name . '<span class="status ready"> Ready for use</span></h3>';

    if (!empty($method)) {
        $output = $PAGE->get_renderer('core_grading');
        $controller = $manager->get_controller($method);
        echo $output->box($controller->render_preview($PAGE), 'definition-preview');
    }

    $dForm->display();
    echo $OUTPUT->footer();
}
