<?php
namespace videoassess\form;
use \videoassess\va;

defined('MOODLE_INTERNAL') || die();

class assess extends \moodleform {
    /**
     * @var stdClass stores the advaned grading instance (if used in grading)
     */
    private $advancegradinginstance;

    function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $data = $this->_customdata;

        if (isset($data->advancedgradinginstance)) {
            $this->use_advanced_grading($data->advancedgradinginstance);
        }

        $formattr = $mform->getAttributes();
        $formattr['id'] = 'submitform';
        $mform->setAttributes($formattr);
        // hidden params
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
     * Gets or sets the instance for advanced grading
     *
     * @param array
     */
    public function use_advanced_grading($gradinginstance = false) {
        if ($gradinginstance !== false) {
            $this->advancegradinginstance = $gradinginstance;
        }
        return $this->advancegradinginstance;
    }

    function add_grades_section() {
        global $CFG, $DB, $USER, $OUTPUT;

        $mform = $this->_form;
        $data = $this->_customdata;
        /* @var $va \videoassess\va */
        $va = $data->va;
        $attributes = array();

        $user = $DB->get_record('user', array('id' => optional_param('userid', 0, PARAM_INT)));

        $mform->addElement('header', 'Grades', $user->firstname . ' ' . $user->lastname . $OUTPUT->user_picture($user, array('size' => 100)));

        $grademenu = make_grades_menu($va->va->grade);
        $gradinginstances = $this->use_advanced_grading();

        foreach ($va->timings as $timing) {

            if(property_exists($this->_customdata,'grade'.$timing)){
                $grade = $this->_customdata->{'grade'.$timing};
            }
            if ($gradinginstances) {
                // ルーブリック
                //grade type -rubric
                $mform->addElement('hidden', 'gradecategory' . $timing, 1);
                $mform->setType('gradecategory'.$timing, PARAM_RAW);
                if (!empty($gradinginstances->$timing)) {
                    $gradinginstance = $gradinginstances->$timing;
                    $gradinginstance->get_controller()->set_grade_range($grademenu);
//                 $gradinginstance = $gradinginstance->get_controller()->get_current_instance();
                    $gradingelement = $mform->addElement(
                        'grading', 'advancedgrading' . $timing,
                        $va->str('grade') . ':',
                        array('gradinginstance' => $gradinginstance));
                    if ($data->gradingdisabled) {
                        $gradingelement->freeze();
                    } else {
                        $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
                        $mform->setType('advancedgradinginstanceid', PARAM_INT);
                    }
                } else {
                    // 2012/05/09 ルーブリックが作成されていなければ評定できないようにする
                    $mform->addElement('hidden', 'xgrade' . $timing, -1);
                    $mform->setType('xgrade' . $timing, PARAM_INT);
                    continue;
                }
            }else {
                // Use simple direct grading.
                if ($va->va->grade > 0) {
                    //grade type -simple direct grading【point】
                    $mform->addElement('hidden', 'gradecategory' . $timing, 2);
                    $mform->setType('gradecategory'.$timing, PARAM_RAW);
                    $name = get_string('gradeoutof', 'assign', $va->va->grade);
                    if (!$data->gradingdisabled) {
                        $gradingelement = $mform->addElement('text', 'xgrade'.$timing, $name);
                        $mform->addHelpButton('xgrade'.$timing, 'gradeoutofhelp', 'assign');
                        $mform->setType('xgrade'.$timing, PARAM_RAW);
                        if (isset($grade->grade)) {
                            $mform->setDefault('xgrade'.$timing, $grade->grade);
                        }
                    } else {
                        $strgradelocked = get_string('gradelocked', 'assign');
                        $mform->addElement('static', 'gradedisabled', $name, $strgradelocked);
                        $mform->addHelpButton('gradedisabled', 'gradeoutofhelp', 'assign');
                    }
                } else {
                    //grade type -simple direct grading【scale】
                    $mform->addElement('hidden', 'gradecategory' . $timing, 3);
                    $mform->setType('gradecategory'.$timing, PARAM_RAW);
                    $grademenu = array(-1 => get_string("nograde")) + make_grades_menu($va->va->grade);
                    if (count($grademenu) > 1) {
                        $gradingelement = $mform->addElement('select', 'xgrade'.$timing, get_string('grade') . ':', $grademenu);
                        // The grade is already formatted with format_float so it needs to be converted back to an integer.
                        if (!empty($data->grade)) {
                            $data->grade = (int)unformat_float($data->grade);
                        }

                        $mform->setType('xgrade'.$timing, PARAM_INT);
                        if (isset($grade->grade)) {
                            $mform->setDefault('xgrade'.$timing, $grade->grade);
                        }
                        if ($data->gradingdisabled) {
                            $gradingelement->freeze();
                        }

                    }
                }
            }
            if (!empty($data->enableoutcomes)) {
                foreach($data->grading_info->outcomes as $n=>$outcome) {
                    $options = make_grades_menu(-$outcome->scaleid);
                    if ($outcome->grades[$data->submission->userid]->locked) {
                        $options[0] = get_string('nooutcome', 'grades');
                        $mform->addElement('static', 'outcome_'.$n.'['.$data->userid.']', $outcome->name.':',
                                           $options[$outcome->grades[$data->submission->userid]->grade]);
                    } else {
                        $options[''] = get_string('nooutcome', 'grades');
                        $attributes = array('id' => 'menuoutcome_'.$n );
                        $mform->addElement('select', 'outcome_'.$n.'['.$data->userid.']', $outcome->name.':', $options, $attributes );
                        $mform->setType('outcome_'.$n.'['.$data->userid.']', PARAM_INT);
                        $mform->setDefault('outcome_'.$n.'['.$data->userid.']', $outcome->grades[$data->submission->userid]->grade );
                    }
                }
            }
            $course_context = \context_module::instance($data->cm->id);
            $gradestr = '-';
            if (isset($grade->grade) && $grade->grade > -1) {
                $gradestr = $grade->grade.'%';
            }
            $mform->addElement('static', 'finalgrade'.$timing, va::str('currentgrade').':' ,
                    \html_writer::tag('span', $gradestr, array('class' => 'mark')));
            $mform->setType('finalgrade'.$timing, PARAM_INT);

            $mform->addElement('editor', 'submissioncomment'.$timing,get_string('feedback', 'videoassessment').':',
            		array('cols' => 50, 'rows' => 8), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, 'context' => $course_context, 'subdirs' => true));

            if (isset($grade->submissioncomment)) {
            $mform->setDefault('submissioncomment'.$timing, array('text' => $grade->submissioncomment,
                'format' => FORMAT_HTML));
            }
            if($data->gradertype == "teacher" || $data->gradertype == "peer")
            $mform->addElement('advcheckbox',"isnotifystudent","notify student",array(),array(0,1));
            if (isset($grade->isnotifystudent)) {
                $mform->setDefault("isnotifystudent",$grade->isnotifystudent);
            }else{
                $mform->setDefault('isnotifystudent', 1);
            }

        }
    }
    public function validation($data, $files)
    {
        // Allow plugin videoassessment types to do any extra validation after the form has been submitted
        $errors = parent::validation($data, $files);
        $cdata = $this->_customdata;
        /* @var $va \videoassess\va */
        $va = $cdata->va;
        foreach ($va->timings as $timing) {
            if (!empty($data['xgrade'.$timing]) && $va->va->grade > 0) {
                if (0 > $data['xgrade'.$timing] || $data['xgrade'.$timing] > 100) {
                    $errors['xgrade'.$timing] = 'Enter a number from 0-100. ';
                }
            }
        }

        return $errors;
    }
    /**
     *
     * @param boolean $cancel
     * @param string $submitlabel
     */
    function add_action_buttons($cancel = true, $submitlabel=null) {
        $mform = $this->_form;
        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }

    function add_submission_content() {
        $mform = $this->_form;
        $mform->addElement('header', 'Submission', get_string('submission', 'videoassessment'));
        $mform->addElement('static', '', '' , $this->_customdata->submission_content );
    }

    /**
     *
     * @param \stdClass $data
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

    public function get_data($gradertype = null) {
        $data = parent::get_data();

        if (!$data) {
            return $data;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null; //TODO: this is wrong, itemid MUST be known when saving files!! (skodak)
        }

        if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
            $data->advancedgrading = null;//XXX
        }

        $gradinginstance = $this->use_advanced_grading();
        foreach (array('before', 'after') as $timing) {
            if (!empty($gradinginstance->$timing)) {
                $gradingarea = $timing.$this->_customdata->va->get_grader_type($data->userid, $gradertype);
                $data->{'xgrade'.$timing} = $gradinginstance->$timing->submit_and_get_grade(
                        $data->{'advancedgrading'.$timing},
                        $this->_customdata->va->get_grade_item($gradingarea, $data->userid)
                );
            }
        }

        return $data;
    }


    /**
     *
     * @param string $timing
     * @return float
     */
    protected function get_current_grade($timing) {
        global $DB, $USER;

        if ($gradeitem = $DB->get_record('videoassessment_grade_items',
                array(
                        'videoassessment' => $this->_customdata->videoassessment->id,
                        'submission' => $this->_customdata->submission->id,
                        'type' => $timing . $this->_customdata->va->get_grader_type($this->_customdata->submission),
                        'userid' => $USER->id
                ))) {
            if ($grade = $DB->get_record('videoassessment_grades', array('gradeitem' => $gradeitem->id))) {
                return $grade->grade;
            }
        }
        return -1;
    }
}
