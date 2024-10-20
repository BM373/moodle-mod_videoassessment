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
 * Allows pre-view of a particular video.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
 
require_once '../../config.php';
require_once $CFG->dirroot.'/mod/videoassessment/locallib.php';

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/mod/videoassessment/videopreview.php', array('id' => $id));
$cm = get_coursemodule_from_id('videoassessment', $id);
$course = $DB->get_record('course', array('id' => $cm->course));
require_login($cm->course, true, $cm);
$PAGE->set_url($url);
$context = context_module::instance($cm->id);

$PAGE->set_pagelayout('embedded');

$va = new videoassess\va($context, $cm, $course);
echo $va->preview_video();
