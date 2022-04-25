<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/videoassessment/backup/moodle2/restore_videoassessment_stepslib.php';

class restore_videoassessment_activity_task extends restore_activity_task {
    protected function define_my_steps() {
        $this->add_step(new restore_videoassessment_activity_structure_step('videoassessment_structure', 'videoassessment.xml'));
    }

    protected function define_my_settings() {

    }

    public static function define_decode_contents() {
        $contents = array();

        return $contents;
    }

    public static function define_decode_rules() {
        $rules = array();

        return $rules;
    }

}
