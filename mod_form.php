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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use videoassess\va;

use core_grades\component_gradeitems;

/**
 * @see moodleform_mod
 */
class mod_videoassessment_mod_form extends moodleform_mod
{

    const MAX_USED_PEERS_LIMIT = 3;
    const DEFAULT_USED_PEERS = 1;

    protected $_videoassessmentinstance = null;

    public function definition()
    {
        global $CFG, $DB, $PAGE;
        $cm = $PAGE->cm;

        $mform = $this->_form;
        $mform->addElement('hidden', 'quickSetupFormUrl', $CFG->wwwroot . '/mod/videoassessment/modedit.php');
        $mform->setType('quickSetupFormUrl', PARAM_RAW);
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('videoassessmentname', 'videoassessment'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(false, get_string('description', 'videoassessment'));

        $mform->addElement('selectyesno', 'allowstudentupload', get_string('allowstudentupload', 'videoassessment'));
        $mform->setDefault('allowstudentupload', 1);
        $mform->addHelpButton('allowstudentupload', 'allowstudentupload', 'videoassessment');

        $this->addQuickSetupElement();

        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', false);

        $name = get_string('allowsubmissionsfromdate', 'assign');
        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'assign');

        $name = get_string('duedate', 'assign');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional' => true));
        $mform->addHelpButton('duedate', 'duedate', 'assign');

        $name = get_string('cutoffdate', 'assign');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional' => true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'assign');

        $name = get_string('gradingduedate', 'assign');
        $mform->addElement('date_time_selector', 'gradingduedate', $name, array('optional' => true));
        $mform->addHelpButton('gradingduedate', 'gradingduedate', 'assign');

        /**
         * @author Le Xuan Anh Version2
         */
        $this->manageVideo();


        $this->addNotifications();

        $this->standard_grading_coursemodule_elements_to_grading('grading');
        //---

        $mform->addElement('radio', 'class', get_string('classgrading', 'videoassessment'), get_string('open', 'videoassessment'), 1);
        $mform->addHelpButton('class', 'classgrading', 'videoassessment');
        $mform->addElement('radio', 'class', null, get_string('close', 'videoassessment'), 0);
        $mform->setType('class', PARAM_INT);
        $mform->setDefault('class', 0);

        /* MinhTB VERSION 2 07-03-2016 */
//        foreach (array('teacher', 'self', 'peer', 'class', 'training') as $gradingtype) {
//            if (empty($this->current->{'advancedgradingmethod_before' . $gradingtype})) {
//                $this->current->{'advancedgradingmethod_before' . $gradingtype} = 'rubric';
//            }
//        }
        /* END MinhTB VERSION 2 07-03-2016 */

        $mform->addElement('select', 'fairnessbonus', get_string('fairnessbonus', 'videoassessment'), array(
            '0' => get_string('no', 'videoassessment'),
            '1' => get_string('yes', 'videoassessment')
        ));
        $mform->addHelpButton('fairnessbonus', 'fairnessbonus', 'videoassessment');
        $bonuspercentage = array();
        for ($i = 0; $i <= 100; $i++) {
            $bonuspercentage[$i] = $i . '%';
        }

        $mform->addElement('select', 'bonuspercentage', 'Bonus Percentage<br>(On top of total)', $bonuspercentage);
        $mform->setDefault('bonuspercentage', 10);
        for ($i = 1; $i <= 6; $i++) {
            $bonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">within</span>');
            $bonusscoregroup[$i][] = $mform->createElement('select', 'bonusscale' . $i, "", $bonuspercentage);
            $bonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">of teacher score = </span>');
            $bonusscoregroup[$i][] = $mform->createElement('select', 'bonus' . $i, "", $bonuspercentage);
            $bonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">of Fairness bonus</span>');
            $grouplabel = "";
            switch ($i) {
                case 1:
                    $grouplabel = "Scale";
                    $mform->setDefault('bonusscale' . $i, 5);
                    $mform->setDefault('bonus' . $i, 100);
                    break;
                case 2:
                    $mform->setDefault('bonusscale' . $i, 10);
                    $mform->setDefault('bonus' . $i, 80);
                    break;
                case 3:
                    $mform->setDefault('bonusscale' . $i, 15);
                    $mform->setDefault('bonus' . $i, 60);
                    break;
                case 4:
                    $mform->setDefault('bonusscale' . $i, 20);
                    $mform->setDefault('bonus' . $i, 40);
                    break;
                case 5:
                    $mform->setDefault('bonusscale' . $i, 25);
                    $mform->setDefault('bonus' . $i, 20);
                    break;
                case 6:
                    $mform->setDefault('bonusscale' . $i, 30);
                    $mform->setDefault('bonus' . $i, 0);
                    break;
                default:
                    break;
            }
            $mform->addGroup($bonusscoregroup[$i], "bonusscoregroup" . $i, $grouplabel, array('', ''), false);
        }

        $mform->addElement('select', 'selffairnessbonus', get_string('selffairnessbonus', 'videoassessment'), array(
            '0' => get_string('no', 'videoassessment'),
            '1' => get_string('yes', 'videoassessment')
        ));
        $mform->addHelpButton('selffairnessbonus', 'selffairnessbonus', 'videoassessment');
        $mform->addElement('select', 'selfbonuspercentage', 'Bonus Percentage<br>(On top of total)', $bonuspercentage);
        $mform->setDefault('bonuspercentage', 10);
        for ($i = 1; $i <= 6; $i++) {
            $selfbonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">within</span>');
            $selfbonusscoregroup[$i][] = $mform->createElement('select', 'selfbonusscale' . $i, "", $bonuspercentage);
            $selfbonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">of teacher score = </span>');
            $selfbonusscoregroup[$i][] = $mform->createElement('select', 'selfbonus' . $i, "", $bonuspercentage);
            $selfbonusscoregroup[$i][] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">of Fairness bonus</span>');
            $grouplabel = "";
            switch ($i) {
                case 1:
                    $grouplabel = "Scale";
                    $mform->setDefault('selfbonusscale' . $i, 5);
                    $mform->setDefault('selfbonus' . $i, 100);
                    break;
                case 2:
                    $mform->setDefault('selfbonusscale' . $i, 10);
                    $mform->setDefault('selfbonus' . $i, 80);
                    break;
                case 3:
                    $mform->setDefault('selfbonusscale' . $i, 15);
                    $mform->setDefault('selfbonus' . $i, 60);
                    break;
                case 4:
                    $mform->setDefault('selfbonusscale' . $i, 20);
                    $mform->setDefault('selfbonus' . $i, 40);
                    break;
                case 5:
                    $mform->setDefault('selfbonusscale' . $i, 25);
                    $mform->setDefault('selfbonus' . $i, 20);
                    break;
                case 6:
                    $mform->setDefault('selfbonusscale' . $i, 30);
                    $mform->setDefault('selfselfbonus' . $i, 0);
                    break;
                default:
                    break;
            }
            $mform->addGroup($selfbonusscoregroup[$i], "selfbonusscoregroup" . $i, $grouplabel, array('', ''), false);
        }


        $mform->addElement('header', 'ratings', get_string('ratings', 'videoassessment'));
        $mform->addHelpButton('ratings', 'ratings', 'videoassessment');
        $mform->addElement('static', 'ratingerror');
        for ($i = 100; $i >= 0; $i--) {
            $ratingopts[$i] = $i . '%';
        }
        $mform->addElement('select', 'ratingteacher', get_string('teacher', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingteacher', 100);
        $mform->addHelpButton('ratingteacher', 'ratingteacher', 'videoassessment');
        $mform->addElement('select', 'ratingself', get_string('self', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingself', 0);
        $mform->addHelpButton('ratingself', 'ratingself', 'videoassessment');
        $mform->addElement('select', 'ratingpeer', get_string('peer', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingpeer', 0);
        $mform->addHelpButton('ratingpeer', 'ratingpeer', 'videoassessment');
        $mform->addElement('select', 'ratingclass', get_string('class', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingclass', 0);
        $mform->addHelpButton('ratingclass', 'ratingclass', 'videoassessment');

        $mform->addElement('selectyesno', 'delayedteachergrade', get_string('delayedteachergrade', 'videoassessment'));
        $mform->setDefault('delayedteachergrade', 1);
        $mform->addHelpButton('delayedteachergrade', 'delayedteachergrade', 'videoassessment');


        $students = get_enrolled_users($this->context);
        $maxusedpeers = min(count($students), self::MAX_USED_PEERS_LIMIT);
        $usedpeeropts = range(0, $maxusedpeers);
        $mform->addElement('select', 'usedpeers', get_string('usedpeers', 'videoassessment'), $usedpeeropts);
        $mform->setDefault('usedpeers', 0);
        $mform->addHelpButton('usedpeers', 'usedpeers', 'videoassessment');

        if ($cm) {
            $href = new moodle_url('/mod/videoassessment/view.php', array('id' => $cm->id, 'action' => 'peers'));
            $linktext = get_string('assignpeers', 'videoassessment');
            $mform->addGroup(array(), 'assignpeersgroup', "<a class='' href='$href'>$linktext</a>", null, false);
            $mform->addHelpButton('assignpeersgroup', 'assignpeers', 'videoassessment');
        }


        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function validation($data, $files)
    {
        // Allow plugin videoassessment types to do any extra validation after the form has been submitted
        $errors = parent::validation($data, $files);
        if ($data['isquickSetup'] == 1) {
            if ($data['isselfassesstype'] == 1 || $data['isteacherassesstype'] == 1 || $data['ispeerassesstype'] == 1 || $data['isclassassesstype'] == 1) {
                $quickratingsum = 0;
                $checkboxes = ['selfassess', 'teacherassess', 'peerassess', 'classassess'];
                foreach ($checkboxes as $check) {
                    if ($data['is' . $check . 'type'] == 1) {
                        $quickratingsum += $data[$check];
                    }
                }
                if ($quickratingsum != 100) {
                    $errors['assesstypegroup'] = get_string('settotalratingtoahundredpercent', 'videoassessment');
                }
            }
            if ($data['gradingsimpledirect'] > 100) {
                $errors['simpledirectgroup'] = 'The grade to pass can not be greater than the maximum possible grade 100';
            }
        } else {
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
        }
        return $errors;
    }

    /**
     * @author Le Xuan Anh Version2
     */
    public function standard_grading_coursemodule_elements_to_grading(string $itemname)
    {
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

            //if supports grades and grades arent being handled via ratings
            if (!$this->_features->rating) {
                $mform->addElement('modgrade', 'grade', get_string('modgrade', 'videoassessment'));
                $mform->addHelpButton('grade', 'modgrade', 'videoassessment');
                $mform->setDefault('grade', $CFG->gradepointdefault);
            }

            if ($this->_features->advancedgrading
                and !empty($this->current->_advancedgradingdata['methods'])
                and !empty($this->current->_advancedgradingdata['areas'])) {

                if (count($this->current->_advancedgradingdata['areas']) == 1) {
                    // if there is just one gradable area (most cases), display just the selector
                    // without its name to make UI simplier
                    $areadata = reset($this->current->_advancedgradingdata['areas']);
                    $areaname = key($this->current->_advancedgradingdata['areas']);
                    $mform->addElement('select', 'advancedgradingmethod_' . $areaname, 'Grading Methods', $this->current->_advancedgradingdata['methods']);
                    $mform->addHelpButton('advancedgradingmethod_' . $areaname, 'gradingmethod', 'core_grading');
                } else {
                    // the module defines multiple gradable areas, display a selector
                    // for each of them together with a name of the area
                    $areasgroup = array();
                    foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                        $areasgroup[] = $mform->createElement('select', 'advancedgradingmethod_' . $areaname, $areadata['title'], $this->current->_advancedgradingdata['methods']);
                        $areasgroup[] = $mform->createElement('static', 'advancedgradingareaname_' . $areaname, '', $areadata['title']);
                        $mform->setDefault('advancedgradingmethod_' . $areaname, $this->current->{'advancedgradingmethod_'.$areaname});
                    }
                    $mform->addGroup($areasgroup, 'advancedgradingmethodsgroup', get_string('advancedgradingmethodsgroup', 'videoassessment'), array(' ', '<br />'), false);
                    $mform->addHelpButton('advancedgradingmethodsgroup', 'advancedgradingmethodsgroup', 'videoassessment');
                }
            }
            /* MinhTB VERSION 2 07-03-2016 */
            $mform->addElement('select', 'training', get_string('trainingpretest', 'videoassessment'), array(
                '0' => get_string('no', 'videoassessment'),
                '1' => get_string('yes', 'videoassessment')
            ));
            $mform->setDefault('training', 0);
            $mform->addHelpButton('training', 'trainingpretest', 'videoassessment');
            /* END MinhTB VERSION 2 07-03-2016 */

            $mform->addElement('filemanager', 'trainingvideo',
                get_string('trainingvideo', 'videoassessment'),
                null,
                array(
                    'subdirs' => 0,
                    'maxbytes' => $COURSE->maxbytes,
                    'maxfiles' => 1,
                    'accepted_types' => array('video', 'audio')
                )
            );
            $mform->addElement('hidden', 'trainingvideoid');
            $mform->setType('trainingvideoid', PARAM_INT);
            $mform->addHelpButton('trainingvideo', 'trainingvideo', 'videoassessment');

            $mform->addElement('textarea', 'trainingdesc',
                get_string('trainingdesc', 'videoassessment'),
                array('cols' => 50, 'rows' => 8)
            );
            $mform->setDefault('trainingdesc', get_string('trainingdesctext', 'videoassessment'));
            $mform->addHelpButton('trainingdesc', 'trainingdesc', 'videoassessment');

            for ($i = 100; $i >= 0; $i--) {
                $ratingopts[$i] = $i . '%';
            }
            $mform->addElement('select', 'accepteddifference', get_string('accepteddifference', 'videoassessment'), $ratingopts);
            $mform->setDefault('accepteddifference', 20);
            $mform->addHelpButton('accepteddifference', 'accepteddifference', 'videoassessment');


            if ($this->_features->gradecat) {
                $mform->addElement('select', 'gradecat', 'Grade Category', grade_get_categories_menu($COURSE->id, $this->_outcomesused));
                $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
            }

            // Grade to pass.
            $mform->addElement('text', $gradepassfieldname, get_string('gradepass', 'grades'));
            $mform->addHelpButton($gradepassfieldname, 'gradepass', 'grades');
            $mform->setType($gradepassfieldname, PARAM_RAW);

            $module = array(
                'name' => 'mod_videoassessment',
                'fullpath' => '/mod/videoassessment/mod_form.js',
                'requires' => array('node', 'event'),
                'strings' => array(array('changetraingingwarning', 'mod_videoassessment'))
            );
            $PAGE->requires->js_init_call('M.mod_videoassessment.init_fairness_bonus_change', null, false, $module);
            $PAGE->requires->js_init_call('M.mod_videoassessment.init_quick_setup_peer_change', null, false, $module);
            $PAGE->requires->js_init_call('M.mod_videoassessment.init_training_change', null, false, $module);
        }
    }

    public function manageVideo()
    {
        global $COURSE, $CFG, $DB, $PAGE;

        $cm = $PAGE->cm;

        if (!$cm) {
            return;
        }

        $viewurl = new moodle_url('/mod/videoassessment/view.php', array('id' => $cm->id));
        $context = context_module::instance($cm->id);

        $va = $DB->get_record('videoassessment', array('id' => $cm->instance));
        $course = $DB->get_record('course', array('id' => $va->course));

        require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
        $vaobj = new va($context, $cm, $course);
        $isteacher = $vaobj->is_teacher();

        $mform = &$this->_form;
        $mform->addElement('header', 'managevideos', get_string('managevideos', 'videoassessment'));
        $mform->addHelpButton('managevideos', 'managevideos', 'videoassessment');
        if ($isteacher) {
            if (va::uses_mobile_upload()) {
                $this->add_link_element('takevideo', new moodle_url($viewurl, array('action' => 'upload', 'actionmodel' => 2)), get_string('takevideo', 'videoassessment'));
            } else {
                $this->add_link_element('uploadvideo', new moodle_url($viewurl, array('action' => 'upload', 'actionmodel' => 2)), get_string('uploadvideo', 'videoassessment'));
                $this->add_link_element('videoassessment:bulkupload', new moodle_url('/mod/videoassessment/bulkupload/index.php', array('cmid' => $cm->id)), get_string('videoassessment:bulkupload', 'videoassessment'));
            }
            /* MinhTB VERSION 2 */
            $this->add_link_element('deletevideos', new moodle_url('/mod/videoassessment/deletevideos.php', array('id' => $cm->id)), get_string('deletevideos', 'videoassessment'));
            $this->add_link_element('associate', new moodle_url($viewurl, array('action' => 'videos')), get_string('associate', 'videoassessment'));
            $this->add_link_element('assess', $viewurl, get_string('assess', 'videoassessment'));
            $this->add_link_element('publishvideos', new moodle_url($viewurl, array('action' => 'publish')), get_string('publishvideos', 'videoassessment'));
            $this->add_link_element('assignclass', new moodle_url('/mod/videoassessment/assignclass/index.php', array('id' => $cm->id)), get_string('assignclass', 'videoassessment'));
            $this->add_link_element('duplicaterubric', new moodle_url('/mod/videoassessment/rubric/duplicate.php', array('id' => $cm->id)), get_string('duplicaterubric', 'videoassessment'));
            /* End */
        }
    }

    public function addNotifications()
    {
        global $PAGE;
        $mform = &$this->_form;


        $mform->addElement('header', 'notifications', get_string('notifications', 'videoassessment'));
        $mform->addHelpButton('notifications', 'notifications', 'videoassessment');
        $notificationscarriergroup[] = $mform->createElement('advcheckbox', 'isregisteredemail', "", "registered email");
        $mform->setDefault('isregisteredemail', 0);
        $notificationscarriergroup[] = $mform->createElement('advcheckbox', 'ismobilequickmail', "", "Mobile Quickmail");
        $mform->setDefault('ismobilequickmail', 0);
        $mform->addGroup($notificationscarriergroup, 'notificationcarriergroup', get_string('notificationcarriergroup', 'videoassessment'), array(' ', '<br />'), false);
        $mform->addHelpButton('notificationcarriergroup', 'notificationcarriergroup', 'videoassessment');

        $teachernotificationtemplate = "Dear [[student name]],
Good work! I just checked your presentation video and made some
scores and comments. Here they are:
[[insert assignment name]] [[insert current date]]
Here is a link to this report: [[insert link to student page to view assessment]]
You can redo your presentation on June 7th and get a better grade.
Send an email to me if you have a question [[teacher email address]]
Best regards,
[[teacher name]]";
        $mform->addElement('advcheckbox', 'teachercommentnotification', get_string('teachercommentnotification', 'videoassessment'), "<b>Teacher Comment notification</b><label class='teacher-notification-displaybtn collapsed'></label>");
        $mform->setDefault('teachercommentnotification', 0);
        $mform->addHelpButton('teachercommentnotification', 'teachercommentnotification', 'videoassessment');
        $teachernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>1.When to send notifiction</b></div>');

        $teachernotificationgroup[] = $mform->createElement('advcheckbox', 'isfirstassessmentbyteacher', "", "First assessment by teacher");
        $teachernotificationgroup[] = $mform->createElement('advcheckbox', 'isadditionalassessment', "", "Additional assessment by teacher");
        $teachernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>2.What information to send</b></div>', '');
        $teachernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with">[[student name]]<br/>[[VA assignment name]]<br/>[[current date]]<br/>[[link to view whole assessment report]]->view Report<br/>[[teacher email address]]<br/>[[teacher name]]</div>');
        $teachernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>3.Template text for notification</b></div>');
        $teachernotificationgroup[] = $mform->createElement('textarea', 'teachernotificationtemplate', "", ['rows' => 10, 'cols' => 80]);
        $mform->setDefault('teachernotificationtemplate', $teachernotificationtemplate);
        $mform->addGroup($teachernotificationgroup, 'teachernotificationgroup', "", array(' <br/>', '<br/>'), false);


        $peertnotificationtemplate = "Dear [[student name]],
Good work! One of your classmates just checked your presentation
video and made some scores and comments. Here they are:
[[insert assignment name]] [[insert current date]]
Here is a link to this report: [[insert link to student page to view assessment]]
**your classmates will get a bonus if they score you fairly**
Send an email to me if you have a question [[teacher email address]]
Best regards,
[[teacher name]]";
        $mform->addElement('advcheckbox', 'peercommentnotification', "", "<b>Peer Comment notification</b><label class='peer-notification-displaybtn collapsed'></label>");
        $mform->setDefault('peercommentnotification', 0);
        $peernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>1.When to send notifiction</b></div>');
        $peernotificationgroup[] = $mform->createElement('advcheckbox', 'isfirstassessmentbystudent', "", "First assessment by student");
        $peernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>2.What information to send</b></div>');
        $peernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with">[[student name]]<br/>[[VA assignment name]]<br/>[[current date]]<br/>[[link to view whole assessment report]]->view Report<br/>[[teacher email address]]<br/>[[teacher name]]</div>');
        $peernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>3.Template text for notification</b></div>');
        $peernotificationgroup[] = $mform->createElement('textarea', 'peertnotificationtemplate', "", ['rows' => 10, 'cols' => 80]);
        $mform->setDefault('peertnotificationtemplate', $peertnotificationtemplate);
        $mform->addGroup($peernotificationgroup, 'peernotificationgroup', "", array(' <br/>', '<br/>'), false);


        $remindernotificationtemplate = "Dear [[student name]],
Have you watched and checked your presentation?
Its due date is/was on June x. Here is a link:
[[insert link to self-assessment page]]
Be sure to write at least 3 comments as well as scores.
Send an email to your me if you have a question [[teacher email
address]]. Thanks!
Best regards,
[[teacher name]]";
        $duadate = array("1" => 1, "2" => 2, "3" => 3, "4" => 4, "5" => 5);
        $mform->addElement('advcheckbox', 'remindernotification', "", "<b>Reminder Notification</b><label class='reminder-notification-displaybtn collapsed'></label>");
        $mform->setDefault('remindernotification', 0);
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>1.When to send notification</b></div>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isbeforeduedate', "", "before due date");
        $remindernotificationgroup[] = $mform->createElement('select', 'beforeduedate', "days before", $duadate);
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">days before</span>');
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isonduedate', "", "on due date");
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isafterduedate', "", "after due date, every", array('group' => 1));
        $remindernotificationgroup[] = $mform->createElement('select', 'afterduedate', "", $duadate);
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">days</span>');
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>2.What information to send</b></div>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isnovideouploaded', "", "on video uploaded");
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isnoselfassessment', "", "on self assessment");
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isnoselfassessmentwithcomments', "", "on self assessment with 20 words of comments");
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement('advcheckbox', 'isnopeerassessment', "", "on peer assessment");
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>3.Template text for notification</b></div>');
        $remindernotificationgroup[] = $mform->createElement('textarea', 'remindernotificationtemplate', "", ['rows' => 10, 'cols' => 80]);
        $mform->setDefault('remindernotificationtemplate', $remindernotificationtemplate);
        $mform->addGroup($remindernotificationgroup, 'remindernotificationgroup', "", array('', ' '), false);


        $videonotificationtemplate = "Dear [[teacher name]],
[[student name]] has just uploaded a video file.
To view it and assess it, please go to: [[insert link to self-assessment page]]
Best regards,
https://moodle.sgu.ac.jp";
        $mform->addElement('advcheckbox', 'videonotification', "", "<b>Video upload/reupload notification</b><label class='video-notification-displaybtn collapsed'></label>");
        $mform->setDefault('videonotification', 0);
        $videonotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>1.When to send notifiction</b></div>');
        $videonotificationgroup[] = $mform->createElement('advcheckbox', 'isfirstupload', "", "when the student uploads a video  for first time");
        $videonotificationgroup[] = $mform->createElement('advcheckbox', 'iswheneverupload', "", "whenever a student re-uploads a video");
        $videonotificationgroup[] = $mform->createElement('static', '', null, '<div class="max-with"><b>2.Template text for notification</b></div>');
        $videonotificationgroup[] = $mform->createElement('textarea', 'videonotificationtemplate', "", ['rows' => 10, 'cols' => 80]);
        $mform->setDefault('videonotificationtemplate', $videonotificationtemplate);
        $mform->addGroup($videonotificationgroup, 'videonotificationgroup', "", array(' <br/>', '<br/>'), false);

        $module = array(
            'name' => 'mod_videoassessment',
            'fullpath' => '/mod/videoassessment/mod_form.js',
        );
        $PAGE->requires->js_init_call('M.mod_videoassessment.init_notification_form_change', null, false, $module);
        $PAGE->requires->css(new \moodle_url('/mod/videoassessment/mod_form.css'));
    }

    private function addQuickSetupElement()
    {
        global $PAGE;
        $cm = $PAGE->cm;

        $mform = &$this->_form;
        for ($i = 100; $i >= 0; $i--) {
            $ratingopts[$i] = $i . '%';
        }
        $numberofpeers = array();
        for ($i = 0; $i <= 5; $i++) {
            $numberofpeers[$i] = $i;
        }
        $mform->addElement('header', 'quickSetup', "Quick Setup");
        $mform->addElement('hidden', 'isquickSetup', 0);
        $mform->setType('isquickSetup', PARAM_RAW);
        $mform->addHelpButton('quickSetup', 'quickSetup', 'videoassessment');

        $assesstypegroup[] = $mform->createElement('advcheckbox', 'isselfassesstype', "", "Self");
        $mform->setDefault('isselfassesstype', 0);
        $assesstypegroup[] = $mform->createElement('select', 'selfassess', "", $ratingopts);
        $mform->setDefault('selfassess', 0);

        $assesstypegroup[] = $mform->createElement('advcheckbox', 'ispeerassesstype', "", "Peer");
        $mform->setDefault('ispeerassesstype', 0);
        $assesstypegroup[] = $mform->createElement('select', 'peerassess', "", $ratingopts);
        $mform->setDefault('peerassess', 0);

        $assesstypegroup[] = $mform->createElement('advcheckbox', 'isteacherassesstype', "", "Teacher");
        $mform->setDefault('isteacherassesstype', 0);
        $assesstypegroup[] = $mform->createElement('select', 'teacherassess', "", $ratingopts);
        $mform->setDefault('teacherassess', 100);

        $assesstypegroup[] = $mform->createElement('advcheckbox', 'isclassassesstype', "", "Class");
        $mform->setDefault('isclassassesstype', 0);
        $assesstypegroup[] = $mform->createElement('select', 'classassess', "", $ratingopts);
        $mform->setDefault('classassess', 0);

        $mform->addGroup($assesstypegroup, 'assesstypegroup', "Types of assessment", array(''), false);


        $students = get_enrolled_users($this->context);
        $maxusedpeers = min(count($students), self::MAX_USED_PEERS_LIMIT);
        $mform->addElement('select', 'numberofpeers', 'Number of peers', $numberofpeers);
        $mform->setDefault('numberofpeers', 0);
        $mform->addHelpButton('numberofpeers', 'usedpeers', 'videoassessment');

//        if ($cm) {
//            $href = new moodle_url('/mod/videoassessment/view.php', array('id' => $cm->id, 'action' => 'peers'));
//            $contentbuttonurl = $href->out();
//            $contentbuttonlabel = '<a class="btn btn-primary" target="_blank" href="' . $contentbuttonurl . '">Go to Assign Peers</a>';
//            $assignpeerbtsgroup[] = $mform->createElement('html', $contentbuttonlabel);
//            $mform->addGroup($assignpeerbtsgroup, 'assignpeerbtsgroup', "Assign Peers", array(' ', '<br />'), false);
//            $mform->addHelpButton('assignpeerbtsgroup', 'assignpeers', 'videoassessment');
//        }

        $simpledirectgroup[] = $mform->createElement('text', 'gradingsimpledirect', "", array('size' =>5));
        $mform->setType('gradingsimpledirect', PARAM_RAW);
        $mform->setDefault('gradingsimpledirect', 100);
        $simpledirectgroup[] = $mform->createElement('static', '', null, '<span class="form-check-inline  fitem" style="width: auto;">maximum points</span>');

        $mform->addGroup($simpledirectgroup, 'simpledirectgroup', "Grading - simple direct", array(' ', '<br />'), false);

        $mform->addElement('button', 'quickSetupButton', 'Submit');
        $PAGE->requires->jquery();
        //$PAGE->requires->js('/mod/videoassessment/grademanage.js', true);
		$PAGE->requires->js_call_amd('mod_videoassessment/grademanage', 'init_grademanage', array());
    }

    private function add_link_element($linkname, $href, $linktext)
    {
        $mform = &$this->_form;
        $mform->addGroup(array(), $linkname . 'group', "<a class='managelink' href='$href'>$linktext</a>", null, false);
        $mform->addHelpButton($linkname . 'group', $linkname, 'videoassessment');
    }
}
