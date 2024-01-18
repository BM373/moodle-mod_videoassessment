<?php
namespace videoassess;

require_once '../../config.php';
require_once $CFG->dirroot . '/mod/videoassessment/locallib.php';

class page_manage_grades extends page {
	public function execute() {
		$this->va->teacher_only();

		switch (optional_param('action', '', PARAM_ALPHA)) {
			case 'delete':
				$this->delete_grade_item();
				break;
			default:
				$this->view();
		}
	}

	private function view() {
		global $DB, $PAGE;

		$PAGE->requires->js_init_call('M.mod_videoassessment.manage_grades_init');
		$PAGE->requires->strings_for_js(array('confirmdeletegrade'), va::VA);;

		$userid = required_param('userid', PARAM_INT);

		echo $this->output->header($this->va);

		$delicon = new \pix_icon('t/delete', get_string('delete'));

		foreach ($this->va->timings as $timing) {
			foreach ($this->va->gradertypes as $gradertype) {
				$gradingarea = $timing.$gradertype;
				echo $this->output->heading(va::str($gradingarea), 4);

				$gradeitems = $this->va->get_grade_items($gradingarea, $userid);
				if ($gradeitems) {
					$grades = array();
					$table = new \html_table();
					$table->attributes = array('class' => 'generaltable boxaligncenter');
					$table->head = array(
							'',
							'Name',
							get_string('grade'),
							'Time marked',
							''
					);

					foreach ($gradeitems as $gradeitem) {
						$grader = $DB->get_record('user', array('id' => $gradeitem->grader));
						$table->data[] = array(
								$this->output->user_picture($grader),
								fullname($grader),
								$gradeitem->grade,
								userdate($gradeitem->timemarked),
								$this->output->action_icon(
										new \moodle_url($this->url, array(
												'userid' => $userid,
												'action' => 'delete',
												'gradeitem' => $gradeitem->id
										)), $delicon, null,
										array('class' => 'deletegrade'))
						);
						if ($gradeitem->grade !== null && $gradeitem->grade > -1) {
							$grades[] = $gradeitem->grade;
						}
					}

					$average = '';
					if ($grades) {
						$average = array_sum($grades) / count($grades);
					}
					$table->data[] = array(
							'Average',
							'',
							$average,
							'',
							''
					);
					echo \html_writer::table($table);
				} else {
					echo \html_writer::tag('div', 'Not graded yet.', array('style' => 'text-align: center'));
				}
			}
		}

		echo $this->output->footer();
	}

	private function delete_grade_item() {
		global $DB;

		$id = required_param('gradeitem', PARAM_INT);
		$gradeitem = $DB->get_record(va::TABLE_GRADE_ITEMS, array('id' => $id), '*', MUST_EXIST);

		$DB->delete_records(va::TABLE_GRADES, array('gradeitem' => $id));
		$DB->delete_records(va::TABLE_GRADE_ITEMS, array('id' => $id));

		$this->va->aggregate_grades($gradeitem->gradeduser);

		redirect(new \moodle_url($this->url, array('userid' => optional_param('userid', 0, PARAM_INT))));
	}
}

$page = new page_manage_grades('/mod/videoassessment/managegrades.php');
$page->execute();
