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

use mod_videoassessment\va;
use mod_videoassessment\form\assign_class;

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assign_class.php');

/**
 * External API for the video assessment module.
 *
 * Defines web service functions related to video assessment such as retrieving comments,
 * courses, sections, and assigning groups.
 *
 * @package   mod_videoassessment
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_videoassessment_external extends external_api {
    /**
     * Define parameters for getallcomments web service function.
     *
     * Returns external function parameters for retrieving all comments
     * associated with a specific video assessment activity.
     *
     * @return external_function_parameters Parameter definitions
     */
    public static function get_getallcomments_parameters() {
        return new external_function_parameters(
            array(
                'ajax' => new external_value(PARAM_INT, 'The data from the videoassessment comment form'),
                'action' => new external_value(PARAM_ALPHANUM, 'Which action it will create'),
                'userid' => new external_value(PARAM_INT, 'Activity which user belongs to'),
                'timing' => new external_value(PARAM_RAW, 'Time of activity'),
                'cmid' => new external_value(PARAM_INT, 'CM ID'),
                'id' => new external_value(PARAM_RAW, 'videoassessment ID'),
            )
        );
    }

    /**
     * Retrieve all comments for a specific video assessment activity.
     *
     * Fetches and formats comments from self, peer, and teacher graders
     * for display in the video assessment interface.
     *
     * @param int $ajax AJAX request identifier
     * @param string $action Action type to perform
     * @param int $userid User ID for the activity
     * @param string $timing Timing of the activity
     * @param int $cmid Course module ID
     * @param string $id Video assessment ID
     * @return array HTML content with formatted comments
     * @throws moodle_exception If user lacks required capabilities
     */
    public static function get_getallcomments($ajax, $action, $userid, $timing, $cmid, $id) {

        $params = self::validate_parameters(
            self::get_getallcomments_parameters(),
            array(
                'ajax' => $ajax,
                'action' => $action,
                'userid' => $userid,
                'timing' => $timing,
                'cmid' => $cmid,
                'id' => $id,
            )
        );

        $cmid = $params['cmid'];
        $userid = $params['userid'];
        $timing = $params['timing'];
        $id = $params['id'];

        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/videoassessment:viewcomments', $context);

        global $OUTPUT, $DB, $PAGE;

        $PAGE->set_context($context);
        $va = $DB->get_record('videoassessment', ['id' => $cmid]);

        $o = \html_writer::start_tag('div', ['class' => 'card  card-body']);
        $gradertypes = ['self', 'peer', 'teacher'];

        foreach ($gradertypes as $gradertype) {
            $gradingarea = $timing . $gradertype;
            $grades = va::get_grade_items_by_id($gradingarea, $userid, $va->id);
            foreach ($grades as $item => $gradeitem) {
                if ($gradeitem->id == $id) {
                    $comment = '<label class="mobile-submissioncomment">' . $gradeitem->submissioncomment . '</label>';
                    $label = '<span class="blue box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    $o .= $OUTPUT->heading($label . $comment);
                }
            }

        }

        $o .= \html_writer::end_tag('div');
        $data = array();
        $data['html'] = json_encode($o);
        return $data;
    }

    /**
     * Define return values for getallcomments web service function.
     *
     * Returns external single structure definition for the HTML content
     * containing formatted comments from graders.
     *
     * @return external_single_structure Return value structure
     */
    public static function get_getallcomments_returns() {
        return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text'),
            )
        );
    }


    /**
     * Define parameters for coursesbycategory web service function.
     *
     * Returns external function parameters for retrieving courses
     * filtered by category for course selection interface.
     *
     * @return external_function_parameters Parameter definitions
     */
    public static function get_coursesbycategory_parameters() {
        return new external_function_parameters(
            array(
                'ajax' => new external_value(PARAM_INT, 'The data from the videoassessment comment form'),
                'action' => new external_value(PARAM_ALPHANUM, 'Which action it will create'),
                'catid' => new external_value(PARAM_INT, 'Activity which user belongs to'),
                'currentcourseid' => new external_value(PARAM_INT, 'Time of activity'),
            )
        );
    }

    /**
     * Retrieve courses filtered by category for selection interface.
     *
     * Fetches courses managed by the current user within a specific
     * category and formats them as HTML option elements.
     *
     * @param int $ajax AJAX request identifier
     * @param string $action Action type to perform
     * @param int $catid Category ID to filter courses
     * @param int $currentcourseid Currently selected course ID
     * @return array HTML content with course options
     * @throws moodle_exception If user lacks required capabilities
     */
    public static function get_coursesbycategory($ajax, $action, $catid, $currentcourseid) {

        $params = self::validate_parameters(
            self::get_coursesbycategory_parameters(),
            array(
                'ajax' => $ajax,
                'action' => $action,
                'catid' => $catid,
                'currentcourseid' => $currentcourseid,
            )
        );

        $catid = $params['catid'];
        $currentcourseid = $params['currentcourseid'];
        $context = context_coursecat::instance($catid);
        self::validate_context($context);
        require_capability('mod/videoassessment:fetchcourses', $context);

        global $CFG, $OUTPUT, $PAGE, $DB, $USER;
        $courseopts = [];
        $html = "";

        if (!empty($catid)) {
            $courses = va::get_courses_managed_by($USER->id, $catid);
            array_walk($courses, function (\stdClass $a) use (&$courseopts) {
                $courseopts[$a->id] = $a->fullname;
            });

            $html = "<option value='0'>" . '(' . get_string('new') . ')' . "</option>";

            foreach ($courseopts as $courseid => $coursename) {
                $selected = '';

                if ($currentcourseid == $courseid) {
                    $selected = ' selected';
                }

                $html .= "<option value='$courseid'" . $selected . ">$coursename</option>";
            }
        }

        $data = array();
        $data['html'] = $html;
        return $data;
    }

    /**
     * Define return values for coursesbycategory web service function.
     *
     * Returns external single structure definition for the HTML content
     * containing course selection options.
     *
     * @return external_single_structure Return value structure
     */
    public static function get_coursesbycategory_returns() {
        return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text'),
            )
        );
    }

    /**
     * Define parameters for sectionsbycourse web service function.
     *
     * Returns external function parameters for retrieving course sections
     * for section selection interface.
     *
     * @return external_function_parameters Parameter definitions
     */
    public static function get_sectionsbycourse_parameters() {
        return new external_function_parameters(
            array(
                'ajax' => new external_value(PARAM_INT, 'The data from the videoassessment comment form'),
                'action' => new external_value(PARAM_ALPHANUM, 'Which action it will create'),
                'courseid' => new external_value(PARAM_INT, 'Activity which user belongs to'),
                'currentsectionid' => new external_value(PARAM_INT, 'Time of activity'),
            )
        );
    }

    /**
     * Retrieve course sections for selection interface.
     *
     * Fetches all sections within a specific course and formats them
     * as HTML option elements for section selection.
     *
     * @param int $ajax AJAX request identifier
     * @param string $action Action type to perform
     * @param int $courseid Course ID to get sections from
     * @param int $currentsectionid Currently selected section ID
     * @return array HTML content with section options
     * @throws moodle_exception If user lacks required capabilities
     */
    public static function get_sectionsbycourse($ajax, $action, $courseid, $currentsectionid) {

        $params = self::validate_parameters(
            self::get_sectionsbycourse_parameters(),
            array(
                'ajax' => $ajax,
                'action' => $action,
                'courseid' => $courseid,
                'currentsectionid' => $currentsectionid,
            )
        );

        $courseid = $params['courseid'];
        $currentsectionid = $params['currentsectionid'];
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('mod/videoassessment:fetchsections', $context);

        global $DB;
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

        $data = array();
        $data['html'] = $html;
        return $data;
    }

    /**
     * Define return values for sectionsbycourse web service function.
     *
     * Returns external single structure definition for the HTML content
     * containing section selection options.
     *
     * @return external_single_structure Return value structure
     */
    public static function get_sectionsbycourse_returns() {
        return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text'),
            )
        );
    }

    /**
     * Define parameters for assignclass_sort_group web service function.
     *
     * Returns external function parameters for sorting and assigning
     * students to groups within video assessment activities.
     *
     * @return external_function_parameters Parameter definitions
     */
    public static function assignclass_sort_group_parameters() {
        return new external_function_parameters(
            [
                'action'  => new external_value(PARAM_ALPHANUM, 'Action type'),
                'sort'    => new external_value(PARAM_INT, 'Sorting method (manual, name, or ID)'),
                'groupid' => new external_value(PARAM_INT, 'Group ID'),
                'id'      => new external_value(PARAM_INT, 'Course module ID'),
            ]
        );
    }

    /**
     * Sort and assign students to groups with manual or automatic ordering.
     *
     * Handles both manual drag-and-drop sorting and automatic sorting
     * by name or ID for student group assignments in video assessment.
     *
     * @param string $action Action type to perform
     * @param int $sort Sorting method (manual, name, or ID)
     * @param int $groupid Group ID for assignment
     * @param int $id Course module ID
     * @return array HTML content with sorted student list
     * @throws moodle_exception If user lacks required capabilities
     * @throws Exception If database transaction fails
     */
    public static function assignclass_sort_group($action, $sort, $groupid, $id) {
        global $CFG, $PAGE, $DB, $OUTPUT;

        $params = self::validate_parameters(
            self::assignclass_sort_group_parameters(),
            array(
                'action' => $action,
                'sort' => $sort,
                'groupid' => $groupid,
                'id' => $id,
            )
        );
        $sort = $params['sort'];
        $groupid = $params['groupid'];
        $id = $params['id'];

        $cm = get_coursemodule_from_id('videoassessment', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videoassessment:managesorting', $context);

        $PAGE->set_context($context);
        $va = new \mod_videoassessment\va($context, $cm, $course);

        if ($sort == assign_class::SORT_MANUALLY) {

            try {
                $transaction = $DB->start_delegated_transaction();

                $students = $va->get_students_sort($groupid, true);

                if (!empty($groupid)) {
                    $type = 'group';
                    $itemid = $groupid;
                } else {
                    $type = 'course';
                    $itemid = $cm->course;
                }

                $sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

                if (!$sortitem) {
                    $object = (object)[
                        'type'   => $type,
                        'itemid' => $itemid,
                        'sortby' => 0,
                    ];
                    $sortitemid = $DB->insert_record('videoassessment_sort_items', $object);
                } else {
                    $sortitemid = $sortitem->id;
                }

                $i = 1;
                $studentsdata = [];
                foreach ($students as $student) {

                    if (!empty($student->orderid)) {
                        $object = (object)[
                            'id' => $student->orderid,
                            'sortorder'  => $i,
                        ];
                        $DB->update_record('videoassessment_sort_order', $object);
                    } else {
                        $object = (object)[
                            'sortitemid' => $sortitemid,
                            'userid'     => $student->id,
                            'sortorder'  => $i,
                        ];
                        $student->orderid = $DB->insert_record('videoassessment_sort_order', $object);
                    }

                    $studentsdata[] = [
                        'orderid'  => $student->orderid,
                        'fullname' => fullname($student),
                    ];
                    $i++;
                }

                $transaction->allow_commit();
            } catch (Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }

            $templatecontext = ['students' => $studentsdata];
            $html = $OUTPUT->render_from_template('mod_videoassessment/assignclass_manual_list', $templatecontext);
        } else {
            $ordersql = ($sort == assign_class::SORT_NAME)
                ? ' ORDER BY CONCAT(u.firstname, " ", u.lastname)'
                : ' ORDER BY u.id';
            $ordersql .= ' ASC';

            $students = $va->get_students_sort($groupid, false, $ordersql);

            $studentsdata = [];
            foreach ($students as $student) {
                $studentsdata[] = [
                    'fullname' => fullname($student),
                ];
            }

            $templatecontext = ['students' => $studentsdata];
            $html = $OUTPUT->render_from_template('mod_videoassessment/assignclass_auto_list', $templatecontext);
        }

        return ['html' => $html];
    }

    /**
     * Define return values for assignclass_sort_group web service function.
     *
     * Returns external single structure definition for the HTML content
     * containing sorted student list with interactive elements.
     *
     * @return external_single_structure Return value structure
     */
    public static function assignclass_sort_group_returns() {
        return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text'),
            )
        );
    }
}
