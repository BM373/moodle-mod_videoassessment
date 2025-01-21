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

use videoassess\va;
use \videoassess\form\assign_class;

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/videoassessment/locallib.php');
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assign_class.php');

		

class mod_videoassessment_external extends external_api {

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

    public static function get_getallcomments($ajax, $action, $userid, $timing, $cmid, $id) {

        $params = self::validate_parameters(self::get_getallcomments_parameters(),
                                            array(
                                                'ajax' => $ajax,
                                                'action' => $action,
                                                'userid' => $userid,
                                                'timing' => $timing,
                                                'cmid' => $cmid,
                                                'id' => $id,
                                            ));

        $cmid = $params['cmid'];
        $userid = $params['userid'];
        $timing = $params['timing'];
        $id = $params['id'];

        global $OUTPUT, $DB, $PAGE;
        $context = context_module::instance($cmid);
        $PAGE->set_context($context);
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
        $data = array();
        $data['html'] = json_encode($o);
        return $data;
    }

    public static function get_getallcomments_returns() {
        return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text')
            )
        );
    }
	
	
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
	
	public static function get_coursesbycategory($ajax, $action, $catid, $currentcourseid) {
		
		$params = self::validate_parameters(self::get_coursesbycategory_parameters(),
                                            array(
                                                'ajax' => $ajax,
												'action' => $action,
												'catid' => $catid,
												'currentcourseid' => $currentcourseid,
                                            ));
		
		//$ajax = $params['ajax'];
        //$action = $params['action'];
        $catid = $params['catid'];
        $currentcourseid = $params['currentcourseid'];
		global $CFG, $OUTPUT, $PAGE, $DB, $USER;
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
        
		$data = array();
		//$data['html'] = json_encode($html);
		$data['html'] = $html;
        return $data;
    }
	
	public static function get_coursesbycategory_returns() {
		return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text')
            )
        );
    }
	
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
	
	public static function get_sectionsbycourse($ajax, $action, $courseid, $currentsectionid) {
		
		$params = self::validate_parameters(self::get_sectionsbycourse_parameters(),
                                            array(
                                                'ajax' => $ajax,
												'action' => $action,
												'courseid' => $courseid,
												'currentsectionid' => $currentsectionid,
                                            ));
		
		//$ajax = $params['ajax'];
        //$action = $params['action'];
        $courseid = $params['courseid'];
        $currentsectionid = $params['currentsectionid'];
		global $CFG, $OUTPUT, $PAGE, $DB, $USER;
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
		//$data['html'] = json_encode($html);
		$data['html'] = $html;
        return $data;
    }
	
	public static function get_sectionsbycourse_returns() {
		return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text')
            )
        );
    }
	
	public static function assignclass_sort_group_parameters() {
        return new external_function_parameters(
            array(
				'action' => new external_value(PARAM_ALPHANUM, 'Which action it will create'),
				'sort' => new external_value(PARAM_INT, 'Activity which user belongs to'),
				'groupid' => new external_value(PARAM_INT, 'Time of activity'),
				'id' => new external_value(PARAM_INT, 'Time of activity'),
            )
        );
    }
	
	public static function assignclass_sort_group($action, $sort, $groupid, $id) {
		
		$params = self::validate_parameters(self::assignclass_sort_group_parameters(),
                                            array(
												'action' => $action,
												'sort' => $sort,
												'groupid' => $groupid,
												'id' => $id,
                                            ));
        $sort = $params['sort'];
        $groupid = $params['groupid'];
		$id = $params['id'];
		global $CFG, $OUTPUT, $PAGE, $DB, $USER;
		
        $cm = get_coursemodule_from_id('videoassessment', $id, 0, false, MUST_EXIST);

		$course = $DB->get_record('course', array('id' => $cm->course));
		$context = \context_module::instance($cm->id);
		$PAGE->set_context($context);
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
			$html .= "<script type='text/javascript' src='/moodle/mod/videoassessment/jquery-sortable.js'></script>";
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
        
		$data = array();
		//$data['html'] = json_encode($html);
		$data['html'] = $html;
        return $data;
    }
	
	public static function assignclass_sort_group_returns() {
		return new external_single_structure(
            array(
                'html' => new external_value(PARAM_RAW, 'settings content text')
            )
        );
    }

}
