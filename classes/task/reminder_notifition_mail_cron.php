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
 * Update Overdue Attempts Task
 *
 * @package    mod_videoassessment
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videoassessment\task;

use core\check\result;

defined('MOODLE_INTERNAL') || die();
/**
 * Update Overdue Attempts Task
 *
 * @package    mod_videoassessment
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class reminder_notifition_mail_cron extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('reminder_notifition_mail_cron', 'mod_videoassessment');
    }

    /**
     *
     * Close off any overdue attempts.
     */
    public function execute() {
        global $DB;

        $videoassessments = $DB->get_records("videoassessment",array("remindernotification"=>1));
        $modlue = $DB->get_record("modules",array('name'=>"videoassessment"));
        foreach ($videoassessments as $videoassessment) {
            $cm = $DB->get_record("course_modules", array("course" => $videoassessment->course,
                "instance" => $videoassessment->id,
                "module" => $modlue->id));
            $context = \context_module::instance($cm->id);
            $students = get_enrolled_users($context, 'mod/videoassessment:submit', NULL);
            $teachers = get_enrolled_users($context, 'mod/videoassessment:addinstance', NULL);
            foreach ($students as $student) {
                if ($this->checkTrigger($videoassessment, $student)) {
                    $videoassessmentcompletionexpected = $cm->completionexpected;
                    mtrace('student ' . $student->username . ' mail send start...');
                    if ($videoassessment->isbeforeduedate == 1 && 0 <= ($videoassessmentcompletionexpected - (($videoassessment->beforeduedate * 24 * 60 * 60 * 1000) + time())) && ($videoassessmentcompletionexpected - (($videoassessment->beforeduedate * 24 * 60 * 60 * 1000) + time())) < 60000) {
                        $this->send_mail($videoassessment, $student, current($teachers));
                    } elseif ($videoassessment->isonduedate == 1 && 0 <= ($videoassessmentcompletionexpected - time()) && ($videoassessmentcompletionexpected - time()) < 60000) {
                        $this->send_mail($videoassessment, $student, current($teachers));
                    } elseif ($videoassessment->isafterduedate == 1 && 0 <= ($videoassessment->nextsendmaildate - time()) && ($videoassessment->nextsendmaildate - time()) < 60000) {
                        $this->send_mail($videoassessment, $student, current($teachers));
                    }
                    mtrace('student ' . $student->username . ' has been sent');
                }

            }
            if ($videoassessment->isonduedate == 1 && 0 <= ($videoassessmentcompletionexpected - time()) && ($videoassessmentcompletionexpected - time()) < 60000) {
                $videoassessment->nextsendmaildate = time() + ($videoassessment->afterduedate * 24 * 60 * 60 * 1000);
            } elseif ($videoassessment->isafterduedate == 1 && 0 <= ($videoassessment->nextsendmaildate - time()) && ($videoassessment->nextsendmaildate - time()) < 60000) {
                $videoassessment->nextsendmaildate = time() + ($videoassessment->afterduedate * 24 * 60 * 60 * 1000);
            }
            $DB->update_record('videoassessment', $videoassessment);
        }

    }
    private function checkTrigger($videoassessment,$student){
        global $DB;
        $isupload = $DB->record_exists('videoassessment_video_assocs',
            array('videoassessment'=>$videoassessment->id, 'associationid'=>$student->id));

        if($videoassessment->isnovideouploaded == 1 && !$isupload){
            return true;
        }

        $isselfassessment = $DB->record_exists('videoassessment_grade_items',
            array('videoassessment'=>$videoassessment->id,'gradeduser'=>$student->id, 'grader'=>$student->id));
        if($videoassessment->isnoselfassessment == 1 && !$isselfassessment){
            return true;
        }

        $selfassessment = $DB->get_record('videoassessment_grade_items',
            array('videoassessment'=>$videoassessment->id,'gradeduser'=>$student->id, 'grader'=>$student->id));
        if(!$isselfassessment){
            return false;
        }else{
            $assessment = $DB->get_record('videoassessment_grades',
                array('videoassessment'=>$videoassessment->id,'gradeitem'=>$selfassessment->id));
            $array = explode(" ", $assessment->submissioncomment);
            if(count($array) < 20 && $videoassessment->isnoselfassessmentwithcomments == 1 ){
                return true;
            }
        }
        $ispeerassessment = $DB->record_exists('videoassessment_grade_items',
            array('videoassessment'=>$videoassessment->id,'gradeduser'=>$student->id, 'type'=>"beforepeer"));
        if($videoassessment->isnopeerassessment == 1 && !$ispeerassessment){
            return true;
        }

    }
    private function send_mail($videoassessment,$user,$teacher){
        global $DB;
        $mailTemplate = $videoassessment->remindernotificationtemplate;
        $templateArray = array("[[student name]]"=>$user->firstname.' '.$user->lastname,
            "[[teacher’s emailaddress]]"=>$teacher->email,
            "[[teacher name]]"=>$teacher->firstname.' '.$teacher->lastname);

        foreach ($templateArray as $item=>$template) {
            $mailTemplate = str_replace($item,$template,$mailTemplate);
        }
        if($videoassessment->isregisteredemail == 1){
            $result = email_to_user($user,$teacher,"",$mailTemplate);
        }
        if($videoassessment->ismobilequickmail == 1){
            $quickmail = $DB->get_record('block_quickmailjpn_users', array('userid' => $user->id));
            if(!empty($quickmail)){
                $mobileuser = $user;
                $mobileuser->email = $quickmail->mobileemail;
                $result = email_to_user($mobileuser,$teacher,"",$mailTemplate);
            }
        }
    }
}
