<?php
require_once '../../config.php';
require_once $CFG->dirroot . '/mod/videoassessment/locallib.php';

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/mod/videoassessment/view.php', array('id' => $id));
if ($action = optional_param('action', null, PARAM_ALPHA)) {
    $url->param('action', $action);
}
$cm = get_coursemodule_from_id('videoassessment', $id);
$course = $DB->get_record('course', array('id' => $cm->course));
require_login($cm->course, true, $cm);
$PAGE->set_url($url);
$context = context_module::instance($cm->id);

$va = new videoassess\va($context, $cm, $course);
$pp = new videoassess\print_page($va);
$pp->do_action();
