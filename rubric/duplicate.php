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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
require_once($CFG->dirroot . '/mod/videoassessment/rubric/form_duplicate.php');

global $CFG, $DB, $OUTPUT, $PAGE;

$cmid = optional_param('id', null, PARAM_INT);
$cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);

$course = $DB->get_record('course', ['id' => $cm->course]);
$context = \context_module::instance($cm->id);
require_capability('mod/videoassessment:grade', $context);
$areas = []; // Array of area IDs for class, peer, and self in the grading_areas table.
$definitionids = []; // Array of defination IDs in grading_definitions table
$criteriaids = []; // Array of criteria IDs in gradingform_rubric_criteria table

// Get data from videoassessment table
$currentvideoassessment = $DB->get_record('videoassessment', ['id' => $cm->instance], 'training');
// Get data from grading_areas table
$areasgrading = $DB->get_records('grading_areas', ['contextid' => $context->id]);

if (is_array($areasgrading)) {
    foreach ($areasgrading as $area) {
        if ($area->areaname == 'beforeteacher') {
            $areateacherid = $area->id;
        } else {
            if ($currentvideoassessment->training == 0) {
                if ($area->areaname != 'beforetraining') {
                    $areas[$area->id] = get_string($area->areaname, 'videoassessment');
                }
            } else {
                $areas[$area->id] = get_string($area->areaname, 'videoassessment');
            }
        }
    }
}

$gradingdefinitionteacher = $DB->get_record('grading_definitions', ['areaid' => $areateacherid]);
// Check if a teacher rubric exists for this context.
if (!$gradingdefinitionteacher) {
    redirect(new \moodle_url('/grade/grading/manage.php', ['areaid' => $areateacherid]), get_string('pleasedefinerubricforteacher', 'videoassessment'));
}
// Get information of Rubric
$manager = get_grading_manager($areateacherid);
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
$dform = new \mod_videoassessment_rubric_form_duplicate('', ['areas' => $areas]);
$dform->set_data($data);

// Post form
if ($data = $dform->get_data()) {
    // Check rubric in grading_definitions
    $areas = $data->areas;
    $inareaids = implode(',', array_keys($areas));
    $areadefinitions = $DB->get_records_sql(
        'SELECT areaid FROM {grading_definitions} WHERE areaid IN (:areateacherid, :inareaids)',
        [
            'areateacherid' => $areateacherid,
            'inareaids' => $inareaids,
        ]
    );

    // Insert into grading_definitions table
    // Get id definitions of new record after insert
    // $gradingdefinitionother : Object use insert data to grading_definitions table (data: peer, self, class)
    $transaction = $DB->start_delegated_transaction();

    try {
        $gradingdefinitionother = clone $gradingdefinitionteacher;

        foreach ($areas as $areaid => $val) {
            $areadefinition = $DB->get_record('grading_definitions', ['areaid' => $areaid]);
            if (isset($areadefinition)) {
                $DB->delete_records('grading_definitions', ['areaid' => $areaid]);
            }
            $gradingdefinitionother->areaid = $areaid;
            $definitionids[] = $DB->insert_record('grading_definitions', $gradingdefinitionother);
        }

        if (is_null($definitionids)) {
            redirect(new \moodle_url('/mod/videoassessment/view.php', ['id' => $PAGE->cm->id]), get_string('duplicateerrors', 'videoassessment'));
        }
        // Insert into gradingform_rubric_criteria table
        // Get ids criteria of new record after insert
        $gradingformcriteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $gradingdefinitionteacher->id]);

        if (is_array($gradingformcriteria)) {
            foreach ($definitionids as $definitionid) {
                foreach ($gradingformcriteria as $gradingformcriteriaitem) {
                    $gradingformcriteriaother = clone $gradingformcriteriaitem;
                    $gradingformcriteriaother->definitionid = $definitionid;

                    $criteriaid = $DB->insert_record('gradingform_rubric_criteria', $gradingformcriteriaother);

                    $gradingformrubriclevels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $gradingformcriteriaitem->id]);

                    foreach ($gradingformrubriclevels as $gradingformrubriclevel) {
                        $gradingrubriclevelteacherother = clone $gradingformrubriclevel;
                        $gradingrubriclevelteacherother->criterionid = $criteriaid;
                        $result = $DB->insert_record('gradingform_rubric_levels', $gradingrubriclevelteacherother);
                    }
                }
            }
        }

        $transaction->allow_commit();
        redirect(new \moodle_url('/mod/videoassessment/view.php', ['id' => $PAGE->cm->id]), get_string('duplicatesuccess', 'videoassessment'));
    } catch (Exception $e) {
        $transaction->rollback($e);
    }
} else { // Default page
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('duplicaterubric', 'videoassessment'));
    echo '<h3>' . $gradingdefinitionteacher->name . '<span class="status ready"> ' . get_string('readyforuse', 'videoassessment') . '</span></h3>';

    if (!empty($method)) {
        $output = $PAGE->get_renderer('core_grading');
        $controller = $manager->get_controller($method);
        echo $output->box($controller->render_preview($PAGE), 'definition-preview');
    }

    $dform->display();
    echo $OUTPUT->footer();
}
