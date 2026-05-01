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
 * This file contains the forms to create and edit an instance of this module.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_videoassessment\va;

use core_grades\component_gradeitems;

/**
 * Settings form for the videoassessment module.
 *
 * Provides comprehensive configuration interface for video assessment activities
 * including grading, notifications, training materials, and assessment types.
 *
 * @see moodleform_mod
 */
class mod_videoassessment_mod_form extends moodleform_mod {
    /** @var int Default number of peers for assessment */
    const DEFAULT_USED_PEERS = 1;

    /**
     * Define the form elements for video assessment configuration.
     *
     * Creates comprehensive form interface including general settings,
     * availability dates, grading options, and notification preferences.
     *
     * @return void
     */
    public function definition() {
        global $CFG, $DB, $PAGE;
        $cm = $PAGE->cm;

        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('videoassessmentname', 'videoassessment'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(false, get_string('description', 'videoassessment'));

        // 1. AVAILABILITY.
        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', false);

        $name = get_string('allowsubmissionsfromdate', 'assign');
        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'assign');

        $name = get_string('duedate', 'assign');
        $mform->addElement('date_time_selector', 'duedate', $name, ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'assign');

        $name = get_string('cutoffdate', 'assign');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, ['optional' => true]);
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'assign');

        $name = get_string('gradingduedate', 'assign');
        $mform->addElement('date_time_selector', 'gradingduedate', $name, ['optional' => true]);
        $mform->addHelpButton('gradingduedate', 'gradingduedate', 'assign');

        // 2. ASSESSORS AND WEIGHTINGS (Default open).
        $mform->addElement('header', 'ratings', get_string('assessorsandweightings', 'videoassessment'));
        $mform->addHelpButton('ratings', 'ratings', 'videoassessment');
        $mform->setExpanded('ratings', true);

        $mform->addElement('static', 'ratingerror');
        for ($i = 100; $i >= 0; $i--) {
            $ratingopts[$i] = $i . '%';
        }
        $mform->addElement('select', 'ratingteacher', get_string('teacher', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingteacher', 80);
        $mform->addHelpButton('ratingteacher', 'ratingteacher', 'videoassessment');
        $mform->addElement('select', 'ratingself', get_string('self', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingself', 10);
        $mform->addHelpButton('ratingself', 'ratingself', 'videoassessment');
        $mform->addElement('select', 'ratingpeer', get_string('peer', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingpeer', 10);
        $mform->addHelpButton('ratingpeer', 'ratingpeer', 'videoassessment');
        $mform->addElement('select', 'ratingclass', get_string('class', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingclass', 0);
        $mform->addHelpButton('ratingclass', 'ratingclass', 'videoassessment');

        $mform->addElement('selectyesno', 'delayedteachergrade', get_string('delayedteachergrade', 'videoassessment'));
        $mform->setDefault('delayedteachergrade', 1);
        $mform->addHelpButton('delayedteachergrade', 'delayedteachergrade', 'videoassessment');

        $mform->addElement('text', 'usedpeers', get_string('numberofpeerassessors', 'videoassessment'), ['size' => 5]);
        $mform->setType('usedpeers', PARAM_INT);
        $mform->setDefault('usedpeers', 2);
        $mform->addHelpButton('usedpeers', 'usedpeers', 'videoassessment');
        $mform->addRule('usedpeers', get_string('err_numeric', 'form'), 'numeric', null, 'client');

        // Assign Peer Assessors - within the same section, wrapped in a div for show/hide.
        $mform->addElement('html', '<div id="assign-peer-assessors-container">');
        $mform->addElement(
            'static',
            'assignpeerassessorslabel',
            '',
            '<h5 class="mt-3">' . get_string('assignpeerassessors', 'videoassessment') . '</h5>'
        );

        // Show peer assignment interface (works for both new and existing activities).
        $peershtml = $this->render_peers_assignment_interface($cm);
        $mform->addElement('html', $peershtml);

        // Hidden field to store peer assignments as JSON (for new activities).
        $mform->addElement('hidden', 'peerassignments', '{}');
        $mform->setType('peerassignments', PARAM_RAW);

        // Error placeholder for peer assignment validation.
        $mform->addElement(
            'static',
            'peerassignmenterror',
            '',
            '<div id="peerassignment-error" class="text-danger" style="display:none;"></div>'
        );

        $mform->addElement('html', '</div>');

        // Initialize JavaScript for peer assessors visibility.
        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initPeerAssessorsVisibility');

        // 3. GRADING (with Whole Class Grading dropdown).
        $this->standard_grading_coursemodule_elements_to_grading('grading');

        // Whole Class Grading - dropdown (Open/Close), default = Close.
        $classoptions = [
            0 => get_string('close', 'videoassessment'),
            1 => get_string('open', 'videoassessment'),
        ];
        $mform->addElement(
            'select',
            'class',
            get_string('classgrading', 'videoassessment'),
            $classoptions
        );
        $mform->addHelpButton('class', 'classgrading', 'videoassessment');
        $mform->setType('class', PARAM_INT);
        $mform->setDefault('class', 0);

        // 4. VIDEO SUBMISSIONS.
        $mform->addElement('header', 'videosubmissions', get_string('videosubmissions', 'videoassessment'));
        $mform->setExpanded('videosubmissions', false);

        $mform->addElement('selectyesno', 'allowstudentupload', get_string('allowstudentupload', 'videoassessment'));
        $mform->setDefault('allowstudentupload', 1);
        $mform->addHelpButton('allowstudentupload', 'allowstudentupload', 'videoassessment');

        // Item #2 of the 2026-04 fix programme: read the three site-level
        // allow-* flags individually so the activity admin can only opt
        // out of channels that the site allows in the first place. The
        // legacy `preventvideouploads` setting still acts as a hard kill
        // switch for backward compatibility (sites that haven't run the
        // upgrade migration yet).
        $allowexternallinks = get_config('videoassessment', 'allowexternallinks');
        $allowvideouploads = get_config('videoassessment', 'allowvideouploads');
        $allowvideorecording = get_config('videoassessment', 'allowvideorecording');
        // Treat `false` (setting not yet defined on this site) as the new
        // default of 1, so a Moodle that has not run the migration yet
        // still behaves as documented.
        $allowexternallinks = ($allowexternallinks === false) ? 1 : (int)$allowexternallinks;
        $allowvideouploads = ($allowvideouploads === false) ? 1 : (int)$allowvideouploads;
        $allowvideorecording = ($allowvideorecording === false) ? 1 : (int)$allowvideorecording;
        $preventvideouploads = !$allowvideouploads || !$allowvideorecording;

        $mform->addElement('advcheckbox', 'allowyoutube', get_string('allowyoutube', 'videoassessment'));
        $mform->addHelpButton('allowyoutube', 'allowyoutube', 'videoassessment');
        if (!$allowexternallinks) {
            $mform->hardFreeze('allowyoutube');
            $mform->setDefault('allowyoutube', 0);
        } else {
            $mform->setDefault('allowyoutube', 1);
        }

        // Video upload option - greyed out when uploads are disabled site-wide.
        $mform->addElement('advcheckbox', 'allowvideoupload', get_string('allowvideoupload', 'videoassessment'));
        $mform->addHelpButton('allowvideoupload', 'allowvideoupload', 'videoassessment');
        if (!$allowvideouploads) {
            $mform->hardFreeze('allowvideoupload');
            $mform->setDefault('allowvideoupload', 0);
        } else {
            $mform->setDefault('allowvideoupload', 1);
        }

        // Video record option - greyed out when in-browser recording is disabled site-wide.
        $mform->addElement('advcheckbox', 'allowvideorecord', get_string('allowvideorecord', 'videoassessment'));
        $mform->addHelpButton('allowvideorecord', 'allowvideorecord', 'videoassessment');
        if (!$allowvideorecording) {
            $mform->hardFreeze('allowvideorecord');
            $mform->setDefault('allowvideorecord', 0);
        } else {
            $mform->setDefault('allowvideorecord', 1);
        }

        // Add CSS and JS to grey out labels when uploads are prevented globally.
        if ($preventvideouploads) {
            $mform->addElement('html', '
                <style>
                    /* Target all possible label elements for these fields */
                    #fitem_id_allowvideoupload,
                    #fitem_id_allowvideorecord,
                    [data-groupname="allowvideoupload"],
                    [data-groupname="allowvideorecord"] {
                        opacity: 0.5 !important;
                    }
                    #fitem_id_allowvideoupload label,
                    #fitem_id_allowvideoupload .col-form-label,
                    #fitem_id_allowvideoupload .form-check-label,
                    #fitem_id_allowvideoupload .fitemtitle,
                    #fitem_id_allowvideorecord label,
                    #fitem_id_allowvideorecord .col-form-label,
                    #fitem_id_allowvideorecord .form-check-label,
                    #fitem_id_allowvideorecord .fitemtitle {
                        color: #888 !important;
                    }
                </style>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        // Find and grey out the form items.
                        var uploadItem = document.getElementById("fitem_id_allowvideoupload");
                        var recordItem = document.getElementById("fitem_id_allowvideorecord");
                        if (uploadItem) uploadItem.style.opacity = "0.5";
                        if (recordItem) recordItem.style.opacity = "0.5";

                        // Also try by input name.
                        var uploadInput = document.querySelector("input[name=\'allowvideoupload\']");
                        var recordInput = document.querySelector("input[name=\'allowvideorecord\']");
                        if (uploadInput) {
                            var parent = uploadInput.closest(".fitem, .form-group, .row");
                            if (parent) parent.style.opacity = "0.5";
                        }
                        if (recordInput) {
                            var parent = recordInput.closest(".fitem, .form-group, .row");
                            if (parent) parent.style.opacity = "0.5";
                        }
                    });
                </script>
            ');
        }

        // 5. NOTIFICATIONS.
        $this->add_notifications();

        // 6. ADVANCED OPTIONS.
        $this->add_advanced_options($cm);

        // Standard Moodle sections.
        $this->standard_coursemodule_elements();

        // Custom action buttons with "Save and create rubric" option.
        $this->add_custom_action_buttons();
    }

    /**
     * Add custom action buttons including "Save and create rubric".
     *
     * @return void
     */
    private function add_custom_action_buttons() {
        global $PAGE;

        $mform = &$this->_form;

        $buttonarray = [];
        // Note: Moodle's modedit.php redirects to activity view when 'submitbutton' is set,
        // and to course page otherwise. So we swap the names to match expected behavior.
        $buttonarray[] = $mform->createElement('submit', 'submitbutton2', get_string('savechangesandreturntocourse'));
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechangesanddisplay'));
        // The rubric button uses a special class and will set redirect_to_rubric via JS before submitting as submitbutton2.
        $buttonarray[] = $mform->createElement(
            'button',
            'submitbutton_rubric_btn',
            get_string('saveandcreaterubric', 'videoassessment'),
            [
                'id' => 'id_submitbutton_rubric',
                'type' => 'button',
                'data-formchangechecker-ignore-submit' => '1',
            ],
            ['customclassoverride' => 'btn btn-primary']
        );
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');

        // Hidden field to track if we should redirect to rubric creation.
        $mform->addElement('hidden', 'redirect_to_rubric', 0);
        $mform->setType('redirect_to_rubric', PARAM_INT);

        // JavaScript to show/hide the rubric button and handle its click.
        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initRubricButtonVisibility');
    }

    /**
     * Validate form data for video assessment configuration.
     *
     * Performs comprehensive validation including rating percentages,
     * date consistency, and grading limits for both quick setup and
     * advanced configuration modes.
     *
     * @param array $data Form data to validate
     * @param array $files Uploaded files data
     * @return array Array of validation errors, empty if valid
     */
    public function validation($data, $files) {
        // Allow plugin videoassessment types to do any extra validation after the form has been submitted.
        $errors = parent::validation($data, $files);

        // Validate number of peer assessments is a non-negative integer.
        if (isset($data['usedpeers'])) {
            $usedpeers = $data['usedpeers'];
            if (!is_numeric($usedpeers) || intval($usedpeers) != $usedpeers || $usedpeers < 0) {
                $errors['usedpeers'] = get_string('usedpeerserror', 'videoassessment');
            }
        }

            $ratingsum = $data['ratingteacher'] + $data['ratingself'] + $data['ratingpeer'] + $data['ratingclass'];
        if ($ratingsum != 100) {
            $errors['ratingerror'] = get_string('settotalratingtoahundredpercent', 'videoassessment');
        }

        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] < $data['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedatevalidation', 'assign');
            }
        }
        if (!empty($data['cutoffdate']) && !empty($data['duedate'])) {
            if ($data['cutoffdate'] < $data['duedate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'assign');
            }
        }
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'assign');
            }
        }
        if ($data['gradingduedate']) {
            if ($data['allowsubmissionsfromdate'] && $data['allowsubmissionsfromdate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingduefromdatevalidation', 'assign');
            }
            if ($data['duedate'] && $data['duedate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingdueduedatevalidation', 'assign');
            }
        }

            // Validate peer assignments: if peer rating > 0 or usedpeers > 0, peers must be assigned.
            $peerrating = isset($data['ratingpeer']) ? intval($data['ratingpeer']) : 0;
            $usedpeers = isset($data['usedpeers']) ? intval($data['usedpeers']) : 0;

        if ($peerrating > 0 || $usedpeers > 0) {
            // Check if any peers have been assigned.
            $peerassignments = isset($data['peerassignments']) ? $data['peerassignments'] : '{}';
            $peersdata = json_decode($peerassignments, true);

            $haspeers = false;
            if (is_array($peersdata)) {
                foreach ($peersdata as $userid => $peers) {
                    if (!empty($peers) && is_array($peers) && count($peers) > 0) {
                        $haspeers = true;
                        break;
                    }
                }
            }

            if (!$haspeers) {
                $errors['peerassignmenterror'] = get_string('peerassignmentrequired', 'videoassessment');
            }
        }

        return $errors;
    }

    /**
     * Add standard grading elements to the form with video assessment specific options.
     *
     * Creates grading configuration interface including advanced grading methods
     * and grade categories.
     *
     * @param string $itemname Grade item name for component integration
     * @return void
     */
    public function standard_grading_coursemodule_elements_to_grading(string $itemname) {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = &$this->_form;
        $component = "mod_{$this->_modname}";
        $itemnumber = component_gradeitems::get_itemnumber_from_itemname($component, $itemname);
        $gradepassfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradepass');
        if ($this->_features->hasgrades) {
            if (!$this->_features->rating || $this->_features->gradecat) {
                $mform->addElement('header', 'modstandardgrade', get_string('grade', 'videoassessment'));
                $mform->addHelpButton('modstandardgrade', 'grade', 'videoassessment');
            }

            // If supports grades and grades arent being handled via ratings.
            if (!$this->_features->rating) {
                $mform->addElement('modgrade', 'grade', get_string('modgrade', 'videoassessment'));
                $mform->addHelpButton('grade', 'modgrade', 'videoassessment');
                $mform->setDefault('grade', $CFG->gradepointdefault);
            }

            if (
                $this->_features->advancedgrading
                && !empty($this->current->_advancedgradingdata['methods'])
                && !empty($this->current->_advancedgradingdata['areas'])
            ) {
                // Use a single grading method selector for all areas.
                // Get the first area name to use as the primary selector.
                $firstareaname = key($this->current->_advancedgradingdata['areas']);

                    $mform->addElement(
                        'select',
                        'advancedgradingmethod_' . $firstareaname,
                        get_string('advancedgradingmethodsgroup', 'videoassessment'),
                        $this->current->_advancedgradingdata['methods']
                    );
                $mform->addHelpButton('advancedgradingmethod_' . $firstareaname, 'advancedgradingmethodsgroup', 'videoassessment');

                // Set default grading method to 'rubric' if it's available.
                if (isset($this->current->_advancedgradingdata['methods']['rubric'])) {
                    $mform->setDefault('advancedgradingmethod_' . $firstareaname, 'rubric');
                }

                // Add hidden fields for other areas that will sync with the main selector.
                $otherareas = array_keys($this->current->_advancedgradingdata['areas']);
                array_shift($otherareas); // Remove first area as it has the visible select.
                foreach ($otherareas as $areaname) {
                    $mform->addElement('hidden', 'advancedgradingmethod_' . $areaname);
                    $mform->setType('advancedgradingmethod_' . $areaname, PARAM_ALPHANUMEXT);
                    // Set default to 'rubric' for hidden fields too.
                    if (isset($this->current->_advancedgradingdata['methods']['rubric'])) {
                        $mform->setDefault('advancedgradingmethod_' . $areaname, 'rubric');
                    }
                }
            }

            if ($this->_features->gradecat) {
                $mform->addElement(
                    'select',
                    'gradecat',
                    get_string('gradecategory', 'videoassessment'),
                    grade_get_categories_menu($COURSE->id, $this->_outcomesused)
                );
                $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
            }

            // Grade to pass.
            $mform->addElement('text', $gradepassfieldname, get_string('gradepass', 'grades'));
            $mform->addHelpButton($gradepassfieldname, 'gradepass', 'grades');
            $mform->setType($gradepassfieldname, PARAM_RAW);
        }
    }

    /**
     * Add advanced options section to the form.
     *
     * Contains calibration training, fairness bonuses, bulk upload, publish videos, etc.
     *
     * @param object|null $cm Course module object
     * @return void
     */
    public function add_advanced_options($cm) {
        global $COURSE, $CFG, $DB, $PAGE;

        $mform = &$this->_form;

        $mform->addElement('header', 'advancedoptions', get_string('advancedoptions', 'videoassessment'));
        $mform->setExpanded('advancedoptions', false);

        // Training Pre-test.
        $mform->addElement('selectyesno', 'training', get_string('trainingpretest', 'videoassessment'));
            $mform->setDefault('training', 0);
            $mform->addHelpButton('training', 'trainingpretest', 'videoassessment');

        // Training Video (shown when training = yes).
            $mform->addElement(
                'filemanager',
                'trainingvideo',
                get_string('trainingvideo', 'videoassessment'),
                null,
                [
                    'subdirs' => 0,
                    'maxbytes' => $COURSE->maxbytes,
                    'maxfiles' => 1,
                    'accepted_types' => ['video', 'audio'],
                ],
            );
            $mform->addElement('hidden', 'trainingvideoid');
            $mform->setType('trainingvideoid', PARAM_INT);
            $mform->addHelpButton('trainingvideo', 'trainingvideo', 'videoassessment');

        // Training Explanation (shown when training = yes).
            $mform->addElement(
                'textarea',
                'trainingdesc',
                get_string('trainingdesc', 'videoassessment'),
                ['cols' => 50, 'rows' => 8]
            );
            $mform->setDefault('trainingdesc', get_string('trainingdesctext', 'videoassessment'));
            $mform->addHelpButton('trainingdesc', 'trainingdesc', 'videoassessment');

        // Accepted difference in scores (shown when training = yes).
        $diffpercentopts = [];
        for ($i = 100; $i >= 0; $i--) {
            $diffpercentopts[$i] = $i . '%';
        }
        $mform->addElement('select', 'accepteddifference', get_string('accepteddifference', 'videoassessment'), $diffpercentopts);
            $mform->setDefault('accepteddifference', 20);
            $mform->addHelpButton('accepteddifference', 'accepteddifference', 'videoassessment');

        // Initialize JavaScript for training pretest visibility toggle.
        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initTrainingChange');

        // Peer Fairness Bonus.
        $mform->addElement('selectyesno', 'fairnessbonus', get_string('peerfairnessbonus', 'videoassessment'));
        $mform->setDefault('fairnessbonus', 0);
        $mform->addHelpButton('fairnessbonus', 'peerfairnessbonus', 'videoassessment');

        // Bonus Percentage (On top of total).
        $bonuspercentopts = [];
        for ($i = 0; $i <= 100; $i++) {
            $bonuspercentopts[$i] = $i . '%';
        }
        $mform->addElement('select', 'bonuspercentage', get_string('bonuspercentage', 'videoassessment'), $bonuspercentopts);
        $mform->setDefault('bonuspercentage', 10);
        $mform->addHelpButton('bonuspercentage', 'bonuspercentage', 'videoassessment');

        // Bonus Scale groups (6 levels) - difference from teacher score ranges.
        $scaleopts = [];
        for ($i = 0; $i <= 100; $i++) {
            $scaleopts[$i] = $i . '%';
        }
        // Level labels represent difference ranges from teacher score.
        $levellabels = [
            1 => '0-5%',
            2 => '6-10%',
            3 => '11-15%',
            4 => '16-20%',
            5 => '21-25%',
            6 => '26%+',
        ];
        for ($level = 1; $level <= 6; $level++) {
            $bonusscoregroup = [];
            $diffsuffix = ' ' . get_string('difference', 'videoassessment') . ':';
            $bonusscoregroup[] = $mform->createElement('static', '', '', $levellabels[$level] . $diffsuffix);
            $bonusscoregroup[] = $mform->createElement('select', 'bonus' . $level, '', $scaleopts);
            $bonusscoregroup[] = $mform->createElement('static', '', '', get_string('offairnessbonus', 'videoassessment'));
            $mform->addGroup($bonusscoregroup, 'bonusscoregroup' . $level, '', [' '], false);
            // Default values: 100%, 80%, 60%, 40%, 20%, 0%.
            $mform->setDefault('bonus' . $level, max(0, 100 - (($level - 1) * 20)));
        }

        // Self Fairness Bonus.
        $mform->addElement('selectyesno', 'selffairnessbonus', get_string('selffairnessbonus', 'videoassessment'));
        $mform->setDefault('selffairnessbonus', 0);
        $mform->addHelpButton('selffairnessbonus', 'selffairnessbonus', 'videoassessment');

        // Self Bonus Percentage.
        $mform->addElement('select', 'selfbonuspercentage', get_string('bonuspercentage', 'videoassessment'), $bonuspercentopts);
        $mform->setDefault('selfbonuspercentage', 10);
        $mform->addHelpButton('selfbonuspercentage', 'bonuspercentage', 'videoassessment');

        // Self Bonus Scale groups (6 levels).
        for ($level = 1; $level <= 6; $level++) {
            $selfbonusscoregroup = [];
            $diffsuffix = ' ' . get_string('difference', 'videoassessment') . ':';
            $selfbonusscoregroup[] = $mform->createElement('static', '', '', $levellabels[$level] . $diffsuffix);
            $selfbonusscoregroup[] = $mform->createElement('select', 'selfbonus' . $level, '', $scaleopts);
            $selfbonusscoregroup[] = $mform->createElement('static', '', '', get_string('offairnessbonus', 'videoassessment'));
            $mform->addGroup($selfbonusscoregroup, 'selfbonusscoregroup' . $level, '', [' '], false);
            // Default values: 100%, 80%, 60%, 40%, 20%, 0%.
            $mform->setDefault('selfbonus' . $level, max(0, 100 - (($level - 1) * 20)));
        }

        // Initialize JavaScript for fairness bonus visibility toggle.
        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initFairnessBonusChange');

        // Automatic File Deletion at Course End Date.
        $mform->addElement('selectyesno', 'autodeletefiles', get_string('autodeletefiles', 'videoassessment'));
        $mform->setDefault('autodeletefiles', 1);
        $mform->addHelpButton('autodeletefiles', 'autodeletefiles', 'videoassessment');

        // Bulk upload videos and related management links (only for existing activities).
        if ($cm) {
            $viewurl = new moodle_url('/mod/videoassessment/view.php', ['id' => $cm->id]);
            $context = context_module::instance($cm->id);

            $va = $DB->get_record('videoassessment', ['id' => $cm->instance]);
            $course = $DB->get_record('course', ['id' => $va->course]);

            require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
            $vaobj = new va($context, $cm, $course);
            $isteacher = $vaobj->is_teacher();

            if ($isteacher) {
                // Bulk Upload Videos.
                if (!va::uses_mobile_upload()) {
                    $this->add_link_element(
                        'videoassessment:bulkupload',
                        new moodle_url('/mod/videoassessment/bulkupload/index.php', ['cmid' => $cm->id]),
                        get_string('videoassessment:bulkupload', 'videoassessment'),
                    );
                }

                // Bulk Video Deletion.
                $this->add_link_element(
                    'deletevideos',
                    new moodle_url('/mod/videoassessment/deletevideos.php', ['id' => $cm->id]),
                    get_string('deletevideos', 'videoassessment'),
                );

                    // Associate.
                $this->add_link_element(
                    'associate',
                    new moodle_url($viewurl, ['action' => 'videos']),
                    get_string('associate', 'videoassessment'),
                );

                    // Publish Videos.
                $this->add_link_element(
                    'publishvideos',
                    new moodle_url($viewurl, ['action' => 'publish']),
                    get_string('publishvideos', 'videoassessment'),
                );
            }
        }
    }

    /**
     * Add video management interface elements to the form.
     *
     * Creates management links for video upload, deletion, association,
     * assessment, and rubric management for teachers.
     *
     * @deprecated This method is kept for backwards compatibility but is no longer used.
     * @return void
     */
    public function manage_video() {
        // This method is deprecated. Video management is now in add_advanced_options().
        return;
    }

    /**
     * Add notification configuration elements to the form.
     *
     * Creates comprehensive notification settings including teacher comments,
     * peer assessments, reminder notifications, and video upload alerts.
     *
     * @return void
     */
    public function add_notifications() {
        global $PAGE;
        $mform = &$this->_form;

        $mform->addElement('header', 'notifications', get_string('notifications', 'videoassessment'));
        $mform->addHelpButton('notifications', 'notifications', 'videoassessment');
        $notificationscarriergroup[] = $mform->createElement(
            'advcheckbox',
            'isregisteredemail',
            "",
            get_string('registeredemail', 'videoassessment'),
        );
        $mform->setDefault('isregisteredemail', 0);
        $notificationscarriergroup[] = $mform->createElement(
            'advcheckbox',
            'ismobilequickmail',
            "",
            get_string('mobilequickmail', 'videoassessment'),
        );
        $mform->setDefault('ismobilequickmail', 0);
        $mform->addGroup(
            $notificationscarriergroup,
            'notificationcarriergroup',
            get_string('notificationcarriergroup', 'videoassessment'),
            [' ', '<br />'],
            false,
        );
        $mform->addHelpButton('notificationcarriergroup', 'notificationcarriergroup', 'videoassessment');

        $mform->addElement(
            'advcheckbox',
            'teachercommentnotification',
            get_string('teachercommentnotification', 'videoassessment'),
            '<b>' .
            get_string('teachercomentnotificationlabel', 'videoassessment') .
            '</b><label class="teacher-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('teachercommentnotification', 0);
        $mform->addHelpButton('teachercommentnotification', 'teachercommentnotification', 'videoassessment');
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.' . get_string('whentosendnotification', 'videoassessment') . '</b></div>',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstassessmentbyteacher',
            "",
            get_string('firstassessmentbyteacher', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isadditionalassessment',
            "",
            get_string('additionalassessmentbyteacher', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            get_string('whatinfomationtosendcontents', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.' . get_string('templatetextfornotification', 'videoassessment') . '</b></div>',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'textarea',
            'teachernotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('teachernotificationtemplate', get_string('teachernotificationtemplate', 'videoassessment'));
        $mform->addGroup($teachernotificationgroup, 'teachernotificationgroup', "", [' <br/>', '<br/>'], false);

        $mform->addElement(
            'advcheckbox',
            'peercommentnotification',
            '',
            '<b>' .
            get_string('peercomentnotificationlabel', 'videoassessment') .
            '</b><label class="teacher-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('peercommentnotification', 0);
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.' . get_string('whentosendnotification', 'videoassessment') . '</b></div>',
        );
        $peernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstassessmentbystudent',
            "",
            get_string('firstassessmentbystudent', 'videoassessment'),
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            get_string('whatinfomationtosendcontents', 'videoassessment'),
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.' . get_string('templatetextfornotification', 'videoassessment') . '</b></div>',
        );
        $peernotificationgroup[] = $mform->createElement(
            'textarea',
            'peertnotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('peertnotificationtemplate', get_string('peertnotificationtemplate', 'videoassessment'));
        $mform->addGroup($peernotificationgroup, 'peernotificationgroup', "", [' <br/>', '<br/>'], false);

        $duadate = ["1" => 1, "2" => 2, "3" => 3, "4" => 4, "5" => 5];
        $mform->addElement(
            'advcheckbox',
            'remindernotification',
            "",
            '<b>' . get_string('remindernotification', 'videoassessment') .
            '</b><label class="reminder-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('remindernotification', 0);
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.' . get_string('whentosendnotification', 'videoassessment') . '</b></div>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isbeforeduedate',
            "",
            get_string('beforeduedate', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement(
            'select',
            'beforeduedate',
            get_string('daysbefore', 'videoassessment'),
            $duadate,
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<span class="form-check-inline fitem" style="width: auto;">' .
            get_string('daysbefore', 'videoassessment') . '</span>',
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isonduedate',
            "",
            get_string('onduedate', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isafterduedate',
            "",
            get_string('afterduedateevery', 'videoassessment'),
            ['group' => 1]
        );
        $remindernotificationgroup[] = $mform->createElement('select', 'afterduedate', "", $duadate);
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<span class="form-check-inline fitem" style="width: auto;">' .
            get_string('days') . '</span>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnovideouploaded',
            "",
            get_string('onvideouploaded', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnoselfassessment',
            "",
            get_string('onselfassessment', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnoselfassessmentwithcomments',
            "",
            get_string('onselfassessmentwithcomments', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnopeerassessment',
            "",
            get_string('onpeerassessment', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.' . get_string('templatetextfornotification', 'videoassessment') . '</b></div>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'textarea',
            'remindernotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80]
        );
        $mform->setDefault('remindernotificationtemplate', get_string('remindernotificationtemplate', 'videoassessment'));
        $mform->addGroup($remindernotificationgroup, 'remindernotificationgroup', "", ['', ' '], false);

        $mform->addElement(
            'advcheckbox',
            'videonotification',
            "",
            '<b>' . get_string('videouploadnotificationlabel', 'videoassessment') .
            '</b><label class="video-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('videonotification', 0);
        $videonotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.' . get_string('whentosendnotification', 'videoassessment') . '</b></div>',
        );
        $videonotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstupload',
            "",
            get_string('videouploadforthefirsttime', 'videoassessment'),
        );
        $videonotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'iswheneverupload',
            "",
            get_string('whenevervideoupload', 'videoassessment'),
        );
        $videonotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('templatetextfornotification', 'videoassessment') . '</b></div>',
        );
        $videonotificationgroup[] = $mform->createElement(
            'textarea',
            'videonotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('videonotificationtemplate', get_string('videonotificationtemplate', 'videoassessment'));
        $mform->addGroup($videonotificationgroup, 'videonotificationgroup', "", [' <br/>', '<br/>'], false);

        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initNotificationFormChange');
        $PAGE->requires->css(new \moodle_url('/mod/videoassessment/mod_form.css'));
    }

    /**
     * Add a link element to the form for management actions.
     *
     * Creates clickable link elements with help buttons for various
     * video assessment management functions.
     *
     * @param string $linkname Name identifier for the link element
     * @param moodle_url $href URL for the link destination
     * @param string $linktext Display text for the link
     * @return void
     */
    private function add_link_element($linkname, $href, $linktext) {
        $mform = &$this->_form;
        $mform->addGroup([], $linkname . 'group', "<a class='managelink' href='$href'>$linktext</a>", null, false);
        $mform->addHelpButton($linkname . 'group', $linkname, 'videoassessment');
    }

    /**
     * Render the peer assignment interface for embedding in the form.
     *
     * Creates an HTML table with student names and peer assignment dropdowns,
     * plus random assignment links.
     *
     * @param object|null $cm Course module object (null for new activities)
     * @return string HTML content for the peer assignment interface
     */
    private function render_peers_assignment_interface($cm) {
        global $DB, $OUTPUT, $PAGE, $COURSE;

        $o = '';
        $isexisting = !empty($cm);

        // Get users with ONLY the student role (exclude teachers/managers) for the table rows.
        $students = $this->get_students_only($this->context);

        if (empty($students)) {
            $o .= html_writer::tag(
                'p',
                get_string('nostudentsingroup', 'videoassessment'),
                ['class' => 'alert alert-info']
            );
            return $o;
        }

        // Get only students (excluding teachers) for the dropdown options.
        // Use core_user\fields to get all required name fields for fullname().
        // Ensure 'id' is the first field (required for get_records_sql to work properly).
        $namefieldsql = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
        // Prepend u.id to ensure it's first (get_records_sql uses first column as key).
        $userfields = 'u.id, ' . $namefieldsql->selects;
        // Get only students (same as $students but with all name fields).
        $allusers = $this->get_students_only($this->context);

        // Build student data for JavaScript (for table rows).
        $studentdata = [];
        foreach ($students as $student) {
            $studentdata[$student->id] = fullname($student);
        }

        // Get groups for the course.
        $groups = groups_get_all_groups($COURSE->id);
        $groupdata = [];
        foreach ($groups as $group) {
            // Get all members from each group (including teachers).
            $groupmembers = [];
            $namefieldsql = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
            $userfields = 'u.id, ' . $namefieldsql->selects;
            $allmembers = groups_get_members($group->id, $userfields);

            // Include all group members (students and teachers).
            foreach ($allmembers as $member) {
                $groupmembers[] = $member->id;
            }

            if (!empty($groupmembers)) {
                $groupdata[$group->id] = [
                    'name' => $group->name,
                    'members' => $groupmembers,
                ];
            }
        }

        // Build allUsers data (students + teachers from groups).
        // First, collect all user IDs from groups (including teachers).
        $allgroupuserids = [];
        foreach ($groupdata as $group) {
            foreach ($group['members'] as $memberid) {
                $allgroupuserids[$memberid] = $memberid;
            }
        }

        // Get user data for all group members (including teachers).
        $allusersdata = [];
        // Start with students.
        foreach ($allusers as $user) {
            $allusersdata[$user->id] = fullname($user);
        }
        // Add teachers from groups.
        if (!empty($allgroupuserids)) {
            // Get user records with all name fields.
            // get_records_list doesn't use table alias, so we need field names without 'u.' prefix.
            $namefields = \core_user\fields::for_name()->get_required_fields();
            $userfields = 'id, ' . implode(', ', $namefields);
            $groupusers = $DB->get_records_list('user', 'id', array_values($allgroupuserids), '', $userfields);
            foreach ($groupusers as $user) {
                if (!isset($allusersdata[$user->id])) {
                    $allusersdata[$user->id] = fullname($user);
                }
            }
        }

        // Use only students for table display.
        $allusersfortable = $students;

        // Get existing peer assignments if editing.
        $existingpeers = [];
        if ($isexisting) {
            $instance = $DB->get_record('videoassessment', ['id' => $cm->instance], '*', MUST_EXIST);
            $peerrecords = $DB->get_records('videoassessment_peers', ['videoassessment' => $instance->id]);
            foreach ($peerrecords as $record) {
                if (!isset($existingpeers[$record->userid])) {
                    $existingpeers[$record->userid] = [];
                }
                $existingpeers[$record->userid][] = $record->peerid;
            }
        }

        $o .= html_writer::start_tag('div', ['class' => 'peers-assignment-container', 'id' => 'peers-assignment-container']);

        // Random assignment buttons (JavaScript-based for new activities).
        $o .= html_writer::start_tag('div', ['class' => 'mb-3']);
        $o .= get_string('assignpeerassessorsrandomly', 'videoassessment') . ': ';
        $o .= html_writer::tag('button', get_string('course'), [
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-secondary',
            'id' => 'random-peers-course',
        ]);
        $o .= ' ';

        // Group button with dropdown if there are groups.
        if (!empty($groupdata)) {
            $o .= html_writer::start_tag('div', ['class' => 'd-inline-block dropdown']);
            $o .= html_writer::tag('button', get_string('group'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary dropdown-toggle',
                'id' => 'random-peers-group-dropdown',
                'data-bs-toggle' => 'dropdown',
                'aria-haspopup' => 'true',
                'aria-expanded' => 'false',
            ]);
            $o .= html_writer::start_tag('div', ['class' => 'dropdown-menu', 'aria-labelledby' => 'random-peers-group-dropdown']);
            foreach ($groupdata as $groupid => $group) {
                $o .= html_writer::tag('a', $group['name'], [
                    'class' => 'dropdown-item random-peers-group-item',
                    'href' => '#',
                    'data-groupid' => $groupid,
                ]);
            }
            $o .= html_writer::end_tag('div');
            $o .= html_writer::end_tag('div');
        } else {
            $o .= html_writer::tag('button', get_string('group') . ' (' . get_string('nogroups', 'group') . ')', [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary',
                'disabled' => 'disabled',
            ]);
        }
        $o .= html_writer::end_tag('div');

        // Build table.
        $o .= html_writer::start_tag('table', ['class' => 'generaltable peers-table', 'id' => 'peers-table']);
        $o .= html_writer::start_tag('thead');
        $o .= html_writer::start_tag('tr');
        $o .= html_writer::tag('th', get_string('fullname'));
        $o .= html_writer::tag('th', get_string('peers', 'videoassessment'));
        $o .= html_writer::end_tag('tr');
        $o .= html_writer::end_tag('thead');
        $o .= html_writer::start_tag('tbody');

        foreach ($allusersfortable as $user) {
            $userpeers = isset($existingpeers[$user->id]) ? $existingpeers[$user->id] : [];

            $o .= html_writer::start_tag('tr', ['data-userid' => $user->id]);
            $o .= html_writer::tag('td', fullname($user));

            // Peers cell with container for assigned peers and dropdown.
            $o .= html_writer::start_tag('td');
            $o .= html_writer::start_tag('div', [
                'class' => 'assigned-peers',
                'id' => 'assigned-peers-' . $user->id,
            ]);

            // Show existing peers (students only).
            foreach ($userpeers as $peerid) {
                // Check studentdata for peer name.
                $peername = isset($studentdata[$peerid]) ? $studentdata[$peerid] : null;

                if ($peername) {
                    $o .= html_writer::start_tag('span', [
                        'class' => 'peer-badge badge bg-secondary text-dark me-1 mb-1',
                        'data-peerid' => $peerid,
                        'style' => 'display: inline-block; margin: 2px;',
                    ]);
                    $o .= $peername . ' ';
                    $o .= html_writer::tag('a', '×', [
                        'href' => '#',
                        'class' => 'remove-peer text-white',
                        'data-userid' => $user->id,
                        'data-peerid' => $peerid,
                        'style' => 'text-decoration: none; font-weight: bold;',
                    ]);
                    $o .= html_writer::end_tag('span');
                }
            }

            $o .= html_writer::end_tag('div');

            // Add peer dropdown - students only.
            $o .= html_writer::start_tag('select', [
                'class' => 'form-control form-control-sm add-peer-select mt-1',
                'data-userid' => $user->id,
                'style' => 'width: auto; display: inline-block;',
            ]);
            $o .= html_writer::tag('option', get_string('addpeer', 'videoassessment'), ['value' => '']);

            // Add students only.
            foreach ($students as $candidate) {
                if ($candidate->id != $user->id) {
                    $disabled = in_array($candidate->id, $userpeers) ? 'disabled' : '';
                    $o .= html_writer::tag('option', fullname($candidate), [
                        'value' => $candidate->id,
                        'disabled' => $disabled ? 'disabled' : null,
                    ]);
                }
            }

            $o .= html_writer::end_tag('select');

            $o .= html_writer::end_tag('td');
            $o .= html_writer::end_tag('tr');
        }

        $o .= html_writer::end_tag('tbody');
        $o .= html_writer::end_tag('table');
        $o .= html_writer::end_tag('div');

        // Initialize JavaScript with student data, existing peers, and group data.
        $jsparams = [
            'students' => $studentdata,
            'allUsers' => $allusersdata, // Only students, no teachers.
            'existingPeers' => $existingpeers,
            'isExisting' => $isexisting,
            'cmid' => $isexisting ? $cm->id : 0,
            'sesskey' => sesskey(),
            'usedpeers' => $isexisting ? $instance->usedpeers : 0,
            'groups' => $groupdata,
        ];
        $PAGE->requires->js_call_amd('mod_videoassessment/peer_assignment', 'init', [$jsparams]);

        return $o;
    }

    /**
     * Get enrolled users who have ONLY the student role.
     *
     * This excludes users who have any teacher, manager, or editing teacher roles,
     * even if they also have the student role.
     *
     * @param context $context The module context (used to get course context)
     * @return array Array of user objects with only student role
     */
    private function get_students_only($context) {
        global $DB, $COURSE;

        // Get the course context - role assignments are at course level, not module level.
        $coursecontext = context_course::instance($COURSE->id);

        // Get the student role ID.
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
        if (!$studentrole) {
            return [];
        }

        // Get role IDs for non-student roles (teacher, editingteacher, manager).
        $excluderoles = $DB->get_records_select(
            'role',
            "shortname IN ('teacher', 'editingteacher', 'manager', 'coursecreator')",
            null,
            '',
            'id'
        );
        $excluderoleids = array_keys($excluderoles);

        // Get all users enrolled with the student role in the course context.
        $students = get_role_users($studentrole->id, $coursecontext, false, 'u.*', 'u.lastname, u.firstname');

        if (empty($students)) {
            return [];
        }

        if (empty($excluderoleids)) {
            return $students;
        }

        // Filter out users who also have any excluded role.
        $filteredstudents = [];
        foreach ($students as $student) {
            $hasexcludedrole = false;
            foreach ($excluderoleids as $roleid) {
                if (user_has_role_assignment($student->id, $roleid, $coursecontext->id)) {
                    $hasexcludedrole = true;
                    break;
                }
            }
            if (!$hasexcludedrole) {
                $filteredstudents[$student->id] = $student;
            }
        }

        return $filteredstudents;
    }

    /**
     * Override get_data to sync all grading method fields.
     *
     * When a single grading method selector is used, this method ensures
     * all grading area methods are set to the same value.
     *
     * @return object|null Form data object with synced grading methods
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data) {
            // Process gradepass field - Moodle parent processes 'gradepass' with unformat_float(),
            // but we need to ensure it's properly set (default to 0 if empty).
            // For itemnumber 0 (which maps to 'grading'), the field name is just 'gradepass'.
            // Always ensure gradepass is set and is numeric (never null or empty string)
            // Parent processes 'gradepass' with unformat_float(), but we need to ensure it's always set.
            if (property_exists($data, 'gradepass')) {
                // Parent already processed it with unformat_float(), but ensure it's not empty/null.
                if ($data->gradepass === '' || $data->gradepass === null) {
                    $data->gradepass = 0;
                } else {
                    // Ensure it's a numeric value.
                    $data->gradepass = (float)$data->gradepass;
                }
            } else {
                // Field doesn't exist in data (form field was empty/not submitted), set default to 0.
                $data->gradepass = 0;
            }

            // Final safety check - ensure it's always numeric, never null.
            $data->gradepass = (float)$data->gradepass;
            if ($data->gradepass < 0) {
                $data->gradepass = 0;
            }

            if (!empty($this->current->_advancedgradingdata['areas'])) {
                // Get all area names.
                $areas = array_keys($this->current->_advancedgradingdata['areas']);

                if (count($areas) > 1) {
                    // Get the value from the first (visible) selector.
                    $firstareaname = $areas[0];
                    $selectedmethod = $data->{'advancedgradingmethod_' . $firstareaname} ?? '';

                    // Sync all other areas to the same method.
                    foreach ($areas as $areaname) {
                        $data->{'advancedgradingmethod_' . $areaname} = $selectedmethod;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Set defaults after form data is loaded.
     * This ensures that new instances default to 'rubric' grading method,
     * and that if a rubric definition exists (e.g., after template selection),
     * the method is set to 'rubric'.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;

        // Set default to 'rubric' for new instances or when rubric definition exists.
        if (!empty($this->current->_advancedgradingdata['areas'])) {
            $areas = array_keys($this->current->_advancedgradingdata['areas']);

            foreach ($areas as $areaname) {
                $fieldname = 'advancedgradingmethod_' . $areaname;

                // Only set default if field exists.
                if ($mform->elementExists($fieldname)) {
                    $currentvalue = $mform->getElementValue($fieldname);
                    $isempty = empty($currentvalue) || (is_array($currentvalue) && empty($currentvalue[0]));

                    // Check if a rubric definition exists for this area.
                    $hasrubricdefinition = false;
                    global $CFG;
                    if (!empty($this->current->_advancedgradingdata['areas'][$areaname]['method'])) {
                        // Method is already set, check if it's rubric.
                        $hasrubricdefinition = ($this->current->_advancedgradingdata['areas'][$areaname]['method'] === 'rubric');
                    } else {
                        // Check if rubric definition exists by checking the grading manager.
                        try {
                            require_once($CFG->dirroot . '/grade/grading/lib.php');
                            if (!empty($this->context)) {
                                $gradingmanager = get_grading_manager($this->context, 'mod_videoassessment', $areaname);
                                $method = $gradingmanager->get_active_method();
                                if (empty($method)) {
                                    // No active method, but check if rubric definition exists.
                                    $gradingmanager->set_area($areaname);
                                    $controller = $gradingmanager->get_controller('rubric');
                                    if ($controller && $controller->is_form_defined()) {
                                        $hasrubricdefinition = true;
                                        // Set the method to 'rubric' since a definition exists.
                                        $gradingmanager->set_active_method('rubric');
                                    }
                                } else if ($method === 'rubric') {
                                    $hasrubricdefinition = true;
                                }
                            }
                        } catch (Exception $e) {
                            // Ignore errors, just continue.
                            debugging('Error checking rubric definition: ' . $e->getMessage(), DEBUG_NORMAL);
                        }
                    }

                    // Set to 'rubric' if empty and rubric is available, or if rubric definition exists.
                    if (isset($this->current->_advancedgradingdata['methods']['rubric'])) {
                        if ($isempty || $hasrubricdefinition) {
                            $mform->setDefault($fieldname, 'rubric');
                        }
                    }
                }
            }
        }
    }
}
