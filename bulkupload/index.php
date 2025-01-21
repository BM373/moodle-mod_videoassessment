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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/videoassessment/bulkupload/lib.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

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
