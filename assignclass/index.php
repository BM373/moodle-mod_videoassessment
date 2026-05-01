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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use mod_videoassessment\va;
use mod_videoassessment\form\assign_class;

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assign_class.php');

$cmid = optional_param('id', null, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);

if (
    optional_param('sort', null, PARAM_INT) !== null
    && optional_param('id', null, PARAM_INT) !== null
    && optional_param('groupid', null, PARAM_INT) !== null
) {
    $sort = required_param('sort', PARAM_INT);
    $groupid = required_param('groupid', PARAM_INT);
    $id = required_param('id', PARAM_INT);

    $cm = get_coursemodule_from_id('videoassessment', $id, 0, false, MUST_EXIST);

    $course = $DB->get_record('course', ['id' => $cm->course]);
    $context = \context_module::instance($cm->id);
    require_capability('mod/videoassessment:managesorting', $context);
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

            $sortitem = $DB->get_record('videoassessment_sort_items', ['type' => $type, 'itemid' => $itemid]);

            if (!$sortitem) {
                $object = new \stdClass();
                $object->type = $type;
                $object->itemid = $itemid;
                $object->sortby = 0;
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
                    $object = new \stdClass();
                    $object->sortitemid = $sortitemid;
                    $object->userid = $student->id;
                    $object->sortorder = $i;

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
        echo $html;
        die;
    } else {
        $ordersql = '';
        if ($sort == assign_class::SORT_NAME) {
            $ordersql .= ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
        } else {
            $ordersql .= ' ORDER BY u.id';
        }

        $ordersql .= ' ASC';

        $students = $va->get_students_sort($groupid, false, $ordersql);

        $studentsdata = [];
        foreach ($students as $k => $student) {
            $studentsdata[] = [
                'fullname' => fullname($student),
            ];
        }

        $templatecontext = ['students' => $studentsdata];
        $html = $OUTPUT->render_from_template('mod_videoassessment/assignclass_auto_list', $templatecontext);
        echo $html;
        die;
    }
}

$course = $DB->get_record('course', ['id' => $cm->course]);
$context = \context_module::instance($cm->id);
require_capability('mod/videoassessment:managesorting', $context);

$va = new \mod_videoassessment\va($context, $cm, $course);

$PAGE->requires->css('/mod/videoassessment/font/font-awesome/css/font-awesome.min.css');
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('mod_videoassessment/assignclass', 'assignclassSortByGroup', []);

$url = new \moodle_url('/mod/videoassessment/assignclass/index.php', ['id' => $cm->id, 'groupid' => $groupid]);
$PAGE->set_url($url);

$students = $va->get_students_sort($groupid, true);

$groups = $DB->get_records('groups', ['courseid' => $course->id], '', 'id, name');

if (empty($groupid)) {
    $itemid = $course->id;
    $type = 'course';
} else {
    $itemid = $groupid;
    $type = 'group';
}

$sortitem = $DB->get_record('videoassessment_sort_items', ['type' => $type, 'itemid' => $itemid]);

if (!empty($sortitem)) {
    $sortby = $sortitem->sortby;
} else {
    $sortby = 0;
}

$form = new assign_class(null, (object)[
    'va' => $va,
    'sortby' => $sortby,
    'students' => $students,
    'groups' => $groups,
    'groupid' => $groupid,
]);

if ($data = $form->get_data()) {
    try {
        $transaction = $DB->start_delegated_transaction();

        $sortby = $data->sortby;
        $groupid = $data->groupid;
        $orderidarr = optional_param_array('orderid', [], PARAM_INT);

        if (!empty($groupid)) {
            $type = 'group';
            $itemid = $groupid;
        } else {
            $type = 'course';
            $itemid = $cm->course;
        }

        $sortitem = $DB->get_record('videoassessment_sort_items', ['type' => $type, 'itemid' => $itemid]);

        if (!$sortitem) {
            $object = new \stdClass();
            $object->type = $type;
            $object->itemid = $itemid;
            $object->sortby = $sortby;
            $DB->insert_record('videoassessment_sort_items', $object);
        } else {
            $sortitem->sortby = $sortby;
            $DB->update_record('videoassessment_sort_items', $sortitem);
        }

        if ($sortby == assign_class::SORT_MANUALLY && !empty($orderidarr)) {
            $i = 1;

            foreach ($orderidarr as $orderid) {
                $object = (object)[
                    'id' => $orderid,
                    'sortorder'  => $i,
                ];
                $DB->update_record('videoassessment_sort_order', $object);
                $i++;
            }
        }

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(va::str('assignclass'));

$form->display();

echo $OUTPUT->footer();
