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

namespace mod_videoassessment;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

/**
 * Grade manager for a particular instance of videoassessment.
 *
 * Provides interface for teachers to view and manage individual grades
 * across different assessment types and timings.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_manage_grades extends page {
    /**
     * Execute the grade management page logic.
     *
     * Handles different actions for grade management including viewing
     * grades and deleting individual grade items with proper authorization.
     *
     * @return void
     * @throws moodle_exception If user lacks required capabilities
     */
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

    /**
     * Display grade management interface for a specific user.
     *
     * Shows all grades across different timings and grader types with
     * delete functionality and grade averages for comprehensive review.
     *
     * @return void
     * @throws moodle_exception If required parameters are missing
     */
    private function view() {
        global $DB, $PAGE;

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'manageGradesInit');
        $PAGE->requires->strings_for_js(array('confirmdeletegrade'), va::VA);

        $userid = required_param('userid', PARAM_INT);

        echo $this->output->header($this->va);

        $delicon = new \pix_icon('t/delete', get_string('delete'));

        foreach ($this->va->timings as $timing) {
            foreach ($this->va->gradertypes as $gradertype) {
                $gradingarea = $timing . $gradertype;
                echo $this->output->heading(va::str($gradingarea), 4);

                $gradeitems = $this->va->get_grade_items($gradingarea, $userid);
                if ($gradeitems) {
                    $grades = array();
                    $table = new \html_table();
                    $table->attributes = array('class' => 'generaltable boxaligncenter');
                    $table->head = array(
                            '',
                            get_string('name'),
                            get_string('grades'),
                            get_string('timemarked', 'videoassessment'),
                            '',
                    );

                    foreach ($gradeitems as $gradeitem) {
                        $grader = $DB->get_record('user', array('id' => $gradeitem->grader));
                        $deleteurl = new \moodle_url($this->url, array(
                            'userid' => $userid,
                            'action' => 'delete',
                            'gradeitem' => $gradeitem->id,
                        ));

                        $iconhtml = $this->output->pix_icon('t/delete', get_string('delete'));

                        $form = \html_writer::start_tag('form', array(
                            'method' => 'post',
                            'action' => $deleteurl,
                            'class' => 'deletegradeform', // optional for JS
                            'style' => 'display:inline',
                        ));
                        $form .= \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                        $form .= \html_writer::tag('button', $iconhtml, array(
                            'type' => 'submit',
                            'class' => 'deletegrade', // this class triggers confirmation
                            'style' => 'all: unset; cursor: pointer;',
                            'title' => get_string('delete'),
                        ));
                        $form .= \html_writer::end_tag('form');
                        $table->data[] = array(
                            $this->output->user_picture($grader),
                            fullname($grader),
                            $gradeitem->grade,
                            userdate($gradeitem->timemarked),
                            $form,
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
                        get_string('average', 'videoassessment'),
                        '',
                        $average,
                        '',
                        '',
                    );
                    echo \html_writer::table($table);
                } else {
                    echo \html_writer::tag('div', get_string('notgradedyet', 'videoassessment'), array('style' => 'text-align: center'));
                }
            }
        }

        echo $this->output->footer();
    }

    /**
     * Delete a specific grade item and recalculate aggregates.
     *
     * Removes individual grade items from the database and triggers
     * grade aggregation recalculation for the affected user.
     *
     * @return void
     * @throws moodle_exception If session key validation fails or grade item not found
     */
    private function delete_grade_item() {
        global $DB;

        require_sesskey();

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
