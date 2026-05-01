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

defined('MOODLE_INTERNAL') || die();

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

            $gradeitem = new restore_path_element('videoassessment_grade_item', '/activity/videoassessment/gradeitems/gradeitem');
            $paths[] = $gradeitem;

            $grade = new restore_path_element('videoassessment_grade', '/activity/videoassessment/grades/grade');
            $paths[] = $grade;

            $aggregation = new restore_path_element('videoassessment_aggregation', '/activity/videoassessment/aggregations/aggregation');
            $paths[] = $aggregation;
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the main videoassessment activity data.
     *
     * Restores the core activity record and applies the new instance ID.
     *
     * @param array $data Activity data from backup
     * @return void
     */
    protected function process_videoassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

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
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->videoid = $this->get_mappingid('videoassessment_video', $data->videoid);
        $data->associationid = $this->get_mappingid('user', $data->associationid);

        $newitemid = $DB->insert_record('videoassessment_video_assocs', $data);
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
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->peerid = $this->get_mappingid('user', $data->peerid);

        $newitemid = $DB->insert_record('videoassessment_peers', $data);
    }

    /**
     * Process grade item records from backup.
     *
     * Restores grade item definitions and creates ID mappings for grade references.
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

        $newitemid = $DB->insert_record('videoassessment_grade_items', $data);
        $this->set_mapping('videoassessment_grade_item', $oldid, $newitemid);
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

        $newitemid = $DB->insert_record('videoassessment_grades', $data);
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
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('videoassessment_aggregation', $data);
    }

    /**
     * Execute post-restore tasks.
     *
     * Adds related video files to the restored activity.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_videoassessment', 'video', null);
    }
}
