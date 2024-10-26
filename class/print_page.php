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
 * The videoassess namespace definition.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class print_page
{
    /**
     *
     * @var va
     */
    private $va;
    /**
     *
     * @var \mod_videoassessment_print_renderer
     */
    private $output;

    public function __construct(va $va)
    {
        global $PAGE;

        $PAGE->set_pagelayout('embedded');
        $this->va = $va;
        $this->output = $PAGE->get_renderer('mod_videoassessment', 'print');

        $PAGE->set_title($this->va->va->name);

        $PAGE->requires->css('/mod/videoassessment/styles-print.css');
    }

    public function do_action()
    {
        $action = optional_param('action', null, PARAM_ALPHA);

        switch ($action) {
            case 'report':
                $this->rubric_report();
                break;
        }
    }

    private function rubric_report()
    {
        global $OUTPUT, $PAGE, $DB;

        echo $this->output->header();
        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this->va);

        if ($userid) {
            $users = array($DB->get_record('user', array('id' => $userid), 'id, lastname, firstname'));
        } else {
            $users = $this->va->get_students();
        }

        $firstpage = true;

        foreach ($users as $user) {
            $userid = $user->id;

            $gradingstatus = $this->va->get_grading_status($userid);
            $usergrades = $this->va->get_aggregated_grades($userid);
            if (!$gradingstatus->any) {
                continue;
            }

            if ($firstpage) {
                $firstpage = false;
            } else {
                $o .= \html_writer::tag('div', '', array('class' => 'pagebreak border-bottom'));
            }

            $o .= $OUTPUT->heading(fullname($user));
            $o .= \html_writer::start_tag('div', array('class' => 'report-rubrics'));
            foreach ($this->va->timings as $timing) {
                if (!$gradingstatus->$timing) {
                    continue;
                }

                $o .= $OUTPUT->heading($this->va->str('allscores'), 3);
                $timinggrades = array();
                foreach ($this->va->gradertypes as $gradertype) {
                    if ($this->va->va->class && $gradertype == 'class' && !has_capability('mod/videoassessment:grade', $this->va->context)) {
                        continue;
                    }

                    $gradingarea = $timing . $gradertype;
                    $o .= $OUTPUT->heading(
                        $this->va->timing_str($timing, null, 'ucfirst') . ' - ' . va::str($gradertype),
                        4, 'main', 'heading-' . $gradingarea);
                    $gradinginfo = grade_get_grades($this->va->course->id, 'mod', 'videoassessment',
                        $this->va->instance, $userid);

                    $o .= \html_writer::start_tag('div', array('id' => 'rubrics-' . $gradingarea));

                    if ($controller = $rubric->get_available_controller($gradingarea)) {
                        $gradeitems = $this->va->get_grade_items($gradingarea, $userid);
                        if (!is_null($gradeitems) && !empty($gradeitems)) {
                            foreach ($gradeitems as $gradeitem) {
                                $o .= $controller->render_grade($PAGE, $gradeitem->id, $gradinginfo, '', false);
                                $timinggrades[] = \html_writer::tag('span', (int)$gradeitem->grade, array('class' => 'rubrictext-' . $gradertype));
                                $o .= \html_writer::tag('hr', '');
                            }
                        }
                    }
                    $o .= \html_writer::end_tag('div');
                }

                //adtis
                $o .= $OUTPUT->heading("General Comments");
                $o .= \html_writer::start_tag('div', array('class' => 'card  card-body'));
                foreach ($this->va->gradertypes as $gradertype) {
                    if ( $gradertype == 'training' || $gradertype == 'class' ||($this->va->va->class && $gradertype == 'class' && !has_capability('mod/videoassessment:grade', $this->va->context))) {
                        continue;
                    }
                    $gradingarea = $timing.$gradertype;
                    $grades = $this->va->get_grade_items($gradingarea, $userid);
                    foreach ($grades as $gradeitem){
                        $comment = '<label class="submissioncomment">'.$gradeitem->submissioncomment.'</label>';
                        if($gradertype == "peer"){
                            $lable = '<span class="blue box">Peer</span>';
                        }elseif ($gradertype == "teacher"){
                            $lable = '<span class="green box">Teacher</span>';
                        }elseif ($gradertype == "self"){
                            $lable = '<span class="red box">Self</span>';
                        }
                        $o .= $OUTPUT->heading($lable.$comment);
                    }

                }
                $o .= \html_writer::end_tag('div');

                $gradeduser = $DB->get_record('user', array('id' => $userid));
                $o .= \html_writer::start_tag('div', array('class' => 'comment comment-'.$gradertype))
                    .$OUTPUT->user_picture($gradeduser)
                    .' '.fullname($gradeduser)
                    .\html_writer::end_tag('div');

                if ($timinggrades) {
                    $totalScore = ' ='.\html_writer::start_tag('div', array('class' => 'comment-grade')).'<span class="comment-score-text">Total    Score</span><span class="comment-score">'.(int)$usergrades->{'grade'.$timing}.'</span>'.\html_writer::end_tag('div');
                    $fairnessBonus = '<span  class="fairness">+</span> '.\html_writer::start_tag('div', array('class' => 'comment-grade fairness')).'<span class="comment-score-text" >+Fairness Bonus</span><span class="comment-score">'.(int)$usergrades->fairnessbonus.'</span>'.\html_writer::end_tag('div');
                    $finalscore = ' = '.\html_writer::start_tag('div', array('class' => 'comment-grade')).'<span class="comment-score-text">Final    Score</span><span class="comment-score">'.(int)$usergrades->finalscore.'</span>'.\html_writer::end_tag('div');
                    $o .= $OUTPUT->container(get_string('grade').': '.implode(', ', $timinggrades).$totalScore.$fairnessBonus.$finalscore, 'finalgrade');
                }
            }
            $o .= \html_writer::end_tag('div');
        }

        $PAGE->requires->js_init_call('M.mod_videoassessment.report_combine_rubrics', null, false,
            $this->va->jsmodule);
        $PAGE->requires->js_init_call('M.mod_videoassessment.init_print');

        echo $o;

        $PAGE->blocks->show_only_fake_blocks();
        echo $this->output->footer();
    }
}
