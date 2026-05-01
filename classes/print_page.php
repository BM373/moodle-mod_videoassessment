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

/**
 * Print page controller for video assessment reports.
 *
 * This class handles the generation and display of printable reports
 * including rubric assessments and grading summaries for video assessments.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class print_page {
    /**
     * Video assessment instance object.
     *
     * Contains the main video assessment functionality and data.
     *
     * @var va
     */
    private $va;

    /**
     * Print renderer instance.
     *
     * Handles the rendering of printable content and layouts.
     *
     * @var \mod_videoassessment_print_renderer
     */
    private $output;

    /**
     * Initialize the print page controller.
     *
     * Sets up the embedded page layout, initializes the video assessment
     * instance, and configures the print renderer with appropriate styling.
     *
     * @param va $va Video assessment instance object
     * @return void
     */
    public function __construct(va $va) {
        global $PAGE;

        $PAGE->set_pagelayout('embedded');
        $this->va = $va;
        $this->output = $PAGE->get_renderer('mod_videoassessment', 'print');

        $PAGE->set_title($this->va->va->name);

        $PAGE->requires->css('/mod/videoassessment/styles-print.css');
    }

    /**
     * Execute the requested print action.
     *
     * Routes the request to the appropriate print method based on
     * the action parameter from the URL.
     *
     * @return void
     */
    public function do_action() {
        $action = optional_param('action', null, PARAM_ALPHA);

        switch ($action) {
            case 'report':
                $this->rubric_report();
                break;
        }
    }

    /**
     * Generate and display rubric assessment report.
     *
     * Creates a comprehensive printable report showing rubric assessments,
     * grades, comments, and final scores for selected users with proper
     * page breaks and formatting for printing.
     *
     * @return void
     */
    private function rubric_report() {
        global $OUTPUT, $PAGE, $DB;

        echo $this->output->header();
        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this->va);

        if ($userid) {
            $users = [$DB->get_record('user', ['id' => $userid], 'id, lastname, firstname')];
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
                $o .= \html_writer::tag('div', '', ['class' => 'pagebreak border-bottom']);
            }

            $o .= $OUTPUT->heading(fullname($user));
            $o .= \html_writer::start_tag('div', ['class' => 'report-rubrics']);
            foreach ($this->va->timings as $timing) {
                if (!$gradingstatus->$timing) {
                    continue;
                }

                $o .= $OUTPUT->heading($this->va->str('allscores'), 3);
                $timinggrades = [];
                foreach ($this->va->gradertypes as $gradertype) {
                    if (
                        $this->va->va->class
                        && $gradertype == 'class'
                        && !has_capability('mod/videoassessment:grade', $this->va->context)
                    ) {
                        continue;
                    }

                    $gradingarea = $timing . $gradertype;
                    $o .= $OUTPUT->heading(
                        $this->va->timing_str($timing, null, 'ucfirst') . ' - ' . va::str($gradertype),
                        4,
                        'main',
                        'heading-' . $gradingarea
                    );
                    $gradinginfo = grade_get_grades(
                        $this->va->course->id,
                        'mod',
                        'videoassessment',
                        $this->va->instance,
                        $userid
                    );

                    $o .= \html_writer::start_tag('div', ['id' => 'rubrics-' . $gradingarea]);

                    if ($controller = $rubric->get_available_controller($gradingarea)) {
                        $gradeitems = $this->va->get_grade_items($gradingarea, $userid);
                        if (!is_null($gradeitems) && !empty($gradeitems)) {
                            foreach ($gradeitems as $gradeitem) {
                                $o .= $controller->render_grade($PAGE, $gradeitem->id, $gradinginfo, '', false);
                                $timinggrades[] = \html_writer::tag(
                                    'span',
                                    (int) $gradeitem->grade,
                                    ['class' => 'rubrictext-' . $gradertype]
                                );
                                $o .= \html_writer::tag('hr', '');
                            }
                        }
                    }
                    $o .= \html_writer::end_tag('div');
                }

                // Adtis.
                $o .= $OUTPUT->heading("General Comments");
                $o .= \html_writer::start_tag('div', ['class' => 'card  card-body']);
                foreach ($this->va->gradertypes as $gradertype) {
                    if (
                        $gradertype == 'training'
                        || $gradertype == 'class'
                        || (
                            $this->va->va->class
                            && $gradertype == 'class'
                            && !has_capability('mod/videoassessment:grade', $this->va->context)
                        )
                    ) {
                        continue;
                    }
                    $gradingarea = $timing . $gradertype;
                    $grades = $this->va->get_grade_items($gradingarea, $userid);
                    foreach ($grades as $gradeitem) {
                        // Format the comment to convert @@PLUGINFILE@@ placeholders to actual URLs.
                        $commentformat = isset($gradeitem->submissioncommentformat)
                            ? $gradeitem->submissioncommentformat
                            : FORMAT_HTML;
                        // First rewrite @@PLUGINFILE@@ placeholders to actual URLs.
                        // Use gradeid (from videoassessment_grades table) not gradeitem->id (from grade_items table).
                        $gradeid = isset($gradeitem->gradeid) ? $gradeitem->gradeid : $gradeitem->id;
                        $commenttext = file_rewrite_pluginfile_urls(
                            $gradeitem->submissioncomment,
                            'pluginfile.php',
                            $this->va->context->id,
                            'mod_videoassessment',
                            'submissioncomment',
                            $gradeid
                        );
                        // Then format the text. noclean is required so
                        // the HTML5 <video>/<source> tags produced by
                        // the recordrtc Atto/Tiny plugin survive the
                        // purifier pass; mirrors the same fix in
                        // view.php (item #6 of the 2026-04 fix
                        // programme).
                        $formattedcomment = format_text($commenttext, $commentformat, [
                            'context' => $this->va->context,
                            'noclean' => true,
                        ]);
                        $comment = '<label class="submissioncomment">' . $formattedcomment . '</label>';
                        if ($gradertype == "peer") {
                            $lable = '<span class="blue box">' . $this->va::str('peer') . '</span>';
                        } else if ($gradertype == "teacher") {
                            $lable = '<span class="green box">' . $this->va::str('teacher') . '</span>';
                        } else if ($gradertype == "self") {
                            $lable = '<span class="red box">' . $this->va::str('self') . '</span>';
                        }
                        $o .= $OUTPUT->heading($lable . $comment);
                    }
                }
                $o .= \html_writer::end_tag('div');

                $gradeduser = $DB->get_record('user', ['id' => $userid]);
                $o .= \html_writer::start_tag('div', ['class' => 'comment comment-' . $gradertype])
                    . $OUTPUT->user_picture($gradeduser)
                    . ' ' . fullname($gradeduser)
                    . \html_writer::end_tag('div');

                if ($timinggrades) {
                    $totalscore = ' ='
                        . \html_writer::start_tag('div', ['class' => 'comment-grade'])
                        . '<span class="comment-score-text">'
                        . $this->va::str('totalscore')
                        . '</span><span class="comment-score">'
                        . (int) $usergrades->{'grade' . $timing}
                        . '</span>'
                        . \html_writer::end_tag('div');
                    $fairnessbonus = '<span  class="fairness">+</span> '
                        . \html_writer::start_tag('div', ['class' => 'comment-grade fairness'])
                        . '<span class="comment-score-text" >'
                        . '+' . $this->va::str('fairnessbonus')
                        . '</span><span class="comment-score">'
                        . (int) $usergrades->fairnessbonus
                        . '</span>'
                        . \html_writer::end_tag('div');
                    $finalscore = ' = '
                        . \html_writer::start_tag('div', ['class' => 'comment-grade'])
                        . '<span class="comment-score-text">'
                        . $this->va::str('finalscore')
                        . '</span><span class="comment-score">'
                        . (int) $usergrades->finalscore
                        . '</span>'
                        . \html_writer::end_tag('div');
                    $finalgradetext = get_string('grade', 'videoassessment') . ': '
                        . implode(', ', $timinggrades)
                        . $totalscore . $fairnessbonus . $finalscore;
                    $o .= $OUTPUT->container($finalgradetext, 'finalgrade');
                }
            }
            $o .= \html_writer::end_tag('div');
        }

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'reportCombineRubrics');
        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'initPrint');

        echo $o;

        $PAGE->blocks->show_only_fake_blocks();
        echo $this->output->footer();
    }
}
