<?php
/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: index.php 866 2012-10-28 09:43:43Z yama $
 */

require_once '../../../config.php';
require_once $CFG->dirroot.'/mod/videoassessment/bulkupload/lib.php';
require_once $CFG->dirroot . '/mod/videoassessment/locallib.php';

$cmid = required_param('cmid', PARAM_INT);

$bulkupload = new videoassessment_bulkupload($cmid);
$bulkupload->require_capability();

$PAGE->set_url('/mod/videoassessment/bulkupload/index.php', array('cmid' => $cmid));
$PAGE->set_title(get_string('videoassessment:bulkupload', 'videoassessment'));
$PAGE->set_heading(get_string('videoassessment:bulkupload', 'videoassessment'));

$PAGE->requires->css('/mod/videoassessment/bulkupload/style.css');

echo $OUTPUT->header();

// タスク一覧リンク
$cm = get_coursemodule_from_id('videoassessment', $cmid);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', array('id' => $cm->course));
$va = new videoassess\va($context, $cm, $course);
echo $va->output->task_link($va);

include __DIR__.'/droparea.html';

echo html_writer::tag(
    'div',
    $OUTPUT->action_link(
            $va->get_view_url('videos'),
            '&raquo; ' . get_string('videoassessment:associate', 'videoassessment')
    )
);

echo $OUTPUT->footer();
