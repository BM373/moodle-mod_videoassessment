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
 * This file contains the definition for the class videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

require_once($CFG->dirroot . '/mod/videoassessment/classes/function.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/grade_table.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/page.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/print_page.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/rubric.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/table_export.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/util.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/va.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/video.php');

require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assess.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/video_assoc.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/video_publish.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/video_upload.php');
