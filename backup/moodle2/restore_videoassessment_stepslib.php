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
 * Restore structure step for the Video Assessment activity.
 *
 * Defines the restore paths and processing methods for activity data.
 *
 * @package   mod_videoassessment
 * @copyright 2024 Don Hinkleman (hinkelman@mac.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videoassessment_activity_structure_step extends restore_activity_structure_step {
    /** @var int Training video id in the backup, mapped once the videos are restored. */
    protected $oldtrainingvideoid = 0;

    /**
     * Define the restore structure for this activity.
     *
     * Creates restore path elements for all activity data including user data
     * if userinfo setting is enabled.
     *
     * @return restore_path_element Prepared activity structure
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $va = new restore_path_element('videoassessment', '/activity/videoassessment');
        $paths[] = $va;

        if ($userinfo) {
            $video = new restore_path_element('videoassessment_video', '/activity/videoassessment/videos/video');
            $paths[] = $video;

            $assoc = new restore_path_element('videoassessment_video_assoc', '/activity/videoassessment/video_assocs/video_assoc');
            $paths[] = $assoc;

            $peer = new restore_path_element('videoassessment_peer', '/activity/videoassessment/peers/peer');
            $paths[] = $peer;

            $gradeitem = new restore_path_element(
                'videoassessment_grade_item',
                '/activity/videoassessment/grade_items/grade_item'
            );
            $paths[] = $gradeitem;

            $grade = new restore_path_element('videoassessment_grade', '/activity/videoassessment/grades/grade');
            $paths[] = $grade;

            $aggregation = new restore_path_element(
                'videoassessment_aggregation',
                '/activity/videoassessment/aggregations/aggregation'
            );
            $paths[] = $aggregation;

            $reportview = new restore_path_element(
                'videoassessment_report_view',
                '/activity/videoassessment/report_views/report_view'
            );
            $paths[] = $reportview;

            $aifbattempt = new restore_path_element(
                'videoassessment_aifb_attempt',
                '/activity/videoassessment/aifb_attempts/aifb_attempt'
            );
            $paths[] = $aifbattempt;
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the main videoassessment activity data.
     *
     * Restores the core activity record and applies the new instance ID.
     * Dates move with the course start date. The training video is a
     * stored video, so it is linked again in {@see self::after_execute()}
     * once the videos (user data) are restored.
     *
     * @param array $data Activity data from backup
     * @return void
     */
    protected function process_videoassessment($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        foreach (['timedue', 'timeavailable', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'nextsendmaildate'] as $field) {
            if (isset($data->$field)) {
                $data->$field = $this->apply_date_offset($data->$field);
            }
        }
        $this->oldtrainingvideoid = (int)($data->trainingvideoid ?? 0);
        $data->trainingvideoid = 0;
        // A draft area id from the settings form of the original site.
        $data->trainingvideo = 0;

        $newitemid = $DB->insert_record('videoassessment', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process video records from backup.
     *
     * Restores video file records and creates ID mappings for references.
     *
     * @param array $data Video data from backup
     * @return void
     */
    protected function process_videoassessment_video($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        // The value 'Youtube' marks an external link. Backups made before 1.3.0-beta2 left the
        // column out, and such a video is taken for an uploaded file.
        $data->tmpname = $data->tmpname ?? '';

        $newitemid = $DB->insert_record('videoassessment_videos', $data);
        $this->set_mapping('videoassessment_video', $oldid, $newitemid);
    }

    /**
     * Process video association records from backup.
     *
     * Restores video-user associations with proper ID mappings.
     *
     * @param array $data Video association data from backup
     * @return void
     */
    protected function process_videoassessment_video_assoc($data) {
        global $DB;

        $data = (object)$data;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->videoid = $this->get_mappingid('videoassessment_video', $data->videoid);
        $data->associationid = $this->get_mappingid('user', $data->associationid);
        if (!$data->videoid || !$data->associationid) {
            return;
        }

        $DB->insert_record('videoassessment_video_assocs', $data);
    }

    /**
     * Process peer relationship records from backup.
     *
     * Restores peer associations between users with proper ID mappings.
     *
     * @param array $data Peer relationship data from backup
     * @return void
     */
    protected function process_videoassessment_peer($data) {
        global $DB;

        $data = (object)$data;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->peerid = $this->get_mappingid('user', $data->peerid);
        if (!$data->userid || !$data->peerid) {
            return;
        }

        $DB->insert_record('videoassessment_peers', $data);
    }

    /**
     * Process grade item records from backup.
     *
     * Restores grade item definitions and creates ID mappings for grade references.
     * Rubric fillings point at the grade item, so the item is also mapped
     * under the name core's grading restore looks up for its area.
     *
     * @param array $data Grade item data from backup
     * @return void
     */
    protected function process_videoassessment_grade_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->gradeduser = $this->get_mappingid('user', $data->gradeduser);
        $data->grader = $this->get_mappingid('user', $data->grader);
        if (!$data->gradeduser || !$data->grader) {
            return;
        }

        $newitemid = $DB->insert_record('videoassessment_grade_items', $data);
        $this->set_mapping('videoassessment_grade_item', $oldid, $newitemid);
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping($data->type), $oldid, $newitemid);
    }

    /**
     * Process grade records from backup.
     *
     * Restores individual grade entries with proper grade item ID mappings.
     *
     * @param array $data Grade data from backup
     * @return void
     */
    protected function process_videoassessment_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->gradeitem = $this->get_mappingid('videoassessment_grade_item', $data->gradeitem);
        if (!$data->gradeitem) {
            return;
        }

        $newitemid = $DB->insert_record('videoassessment_grades', $data);
        // Files embedded in the comment are stored under the grade id.
        $this->set_mapping('videoassessment_grade', $oldid, $newitemid, true);
    }

    /**
     * Process aggregated grade records from backup.
     *
     * Restores user grade aggregation data with proper user ID mappings.
     *
     * @param array $data Aggregation data from backup
     * @return void
     */
    protected function process_videoassessment_aggregation($data) {
        global $DB;

        $data = (object)$data;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }

        $DB->insert_record('videoassessment_aggregation', $data);
    }

    /**
     * Process report visit records from backup.
     *
     * Keeps the "Report Viewed" state of each learner.
     *
     * @param array $data Report visit data from backup
     * @return void
     */
    protected function process_videoassessment_report_view($data) {
        global $DB;

        $data = (object)$data;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }

        $DB->insert_record('videoassessment_report_views', $data);
    }

    /**
     * Process AI speech feedback attempts from backup.
     *
     * The ad-hoc task of an unfinished attempt is not part of the backup, so
     * such an attempt is restored as queued: the learner's page starts it
     * again on their next visit. Nothing is sent to the AI during the restore.
     *
     * @param array $data Attempt data from backup
     * @return void
     */
    protected function process_videoassessment_aifb_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        if ((int)$data->status === \mod_videoassessment\aifeedback\attempt_manager::STATUS_PROCESSING) {
            $data->status = \mod_videoassessment\aifeedback\attempt_manager::STATUS_QUEUED;
        }

        $newitemid = $DB->insert_record('videoassessment_aifb_attempts', $data);
        $this->set_mapping('videoassessment_aifb_attempt', $oldid, $newitemid, true);
    }

    /**
     * Execute post-restore tasks.
     *
     * Adds the description files, video files, comment files and AI feedback
     * recordings to the restored activity, and links the training video again
     * when it was restored with the other videos.
     *
     * @return void
     */
    protected function after_execute() {
        global $DB;

        $this->add_related_files('mod_videoassessment', 'intro', null);
        $this->add_related_files('mod_videoassessment', 'video', null);
        $this->add_related_files('mod_videoassessment', 'submissioncomment', 'videoassessment_grade');
        $this->add_related_files('mod_videoassessment', 'aifbaudio', 'videoassessment_aifb_attempt');

        if ($this->oldtrainingvideoid) {
            $newvideoid = $this->get_mappingid('videoassessment_video', $this->oldtrainingvideoid);
            if ($newvideoid) {
                $DB->set_field('videoassessment', 'trainingvideoid', $newvideoid, ['id' => $this->task->get_activityid()]);
            }
        }
    }
}
