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
 * Backup structure step for the Video Assessment activity.
 *
 * Defines the nested elements and data sources for activity backup.
 *
 * @package   mod_videoassessment
 * @copyright 2024 Don Hinkleman (hinkelman@mac.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_videoassessment_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure for this activity.
     *
     * Creates the root and child elements and maps their data sources
     * and file annotations depending on userinfo setting. Every column of
     * the activity table is listed, so a restored activity keeps all its
     * settings; restore fills columns missing from older backups with
     * their defaults.
     *
     * @return backup_nested_element Root element prepared for backup
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $va = new backup_nested_element('videoassessment', ['id'], [
                'name', 'intro', 'introformat', 'maxbytes', 'timedue', 'timeavailable', 'training',
                'trainingvideo', 'trainingvideoid', 'trainingdesc', 'accepteddifference', 'grade',
                'timemodified', 'ratingteacher', 'ratingself', 'ratingpeer', 'ratingclass', 'class',
                'usedpeers', 'beforelabel', 'afterlabel', 'delayedteachergrade', 'allowstudentupload',
                'allowstudentpeerselection', 'sort', 'sortorder', 'isregisteredemail', 'ismobilequickmail',
                'teachercommentnotification', 'isfirstassessmentbyteacher', 'isadditionalassessment',
                'teachernotificationtemplate', 'peercommentnotification', 'isfirstassessmentbystudent',
                'peertnotificationtemplate', 'remindernotification', 'isbeforeduedate', 'isonduedate',
                'isafterduedate', 'beforeduedate', 'afterduedate', 'isnovideouploaded', 'isnoselcfassessment',
                'isnoselfassessmentwithcomments', 'isnopeerassessment', 'remindernotificationtemplate',
                'videonotification', 'isfirstupload', 'iswheneverupload', 'videonotificationtemplate',
                'nextsendmaildate', 'fairnessbonus', 'bonuspercentage', 'bonusscale1', 'bonus1', 'bonusscale2',
                'bonus2', 'bonusscale3', 'bonus3', 'bonusscale4', 'bonus4', 'bonusscale5', 'bonus5',
                'bonusscale6', 'bonus6', 'selffairnessbonus', 'selfbonuspercentage', 'selfbonusscale1',
                'selfbonus1', 'selfbonusscale2', 'selfbonus2', 'selfbonusscale3', 'selfbonus3',
                'selfbonusscale4', 'selfbonus4', 'selfbonusscale5', 'selfbonus5', 'selfbonusscale6',
                'selfbonus6', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'allowyoutube',
                'allowvideoupload', 'allowvideorecord', 'autodeletefiles', 'gradepass',
                'gradepass_videoassessment', 'aifeedback', 'aifeedbackinstructions', 'aifeedbackkeywords',
                'aifeedbacklang', 'aitranscribelang',
        ]);
        $va->set_source_table('videoassessment', ['id' => backup::VAR_ACTIVITYID]);
        $va->annotate_files('mod_videoassessment', 'intro', null);

        if ($userinfo) {
            $videos = new backup_nested_element('videos');
            $video = new backup_nested_element('video', ['id'], [
                    'filepath', 'filename', 'thumbnailname', 'tmpname', 'originalname', 'timecreated',
                    'timemodified']);

            $videoassocs = new backup_nested_element('video_assocs');
            $videoassoc = new backup_nested_element('video_assoc', ['id'], [
                    'videoid', 'associationtype', 'timing', 'associationid', 'timemodified']);

            $peers = new backup_nested_element('peers');
            $peer = new backup_nested_element('peer', ['id'], ['userid', 'peerid']);

            $gradeitems = new backup_nested_element('grade_items');
            $gradeitem = new backup_nested_element('grade_item', ['id'], [
                    'gradeduser', 'type', 'grader', 'usedbypeermarking']);

            $grades = new backup_nested_element('grades');
            $grade = new backup_nested_element('grade', ['id'], [
                    'gradeitem', 'timemarked', 'grade', 'submissioncomment', 'mailed', 'isnotifystudent']);

            $aggregations = new backup_nested_element('aggregations');
            $aggregation = new backup_nested_element('aggregation', ['id'], [
                    'userid', 'timing', 'timemodified', 'grade', 'gradebefore', 'gradeafter',
                    'gradebeforeteacher', 'gradebeforeself', 'gradebeforepeer', 'gradebeforeclass',
                    'gradebeforetraining', 'passtraining', 'gradeafterteacher', 'gradeafterself',
                    'gradeafterpeer']);

            $reportviews = new backup_nested_element('report_views');
            $reportview = new backup_nested_element('report_view', ['id'], ['userid', 'timeviewed']);

            $aifbattempts = new backup_nested_element('aifb_attempts');
            $aifbattempt = new backup_nested_element('aifb_attempt', ['id'], [
                    'userid', 'attempt', 'status', 'duration', 'transcript', 'feedback', 'errormessage',
                    'timecreated', 'timeprocessed', 'timeviewed', 'timemodified']);

            // Restore relies on this order: videos before their associations, grade items before grades.
            $va->add_child($videos);
            $va->add_child($videoassocs);
            $va->add_child($peers);
            $va->add_child($gradeitems);
            $va->add_child($grades);
            $va->add_child($aggregations);
            $va->add_child($reportviews);
            $va->add_child($aifbattempts);

            $videos->add_child($video);
            $videoassocs->add_child($videoassoc);
            $peers->add_child($peer);
            $gradeitems->add_child($gradeitem);
            $grades->add_child($grade);
            $aggregations->add_child($aggregation);
            $reportviews->add_child($reportview);
            $aifbattempts->add_child($aifbattempt);

            $video->set_source_table('videoassessment_videos', ['videoassessment' => backup::VAR_PARENTID]);
            $videoassoc->set_source_table('videoassessment_video_assocs', ['videoassessment' => backup::VAR_PARENTID]);
            $peer->set_source_table('videoassessment_peers', ['videoassessment' => backup::VAR_PARENTID]);
            $gradeitem->set_source_table('videoassessment_grade_items', ['videoassessment' => backup::VAR_PARENTID]);
            $grade->set_source_table('videoassessment_grades', ['videoassessment' => backup::VAR_PARENTID]);
            $aggregation->set_source_table('videoassessment_aggregation', ['videoassessment' => backup::VAR_PARENTID]);
            $reportview->set_source_table('videoassessment_report_views', ['videoassessment' => backup::VAR_PARENTID]);
            $aifbattempt->set_source_table('videoassessment_aifb_attempts', ['videoassessment' => backup::VAR_PARENTID]);

            // Learners and assessors who have left the course still have to be in the backup.
            $videoassoc->annotate_ids('user', 'associationid');
            $peer->annotate_ids('user', 'userid');
            $peer->annotate_ids('user', 'peerid');
            $gradeitem->annotate_ids('user', 'gradeduser');
            $gradeitem->annotate_ids('user', 'grader');
            $aggregation->annotate_ids('user', 'userid');
            $reportview->annotate_ids('user', 'userid');
            $aifbattempt->annotate_ids('user', 'userid');

            $va->annotate_files('mod_videoassessment', 'video', null);
            $grade->annotate_files('mod_videoassessment', 'submissioncomment', 'id');
            $aifbattempt->annotate_files('mod_videoassessment', 'aifbaudio', 'id');
        }

        return $this->prepare_activity_structure($va);
    }
}
