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
 * Displays information about all the videoassessment modules in the requested course
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */


require_once("../../config.php");
require_once($CFG->dirroot.'/mod/videoassessment/locallib.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);
$PAGE->set_url('/mod/videoassessment/index.php', ['id' => $id]);
$PAGE->set_pagelayout('incourse');

// // Print the header.
$strassessment = get_string('modulename', 'videoassessment');
$PAGE->navbar->add($strassessment);
$PAGE->set_title($strassessment);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($strassessment));

$context = context_course::instance($id);

require_capability('mod/videoassessment:view', $context);

// Get all the appropriate data.
if (!$videoassessments = get_all_instances_in_course('videoassessment', $course)) {
    notice(get_string('thereareno', 'moodle', $strassessment), 'notifyproblem');
    die;
}

// // Configure table for displaying the list of instances.
$headings = [get_string('topic')];
$align = ['left'];

array_push($headings, get_string('name'));
array_push($align, 'left');

$table = new html_table();
$table->head = $headings;
$table->align = $align;

$currentsection = '';
foreach ($videoassessments as $va) {
    $cm = get_coursemodule_from_instance('videoassessment', $va->id);
    $context = context_module::instance($cm->id);
    $data = [];

    // Section number if necessary.
    $strsection = '';
    if ($va->section != $currentsection) {
        if ($va->section) {
            $strsection = $va->section;
            $strsection = get_section_name($course, $va->section);
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $va->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$va->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$va->coursemodule\">" .
            format_string($va->name, true) . '</a>';

    $table->data[] = $data;
}

// // Display the table.
echo html_writer::table($table);

// // Finish the page.
echo $OUTPUT->footer();
