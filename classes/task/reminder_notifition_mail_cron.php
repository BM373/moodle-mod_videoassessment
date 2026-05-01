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

namespace mod_videoassessment\task;

use core\check\result;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for sending reminder notification emails.
 *
 * This task automatically sends reminder emails to students based on
 * configured triggers for video uploads, assessments, and due dates.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder_notifition_mail_cron extends \core\task\scheduled_task {

    /**
     * Get the human-readable name of the scheduled task.
     *
     * Returns the localized name of this reminder notification task
     * for display in the admin interface.
     *
     * @return string Localized task name
     */
    public function get_name() {
        return get_string('reminder_notifition_mail_cron', 'mod_videoassessment');
    }

    /**
     * Execute the reminder notification task.
     *
     * Processes all video assessments with reminder notifications enabled,
     * checks trigger conditions, and sends emails to students based on
     * configured timing and completion criteria.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $videoassessments = $DB->get_records("videoassessment", array("remindernotification" => 1));
        $modlue = $DB->get_record("modules", array('name' => "videoassessment"));
        foreach ($videoassessments as $videoassessment) {
            $cm = $DB->get_record("course_modules", array(
                "course" => $videoassessment->course,
                "instance" => $videoassessment->id,
                "module" => $modlue->id,
            ));
            $context = \context_module::instance($cm->id);
            $students = get_enrolled_users($context, 'mod/videoassessment:submit', null);
            $teachers = get_enrolled_users($context, 'mod/videoassessment:addinstance', null);
            foreach ($students as $student) {
                if ($this->check_trigger($videoassessment, $student)) {
                    $videoassessmentcompletionexpected = $cm->completionexpected;
                    mtrace('student ' . $student->username . ' mail send start...');
                    if ($videoassessment->isbeforeduedate == 1
                        && 0 <= ($videoassessmentcompletionexpected - (($videoassessment->beforeduedate * 24 * 60 * 60 * 1000) + time()))
                        && ($videoassessmentcompletionexpected - (($videoassessment->beforeduedate * 24 * 60 * 60 * 1000) + time())) < 60000) {

                        $this->send_mail($videoassessment, $student, current($teachers));

                    } else if ($videoassessment->isonduedate == 1
                        && 0 <= ($videoassessmentcompletionexpected - time())
                        && ($videoassessmentcompletionexpected - time()) < 60000) {

                        $this->send_mail($videoassessment, $student, current($teachers));

                    } else if ($videoassessment->isafterduedate == 1
                        && 0 <= ($videoassessment->nextsendmaildate - time())
                        && ($videoassessment->nextsendmaildate - time()) < 60000) {

                        $this->send_mail($videoassessment, $student, current($teachers));

                    }
                    mtrace('student ' . $student->username . ' has been sent');
                }

            }
            if ($videoassessment->isonduedate == 1 && 0 <= ($videoassessmentcompletionexpected - time()) && ($videoassessmentcompletionexpected - time()) < 60000) {
                $videoassessment->nextsendmaildate = time() + ($videoassessment->afterduedate * 24 * 60 * 60 * 1000);
            } else if ($videoassessment->isafterduedate == 1 && 0 <= ($videoassessment->nextsendmaildate - time()) && ($videoassessment->nextsendmaildate - time()) < 60000) {
                $videoassessment->nextsendmaildate = time() + ($videoassessment->afterduedate * 24 * 60 * 60 * 1000);
            }
            $DB->update_record('videoassessment', $videoassessment);
        }

    }

    /**
     * Check if reminder trigger conditions are met for a student.
     *
     * Evaluates various conditions such as video upload status, self-assessment
     * completion, and peer assessment requirements to determine if a reminder
     * email should be sent to the student.
     *
     * @param \stdClass $videoassessment Video assessment instance data
     * @param \stdClass $student Student user object
     * @return bool True if reminder should be sent, false otherwise
     */
    private function check_trigger($videoassessment, $student) {
        global $DB;
        $isupload = $DB->record_exists(
            'videoassessment_video_assocs',
            array('videoassessment' => $videoassessment->id, 'associationid' => $student->id)
        );

        if ($videoassessment->isnovideouploaded == 1 && !$isupload) {
            return true;
        }

        $isselfassessment = $DB->record_exists(
            'videoassessment_grade_items',
            array('videoassessment' => $videoassessment->id, 'gradeduser' => $student->id, 'grader' => $student->id)
        );
        if ($videoassessment->isnoselfassessment == 1 && !$isselfassessment) {
            return true;
        }

        $selfassessment = $DB->get_record(
            'videoassessment_grade_items',
            array('videoassessment' => $videoassessment->id, 'gradeduser' => $student->id, 'grader' => $student->id)
        );
        if (!$isselfassessment) {
            return false;
        } else {
            $assessment = $DB->get_record(
                'videoassessment_grades',
                array('videoassessment' => $videoassessment->id, 'gradeitem' => $selfassessment->id)
            );
            $array = explode(" ", $assessment->submissioncomment);
            if (count($array) < 20 && $videoassessment->isnoselfassessmentwithcomments == 1) {
                return true;
            }
        }
        $ispeerassessment = $DB->record_exists(
            'videoassessment_grade_items',
            array('videoassessment' => $videoassessment->id, 'gradeduser' => $student->id, 'type' => "beforepeer")
        );
        if ($videoassessment->isnopeerassessment == 1 && !$ispeerassessment) {
            return true;
        }

    }

    /**
     * Send reminder email to student.
     *
     * Composes and sends a reminder email using the configured template
     * with placeholder substitution for student and teacher information.
     * Supports both regular email and mobile quickmail integration.
     *
     * @param \stdClass $videoassessment Video assessment instance data
     * @param \stdClass $user Student user object
     * @param \stdClass $teacher Teacher user object
     * @return void
     */
    private function send_mail($videoassessment, $user, $teacher) {
        global $DB;
        $mailtemplate = $videoassessment->remindernotificationtemplate;
        $templatearray = array(
            "[[student name]]" => $user->firstname . ' ' . $user->lastname,
            "[[teacher's emailaddress]]" => $teacher->email,
            "[[teacher name]]" => $teacher->firstname . ' ' . $teacher->lastname,
        );

        foreach ($templatearray as $item => $template) {
            $mailtemplate = str_replace($item, $template, $mailtemplate);
        }
        if ($videoassessment->isregisteredemail == 1) {
            $result = email_to_user($user, $teacher, "", $mailtemplate);
        }
        if ($videoassessment->ismobilequickmail == 1) {
            // NOTE: The Quickmail JPN block is optional.
            // Only attempt to use its user table if the quickmailjpn plugin is installed
            // to avoid errors when the block is missing.
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('block_quickmailjpn_users')) {
                $quickmail = $DB->get_record('block_quickmailjpn_users', array('userid' => $user->id));
                if (!empty($quickmail)) {
                    $mobileuser = $user;
                    $mobileuser->email = $quickmail->mobileemail;
                    $result = email_to_user($mobileuser, $teacher, "", $mailtemplate);
                }
            }
        }
    }
}
