<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/videoassessment/backup/moodle2/backup_videoassessment_stepslib.php';

class backup_videoassessment_activity_task extends backup_activity_task {
    protected function define_my_settings() {
        if (!get_config('videoassessment', 'backupusers')) {
            foreach ($this->get_settings() as $setting) {
                if ($setting instanceof backup_activity_userinfo_setting) {
                    $setting->set_value(false);
                }
            }
        }
    }

    protected function define_my_steps() {
        $this->add_step(new backup_videoassessment_activity_structure_step('videoassessment', 'videoassessment.xml'));
    }

    public static function encode_content_links($content) {
        return $content;
    }
}
