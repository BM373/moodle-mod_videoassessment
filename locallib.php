<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/formslib.php';

require_once $CFG->dirroot . '/mod/videoassessment/class/function.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/grade_table.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/page.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/print_page.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/rubric.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/table_export.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/util.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/va.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/video.php';

require_once $CFG->dirroot . '/mod/videoassessment/class/form/assess.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/form/video_assoc.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/form/video_publish.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/form/video_upload.php';
