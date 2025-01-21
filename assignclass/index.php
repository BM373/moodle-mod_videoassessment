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

namespace videoassess;

use \videoassess\va;
use \videoassess\form\assign_class;

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assign_class.php');

$cmid = optional_param('id', null, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);

if (optional_param('sort', null, PARAM_INT) !== null && optional_param('id', null, PARAM_INT) !== null && optional_param('groupid', null, PARAM_INT) !== null) {
    $sort = required_param('sort', PARAM_INT);
    $groupid = required_param('groupid', PARAM_INT);
    $id = required_param('id', PARAM_INT);

    $cm = get_coursemodule_from_id('videoassessment', $id, 0, false, MUST_EXIST);

    $course = $DB->get_record('course', array('id' => $cm->course));
    $context = \context_module::instance($cm->id);
    $va = new \videoassess\va($context, $cm, $course);

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
                $object = new \stdClass();
                $object->type = $type;
                $object->itemid = $itemid;
                $object->sortby = 0;
                $sortitemid = $DB->insert_record('videoassessment_sort_items', $object);
            } else {
                $sortitemid = $sortitem->id;
            }

            $i = 1;
            $html = '<ul id="manually-list">';
            foreach ($students as $k => $student) {

                if (!empty($student->orderid)) {
                    $sql = "
                        UPDATE {videoassessment_sort_order} vso
                        SET vso.sortorder = :order
                        WHERE vso.id = :id
                    ";

                    $params = array(
                        'order' => $i,
                        'id' => $student->orderid
                    );

                    $DB->execute($sql, $params);
                } else {
                    $object = new \stdClass();
                    $object->sortitemid = $sortitemid;
                    $object->userid = $student->id;
                    $object->sortorder = $i;

                    $student->orderid = $DB->insert_record('videoassessment_sort_order', $object);
                }

                $html .= '<li data-orderid="' . $student->orderid . '" class="clearfix">';
                $html .= '<div class="name">' . fullname($student) . '</div>';
                $html .= '</li>';
                $i++;
            }

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        $html .= '</ul><div id="manually-hidden"></div>';
        $html .= "<script type='text/javascript' src='/mod/videoassessment/js/jquery-sortable.js'></script>";
        $html .= "<script type='text/javascript'>";
        $html .= "
        group = $('#manually-list').sortable({
                group: 'manually-list',
                onDrop: function(item, container, _super) {
                    var data = group.sortable('serialize').get();

                    var html = '';
                    for (x in data[0]) {
                        var obj = data[0][x];
                        html += '<input type=\"hidden\" name=\"orderid[]\" value=\"' + obj.orderid + '\" />';
                    }

                    $('#manually-hidden').html(html);

                    _super(item, container);
                }
            });
        ";
        $html .= "</script>";
    } else {
        $order_sql = '';
        if ($sort == assign_class::SORT_NAME) {
            $order_sql .= ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
        } else {
            $order_sql .= ' ORDER BY u.id';
        }

        $order_sql .= ' ASC';

        $students = $va->get_students_sort($groupid, false, $order_sql);

        $html = '<ul class="id_order_students">';
        foreach ($students as $k => $student) {
            $html .= '<li class="clearfix">';
            $html .= '<div class="name">' . fullname($student) . '</div>';
            $html .= '</li>';
        }

        $html .= '</ul>';
    }

    echo $html; die;
}

$course = $DB->get_record('course', array('id' => $cm->course));
$context = \context_module::instance($cm->id);
$va = new \videoassess\va($context, $cm, $course);

$va->teacher_only();

$PAGE->requires->css('/mod/videoassessment/styles.css');
$PAGE->requires->css('/mod/videoassessment/font/font-awesome/css/font-awesome.min.css');
$PAGE->requires->jquery();
// $PAGE->requires->js('/mod/videoassessment/js/jquery-sortable.js', true);
//$PAGE->requires->js('/mod/videoassessment/assignclass/assignclass.js');
$PAGE->requires->js_call_amd('mod_videoassessment/assignclass', 'assignclassSortByGroup', array());

$url = new \moodle_url('/mod/videoassessment/assignclass/index.php', array('id' => $cm->id, 'groupid' => $groupid));
$PAGE->set_url($url);

$students = $va->get_students_sort(true);

$groups = $DB->get_records('groups', array('courseid' => $course->id), '', 'id, name');

if (empty($groupid)) {
    $itemid = $course->id;
    $type = 'course';
} else {
    $itemid = $groupid;
    $type = 'group';
}

$sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

if (!empty($sortitem)) {
    $sortby = $sortitem->sortby;
} else {
    $sortby = 0;
}

$form = new assign_class(null, (object)array(
    'va' => $va,
    'sortby' => $sortby,
    'students' => $students,
    'groups' => $groups,
    'groupid' => $groupid
));

if ($data = $form->get_data()) {

    try {
        $transaction = $DB->start_delegated_transaction();

        $sortby = $data->sortby;
        $groupid = $data->groupid;
        $orderid_arr = optional_param_array('orderid', array(), PARAM_INT);

        if (!empty($groupid)) {
            $type = 'group';
            $itemid = $groupid;
        } else {
            $type = 'course';
            $itemid = $cm->course;
        }

        $sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

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

        if ($sortby = assign_class::SORT_MANUALLY && !empty($orderid_arr)) {
            $i = 1;

            foreach ($orderid_arr as $orderid) {
                $sql = "
                    UPDATE {videoassessment_sort_order} vso
                    SET vso.sortorder = :order
                    WHERE vso.id = :id
                ";

                $params = array(
                    'order' => $i,
                    'id' => $orderid
                );

                $DB->execute($sql, $params);
                $i++;
            }
        }

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
    }

    //redirect($url);
}

echo $OUTPUT->header($va);
echo $OUTPUT->heading(va::str('assignclass'));

$form->display();

echo $OUTPUT->footer();
