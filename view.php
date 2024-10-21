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
 * Allows viewing/use of a particular instance of videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use videoassess\va;
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/videoassessment/locallib.php');

/* MinhTB VERSION 2 03-03-2016 */
if (optional_param('ajax', null, PARAM_ALPHANUM)) {
    $action = optional_param('action', null, PARAM_ALPHANUM);

    if ($action == 'getcoursesbycategory') {
        $catid = optional_param('catid', null, PARAM_INT);
        $currentcourseid = optional_param('currentcourseid', 0, PARAM_INT);
        $courseopts = [];
        $html = "";

        if (!empty($catid)) {
            $courses = \videoassess\va::get_courses_managed_by($USER->id, $catid);
            array_walk($courses, function (\stdClass $a) use (&$courseopts) {
                $courseopts[$a->id] = $a->fullname;
            });

            $html = "<option value='0'>" . '('.get_string('new').')' . "</option>";

            foreach ($courseopts as $courseid => $coursename) {
                $selected = '';

                if ($currentcourseid == $courseid) {
                    $selected = ' selected';
                }

                $html .= "<option value='$courseid'" . $selected . ">$coursename</option>";
            }
        }

        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == 'getsectionsbycourse') {
        $courseid = optional_param('courseid', null, PARAM_INT);
        $currentsectionid = optional_param('currentsectionid', null, PARAM_INT);
        $sectionopts = [];
        $html = "";

        if (!empty($courseid)) {
            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();

            if (!empty($sections)) {
                foreach ($sections as $key => $section) {
                    $sectionopts[$section->__get('id')] = get_section_name($courseid, $section->__get('section'));
                }

                foreach ($sectionopts as $sectionid => $sectionname) {
                    $selected = '';

                    if ($currentsectionid == $sectionid) {
                        $selected = ' selected';
                    }

                    $html .= "<option value='$sectionid'" . $selected . ">$sectionname</option>";
                }
            }
        }

        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == "getallcomments") {
        global $OUTPUT, $DB, $PAGE;
        $cmid = optional_param('cmid', null, PARAM_INT);
        $userid = optional_param('userid', null, PARAM_INT);
        $timing = optional_param('timing', null, PARAM_RAW);
        $id = optional_param('id', null, PARAM_RAW);
        $context = context_module::instance($cmid);
        $va = $DB->get_record('videoassessment', ['id' => $cmid]);

        $o = \html_writer::start_tag('div', ['class' => 'card  card-body']);
        $gradertypes = ['self', 'peer', 'teacher'];

        foreach ($gradertypes as $gradertype) {
            $gradingarea = $timing.$gradertype;
            $grades = \videoassess\va::get_grade_items_byid($gradingarea, $userid, $va->id);
            foreach ($grades as $item => $gradeitem) {
                if ($gradeitem->id == $id) {
                    $comment = '<label class="mobile-submissioncomment">'.$gradeitem->submissioncomment.'</label>';
                    if ($gradertype == "peer") {
                        $lable = '<span class="blue box">Peer</span>';
                    } else if ($gradertype == "teacher") {
                        $lable = '<span class="green box">Teacher</span>';
                    } else if ($gradertype == "self") {
                        $lable = '<span class="red box">Self</span>';
                    }
                    $o .= $OUTPUT->heading($lable.$comment);
                }
            }

        }
        $o .= \html_writer::end_tag('div');

        echo json_encode([
            'html' => $o,
        ]);
        die;
    }
}
/* END MinhTB VERSION 2 03-03-2016 */
global  $DB, $PAGE;
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/mod/videoassessment/view.php', ['id' => $id]);
$ismailsent = optional_param('ismailsent', 0, PARAM_INT);
if ($action = optional_param('action', null, PARAM_ALPHA)) {
    $url->param('action', $action);
}
$cm = get_coursemodule_from_id('videoassessment', $id);
$course = $DB->get_record('course', ['id' => $cm->course]);
require_login($cm->course, true, $cm);
$PAGE->set_url($url);
$PAGE->set_heading($cm->name);
/* MinhTB VERSION 2 */
// $PAGE->requires->jquery();
$PAGE->requires->js('/mod/videoassessment/jquery-1.12.4.js', true);
/* END */
if ($action == "") {
    $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'init_message_sent_window', [$ismailsent]);
}
$context = context_module::instance($cm->id);

$va = new videoassess\va($context, $cm, $course);
echo $va->view($action);
