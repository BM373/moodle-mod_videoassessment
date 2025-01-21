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

defined('MOODLE_INTERNAL') || die();

require_once('../../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/mod/videoassessment/bulkupload/lib.php');
require_once($CFG->dirroot.'/filter/mediaplugin/filter.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = $DB->get_record('course_modules', array('id' => $cmid));

$bulkupload = new videoassessment_bulkupload($cmid);
$bulkupload->require_capability();

$baseurl = '/mod/videoassessment/bulkupload/view.php';
$PAGE->set_url($baseurl, array('cmid' => $cmid));
$titlestr = get_string('videoassessment:associated', 'videoassessment');
$PAGE->set_title($titlestr);
$PAGE->set_heading($titlestr);
$PAGE->navbar->add($titlestr);

$PAGE->requires->js('/mod/videoassessment/videoassessment.js');
$PAGE->requires->js_init_call('M.mod_videoassessment.init_video_preview', array($cmid), false, videoassessment_get_js_module());
$PAGE->requires->js_init_call('M.mod_videoassessment.assoc_init', null, false, videoassessment_get_js_module());

$files = $bulkupload->get_files();
uasort($files, function($a, $b) { return strnatcasecmp($a->get_filename(), $b->get_filename()); });

if ($disassociate = optional_param_array('disassociate', null, PARAM_ALPHA)) {
    foreach ($disassociate as $key => $label) {
        if (isset($files[$key])) {
            $bulkupload->move_file($files[$key], '/', true);
        }
    }
    redirect(new moodle_url('/mod/videoassessment/bulkupload/assoc.php', array('cmid' => $cmid)));
}

echo $OUTPUT->header();

if ($changed = optional_param('changed', 0, PARAM_INT)) {
    echo $OUTPUT->notification(get_string('associated', 'videoassessment', $changed), 'notifysuccess');
}

echo $OUTPUT->box_start();

$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
groups_print_activity_menu($cm, new moodle_url($baseurl, array('cmid' => $cm->id)));

echo '<form action="'.$CFG->wwwroot.$baseurl.'" method="post">'
    .'<input type="hidden" name="cmid" value="'.$cmid.'"/>';

$table = new flexible_table('assoc-users');
$table->define_baseurl($baseurl);
$columns = array('video', 'timemodified', 'user', 'timing', 'size', 'action');
$headers = array(
    get_string('video', 'videoassessment'),
    get_string('uploadedat', 'videoassessment'),
    get_string('user'),
    get_string('timing', 'videoassessment'),
    get_string('size'),
    get_string('action')
);
$table->define_columns($columns);
$table->define_headers($headers);
$table->column_style('size', 'text-align', 'right');
$table->setup();

$context = context_module::instance($cmid);
$groupusers = get_enrolled_users($context, '', $currentgroup);

$timingopts = array(
    'before' => get_string('before', 'videoassessment'),
    'after' => get_string('after', 'videoassessment')
    );

$totalsize = array_reduce($files,
    function ($sum, $file) { return $sum + (float)$file->get_filesize(); },
    0);

/* @var $file stored_file */
foreach ($files as $key => $file) {
    if (list ($userid, $timing) = videoassessment_get_assoc($file)) {
        if (!isset($groupusers[$userid]))
            continue; // グループ絞り込み

        $thumbnailfilename = preg_replace('/\.[^.]+$/',
            videoassessment_bulkupload::THUMBNAIL_FORMAT, $file->get_filename());
        $thumbnailurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(), 'mod_videoassessment', 'video', 0,
            $file->get_filepath(), $thumbnailfilename);
        $table->add_data(
            array(
                html_writer::tag(
                    'a', sprintf('<img src="%s" />', $thumbnailurl), array(
                        'href' => 'javascript:void(0)',
                        'onclick' => 'M.mod_videoassessment.assoc_preview_video(\''.$key.'\')'
                    )
                ),
                userdate($file->get_timemodified()),
                fullname($groupusers[$userid]),
                $timingopts[$timing],
                display_size($file->get_filesize()),
                html_writer::empty_tag(
                    'input', array(
                        'type' => 'submit',
                        'name' => 'disassociate['.$key.']',
                        'value' => get_string('disassociate', 'videoassessment')
                    )
                )
            )
        );
    }
}

$table->add_data(array(get_string('total'), '', '', '', display_size($totalsize), ''));

$table->finish_output();

echo $OUTPUT->box_end();

echo '<div id="videopreview"></div>';

echo html_writer::tag(
    'div',
    $OUTPUT->action_link(
        new moodle_url('/mod/videoassessment/view.php', array('id' => $cmid)),
        '&raquo; ' . get_string('videoassessment:view', 'videoassessment')
        )
    );

echo $OUTPUT->footer();
