<?php
defined('MOODLE_INTERNAL') || die();

class restore_videoassessment_activity_structure_step extends restore_activity_structure_step {
    /**
     *
     * @return restore_path_element
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

    protected function process_videoassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('videoassessment', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_videoassessment_video($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');

        $newitemid = $DB->insert_record('videoassessment_videos', $data);
        $this->set_mapping('videoassessment_video', $oldid, $newitemid);
    }

    protected function process_videoassessment_video_assoc($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->videoid = $this->get_mappingid('videoassessment_video', $data->videoid);
        $data->associationid = $this->get_mappingid('user', $data->associationid);

        $newitemid = $DB->insert_record('videoassessment_video_assocs', $data);
    }

    protected function process_videoassessment_peer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->peerid = $this->get_mappingid('user', $data->peerid);

        $newitemid = $DB->insert_record('videoassessment_peers', $data);
    }

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

    protected function process_videoassessment_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->gradeitem = $this->get_mappingid('videoassessment_grade_item', $data->gradeitem);

        $newitemid = $DB->insert_record('videoassessment_grades', $data);
    }

    protected function process_videoassessment_aggregation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoassessment = $this->get_new_parentid('videoassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('videoassessment_aggregation', $data);
    }

    protected function after_execute() {
        $this->add_related_files('mod_videoassessment', 'video', null);
    }
}
