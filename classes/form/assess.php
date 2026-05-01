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

/**
 * Moodle form for advanced grading within the Video Assessment module.
 *
 * This form handles the display and submission of grades and comments
 * for video assessments, supporting both simple and advanced grading methods.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assess extends \moodleform {
    /**
     * Stores the advanced grading instance(s) if used in grading.
     *
     * This can be an object containing multiple grading instances indexed by timing.
     *
     * @var \stdClass|array
     */
    private $advancegradinginstance;

    /**
     * Define the form structure and elements.
     *
     * Sets up hidden fields, grading sections, and action buttons
     * for the video assessment grading form.
     *
     * @return void
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $data = $this->_customdata;

        if (isset($data->advancedgradinginstance)) {
            $this->use_advanced_grading($data->advancedgradinginstance);
        }

        $formattr = $mform->getAttributes();
        $formattr['id'] = 'submitform';
        $mform->setAttributes($formattr);
        // Hidden params.
        $mform->addElement('hidden', 'action', 'assess');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'userid', $data->userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'id', $data->va->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'mode', 'grade');
        $mform->setType('mode', PARAM_TEXT);
        $mform->addElement('hidden', 'menuindex', "0");
        $mform->setType('menuindex', PARAM_INT);
        $mform->addElement('hidden', 'saveuserid', "-1");
        $mform->setType('saveuserid', PARAM_INT);
        $mform->addElement('hidden', 'filter', "0");
        $mform->setType('filter', PARAM_INT);
        $mform->addElement('hidden', 'gradertype', $data->gradertype);
        $mform->setType('gradertype', PARAM_ALPHA);

        if (!empty($data->rubricspassed)) {
            $mform->addElement('hidden', 'rubrics_passed', json_encode($data->rubricspassed));
            $mform->setType('rubrics_passed', PARAM_TEXT);
        }

        $this->add_grades_section();

        $this->add_action_buttons();
    }

    /**
     * Gets or sets the instance for advanced grading.
     *
     * Manages the advanced grading instance used for rubric-based grading.
     *
     * @param array|\stdClass|false $gradinginstance Advanced grading instance or false to get current
     * @return \stdClass|array|false Current advanced grading instance
     */
    public function use_advanced_grading($gradinginstance = false) {
        if ($gradinginstance !== false) {
            $this->advancegradinginstance = $gradinginstance;
        }
        return $this->advancegradinginstance;
    }

    /**
     * Add the grades section to the form.
     *
     * Creates grading elements for each timing (before/after) with support
     * for advanced grading (rubric) or simple grading (points/scale).
     *
     * @return void
     */
    public function add_grades_section() {
        global $CFG, $DB, $USER, $OUTPUT;

        $mform = $this->_form;
        $data = $this->_customdata;
        /* @var $va \mod_videoassessment\va */
        $va = $data->va;
        $attributes = [];

        $user = $DB->get_record('user', ['id' => optional_param('userid', 0, PARAM_INT)]);

        $mform->addElement('header', 'Grades', $user->firstname . ' ' . $user->lastname . $OUTPUT->user_picture($user, ['size' => 100]));

        $grademenu = make_grades_menu($va->va->grade);
        $gradinginstances = $this->use_advanced_grading();

        // Check if we have any advanced grading instances.
        // Even if $gradinginstances is set, it might be empty, so check for actual instances.
        $hasadvancedgrading = false;
        if ($gradinginstances && is_object($gradinginstances)) {
            foreach ($va->timings as $timing) {
                if (!empty($gradinginstances->$timing)) {
                    $hasadvancedgrading = true;
                    break;
                }
            }
        }

        foreach ($va->timings as $timing) {
            if (property_exists($this->_customdata, 'grade' . $timing)) {
                $grade = $this->_customdata->{'grade' . $timing};
            }

            // Use advanced grading if we have at least one instance.
            if ($hasadvancedgrading) {
                // Grade type -rubric.
                $mform->addElement('hidden', 'gradecategory' . $timing, 1);
                $mform->setType('gradecategory' . $timing, PARAM_RAW);
                if (!empty($gradinginstances->$timing)) {
                    $gradinginstance = $gradinginstances->$timing;
                    $gradinginstance->get_controller()->set_grade_range($grademenu);
                    $gradingelement = $mform->addElement(
                        'grading',
                        'advancedgrading' . $timing,
                        $va->str('grade') . ':',
                        ['gradinginstance' => $gradinginstance]
                    );
                    if ($data->gradingdisabled) {
                        $gradingelement->freeze();
                    } else {
                        $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
                        $mform->setType('advancedgradinginstanceid', PARAM_INT);
                    }
                } else {
                    // Ensure that grading cannot be performed unless a rubric has been created.
                    $mform->addElement('hidden', 'xgrade' . $timing, -1);
                    $mform->setType('xgrade' . $timing, PARAM_INT);
                    continue;
                }
            } else {
                // Use simple direct grading.
                if ($va->va->grade > 0) {
                    // Grade type -simple direct grading【point】.
                    $mform->addElement('hidden', 'gradecategory' . $timing, 2);
                    $mform->setType('gradecategory' . $timing, PARAM_RAW);
                    $name = get_string('gradeoutof', 'assign', $va->va->grade);
                    if (!$data->gradingdisabled) {
                        $gradingelement = $mform->addElement('text', 'xgrade' . $timing, $name);
                        $mform->addHelpButton('xgrade' . $timing, 'gradeoutofhelp', 'assign');
                        $mform->setType('xgrade' . $timing, PARAM_RAW);
                        if (isset($grade->grade)) {
                            $mform->setDefault('xgrade' . $timing, $grade->grade);
                        }
                    } else {
                        $strgradelocked = get_string('gradelocked', 'assign');
                        $mform->addElement('static', 'gradedisabled', $name, $strgradelocked);
                        $mform->addHelpButton('gradedisabled', 'gradeoutofhelp', 'assign');
                    }
                } else {
                    // Grade type -simple direct grading【scale】.
                    $mform->addElement('hidden', 'gradecategory' . $timing, 3);
                    $mform->setType('gradecategory' . $timing, PARAM_RAW);
                    $grademenu = [-1 => get_string("nograde")] + make_grades_menu($va->va->grade);
                    if (count($grademenu) > 1) {
                        $gradingelement = $mform->addElement('select', 'xgrade' . $timing, get_string('grade') . ':', $grademenu);
                        // The grade is already formatted with format_float so it needs to be converted back to an integer.
                        if (!empty($data->grade)) {
                            $data->grade = (int)unformat_float($data->grade);
                        }

                        $mform->setType('xgrade' . $timing, PARAM_INT);
                        if (isset($grade->grade)) {
                            $mform->setDefault('xgrade' . $timing, $grade->grade);
                        }
                        if ($data->gradingdisabled) {
                            $gradingelement->freeze();
                        }
                    }
                }
            }
            if (!empty($data->enableoutcomes)) {
                foreach ($data->grading_info->outcomes as $n => $outcome) {
                    $options = make_grades_menu(-$outcome->scaleid);
                    if ($outcome->grades[$data->submission->userid]->locked) {
                        $options[0] = get_string('nooutcome', 'grades');
                        $mform->addElement(
                            'static',
                            'outcome_' . $n . '[' . $data->userid . ']',
                            $outcome->name . ':',
                            $options[$outcome->grades[$data->submission->userid]->grade]
                        );
                    } else {
                        $options[''] = get_string('nooutcome', 'grades');
                        $attributes = ['id' => 'menuoutcome_' . $n ];
                        $mform->addElement('select', 'outcome_' . $n . '[' . $data->userid . ']', $outcome->name . ':', $options, $attributes);
                        $mform->setType('outcome_' . $n . '[' . $data->userid . ']', PARAM_INT);
                        $mform->setDefault('outcome_' . $n . '[' . $data->userid . ']', $outcome->grades[$data->submission->userid]->grade);
                    }
                }
            }
            $coursecontext = \context_module::instance($data->cm->id);
            $gradestr = '-';
            if (isset($grade->grade) && $grade->grade > -1) {
                $gradestr = $grade->grade . '%';
            }
            $mform->addElement(
                'static',
                'finalgrade' . $timing,
                va::str('currentgrade') . ':',
                \html_writer::tag('span', $gradestr, ['class' => 'mark'])
            );
            $mform->setType('finalgrade' . $timing, PARAM_INT);

            // Get course maxbytes setting for file uploads using Moodle's standard function.
            global $COURSE, $CFG, $PAGE;
            $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);

            // Editor options with file upload support.
            $editoroptions = [
                'maxfiles' => EDITOR_UNLIMITED_FILES,
                'maxbytes' => $maxbytes,
                'noclean' => true,
                'context' => $coursecontext,
                'subdirs' => true,
            ];

            $fieldname = 'submissioncomment' . $timing;

            // Prepare editor data with file support if grade exists.
            if (isset($grade->submissioncomment) && isset($grade->id)) {
                $editorvalue = new \stdClass();
                $editorvalue->text = $grade->submissioncomment;
                $editorvalue->textformat = isset($grade->submissioncommentformat) && $grade->submissioncommentformat > 0
                    ? $grade->submissioncommentformat
                    : FORMAT_HTML;

                // Prepare editor with file area support.
                // file_prepare_standard_editor uses 'text' as the field name, so it creates 'text_editor' property.
                $editorvalue = file_prepare_standard_editor(
                    $editorvalue,
                    'text',
                    $editoroptions,
                    $coursecontext,
                    'mod_videoassessment',
                    'submissioncomment',
                    $grade->id
                );

                $mform->addElement(
                    'editor',
                    $fieldname,
                    get_string('feedback', 'videoassessment') . ':',
                    ['cols' => 50, 'rows' => 8],
                    $editoroptions
                );
                $mform->setType($fieldname, PARAM_RAW);
                // File_prepare_standard_editor creates 'text_editor' property, not fieldname_editor.
                $mform->setDefault($fieldname, $editorvalue->text_editor);
            } else {
                // New feedback - no file area needed yet.
                $mform->addElement(
                    'editor',
                    $fieldname,
                    get_string('feedback', 'videoassessment') . ':',
                    ['cols' => 50, 'rows' => 8],
                    $editoroptions
                );
                $mform->setType($fieldname, PARAM_RAW);
                if (isset($grade->submissioncomment)) {
                    $mform->setDefault(
                        $fieldname,
                        [
                            'text' => $grade->submissioncomment,
                            'format' => FORMAT_HTML,
                        ],
                    );
                }
            }
            if ($data->gradertype == "teacher" || $data->gradertype == "peer") {
                $mform->addElement('advcheckbox', "isnotifystudent", "notify student", [], [0, 1]);
            }
            // Determine default value: check user preference first, then existing grade, then default to 1.
            global $USER;
            $defaultnotify = get_user_preferences('videoassessment_notify_student_default', null);
            if ($defaultnotify !== null) {
                $mform->setDefault("isnotifystudent", (int)$defaultnotify);
            } else if (isset($grade->isnotifystudent)) {
                $mform->setDefault("isnotifystudent", $grade->isnotifystudent);
            } else {
                $mform->setDefault('isnotifystudent', 1);
            }
        }
    }

    /**
     * Validate form data for grade ranges and required fields.
     *
     * Ensures grades are within valid ranges (0-100) for point-based grading.
     *
     * @param array $data Form data to validate
     * @param array $files Uploaded files array
     * @return array Array of validation errors
     */
    public function validation($data, $files) {
        // Allow plugin videoassessment types to do any extra validation after the form has been submitted.
        $errors = parent::validation($data, $files);
        $cdata = $this->_customdata;
        /* @var $va \mod_videoassessment\va */
        $va = $cdata->va;

        // Check if advanced grading is being used and validate rubric completeness.
        // Use the instances that are already set up in the form.
        $gradinginstances = $this->use_advanced_grading();
        if ($gradinginstances && is_object($gradinginstances)) {
            foreach ($va->timings as $timing) {
                if (!empty($gradinginstances->$timing)) {
                    $gradinginstance = $gradinginstances->$timing;

                    // Check if rubric data was submitted for this timing.
                    // Try multiple ways to get the grading data - it might be in different formats.
                    $gradingdata = null;
                    $fieldname = 'advancedgrading' . $timing;

                    // First try from the $data array (from exportValues).
                    if (isset($data[$fieldname])) {
                        $gradingdata = $data[$fieldname];
                    }

                    // If not found, try to get it directly from the form element's submit value.
                    if ($gradingdata === null) {
                        $element = $this->_form->getElement($fieldname);
                        if ($element && method_exists($element, 'getSubmitValue')) {
                            $gradingdata = $element->getSubmitValue();
                        }
                    }

                    // If still not found, try $_POST directly (as a last resort).
                    if ($gradingdata === null && isset($_POST[$fieldname])) {
                        $gradingdata = $_POST[$fieldname];
                    }

                    // Only validate if data was submitted and form is not empty.
                    // Empty forms are allowed - validation only happens when user tries to submit incomplete data.
                    if ($gradingdata !== null && is_array($gradingdata) && !$gradinginstance->is_empty_form($gradingdata)) {
                        // Form has some data - validate that all criteria are graded.
                        // validate_grading_element returns true if all criteria are properly graded.
                        $isvalid = $gradinginstance->validate_grading_element($gradingdata);
                        if (!$isvalid) {
                            $errorfield = 'advancedgrading' . $timing;
                            $errormessage = get_string('rubricnotcompleted', 'videoassessment');
                            $errors[$errorfield] = $errormessage;
                            // Also set error on the form element directly to ensure it's displayed.
                            $this->_form->setElementError($errorfield, $errormessage);
                        }
                    }
                    // If gradingdata is null, an empty rubric form, or simply not
                    // submitted at all, fall through and allow the (empty)
                    // submission - no rubric error is raised.
                }
            }
        }

        foreach ($va->timings as $timing) {
            if (!empty($data['xgrade' . $timing]) && $va->va->grade > 0) {
                if (0 > $data['xgrade' . $timing] || $data['xgrade' . $timing] > 100) {
                    $errors['xgrade' . $timing] = 'Enter a number from 0-100. ';
                }
            }
        }

        return $errors;
    }

    /**
     * Add action buttons to the form.
     *
     * Creates submit and cancel buttons for the grading form.
     *
     * @param boolean $cancel Whether to show cancel button
     * @param string|null $submitlabel Custom label for submit button
     * @return void
     */
    public function add_action_buttons($cancel = true, $submitlabel = null) {
        $mform = $this->_form;
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'grading_buttonar', '', [' '], false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }

    /**
     * Add submission content section to the form.
     *
     * Displays the student's submission content in a read-only section.
     *
     * @return void
     */
    public function add_submission_content() {
        $mform = $this->_form;
        $mform->addElement('header', 'Submission', get_string('submission', 'videoassessment'));
        $mform->addElement('static', '', '', $this->_customdata->submission_content);
    }

    /**
     * Set form data with proper text formatting defaults.
     *
     * Ensures text fields have proper format defaults before setting data.
     *
     * @param \stdClass $data Data object to set
     * @return bool Success status from parent method
     */
    public function set_data($data) {
        if (!isset($data->text)) {
            $data->text = '';
        }
        if (!isset($data->format)) {
            $data->textformat = FORMAT_HTML;
        } else {
            $data->textformat = $data->format;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null;
        }
        return parent::set_data($data);
    }

    /**
     * Get form data with advanced grading processing.
     *
     * Processes advanced grading instances to extract grades and
     * returns the complete form data object.
     *
     * @param string|null $gradertype Optional grader type override
     * @return \stdClass|null Form data object or null if cancelled
     */
    public function get_data($gradertype = null) {
        $data = parent::get_data();

        if (!$data) {
            return $data;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null; // TODO: this is wrong, itemid MUST be known when saving files!! (skodak)
        }

        if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
            $data->advancedgrading = null; // XXX
        }

        $gradinginstance = $this->use_advanced_grading();
        // Use gradertype from form data (submitted hidden field) first, then customdata, then parameter.
        // This ensures we use the actual gradertype that was submitted with the form.
        $formgradertype = null;
        if (isset($data->gradertype) && !empty($data->gradertype)) {
            $formgradertype = $data->gradertype;
        } else if (isset($this->_customdata->gradertype) && !empty($this->_customdata->gradertype)) {
            $formgradertype = $this->_customdata->gradertype;
        } else if (!empty($gradertype)) {
            $formgradertype = $gradertype;
        }

        // If we still don't have a gradertype, determine it from the user.
        if (empty($formgradertype)) {
            $formgradertype = $this->_customdata->va->get_grader_type($data->userid);
        }

        // Use the grading instances that were already set up in the form definition.
        $gradinginstances = $this->use_advanced_grading();

        // Only process timings that actually exist in the form (fix for first assessment grade issue).
        foreach ($this->_customdata->va->timings as $timing) {
            if (!empty($gradinginstances) && is_object($gradinginstances) && !empty($gradinginstances->$timing)) {
                $gradingarea = $timing . $this->_customdata->va->get_grader_type($data->userid, $gradertype);
                $data->{'xgrade' . $timing} = $gradinginstances->$timing->submit_and_get_grade(
                    $data->{'advancedgrading' . $timing},
                    $this->_customdata->va->get_grade_item($gradingarea, $data->userid)
                );
            }
        }

        return $data;
    }

    /**
     * Get the current grade for a specific timing.
     *
     * Retrieves the existing grade from the database for the current user
     * and specified timing.
     *
     * @param string $timing Timing key ('before' or 'after')
     * @return float Current grade value or -1 if not found
     */
    protected function get_current_grade($timing) {
        global $DB, $USER;

        if (
            $gradeitem = $DB->get_record(
                'videoassessment_grade_items',
                [
                        'videoassessment' => $this->_customdata->videoassessment->id,
                        'submission' => $this->_customdata->submission->id,
                        'type' => $timing . $this->_customdata->va->get_grader_type($this->_customdata->submission),
                        'userid' => $USER->id,
                ]
            )
        ) {
            if ($grade = $DB->get_record('videoassessment_grades', ['gradeitem' => $gradeitem->id])) {
                return $grade->grade;
            }
        }
        return -1;
    }
}
