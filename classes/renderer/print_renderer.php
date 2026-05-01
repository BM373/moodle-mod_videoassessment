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

namespace mod_videoassessment\output;

use plugin_renderer_base;
use renderable;
use mod_videoassessment\va;
use mod_videoassessment\rubric;

defined('MOODLE_INTERNAL') || die();

/**
 * Print renderer for video assessment module.
 *
 * This renderer handles the display of printable content including
 * rubric assessments and grading reports for video assessments.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class print_renderer extends plugin_renderer_base {
    /**
     * Render a renderable object using appropriate method.
     *
     * Routes renderable objects to their specific render methods
     * or falls back to the default output renderer if no specific
     * method exists.
     *
     * @param renderable $widget The renderable object to render
     * @return string HTML output of the rendered object
     */
    public function render(renderable $widget) {
        $rendermethod = 'render_' . str_replace('mod_videoassessment\renderable\\', '', get_class($widget));
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        return $this->output->render($widget);
    }

    /**
     * Render rubric assessments for printable reports.
     *
     * Generates HTML output for rubric assessments organized by timing
     * and grader type with proper formatting for print layouts.
     *
     * @return string HTML output of the rubric assessments
     */
    protected function render_rubrics() {
        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this);

        $o .= \html_writer::start_tag('div', array('class' => 'report-rubrics'));
        foreach ($this->timings as $timing) {
            $o .= $this->output->heading(va::str($timing . 'marks'));
            foreach ($this->gradertypes as $gradertype) {
                $gradingarea = $timing . $gradertype;
                $o .= $this->output->heading(
                    va::str($timing) . ' - ' . va::str($gradertype),
                    2,
                    'main',
                    'heading-' . $gradingarea
                );
                $gradinginfo = grade_get_grades(
                    $this->course->id,
                    'mod',
                    'videoassessment',
                    $this->instance,
                    $userid
                );
                $o .= \html_writer::start_tag('div', array('id' => 'rubrics-' . $gradingarea, 'class' => 'rubrics-page-down'));
                if ($controller = $rubric->get_available_controller($gradingarea)) {
                    $gradeitems = $this->get_grade_items($gradingarea, $userid);
                    foreach ($gradeitems as $gradeitem) {
                        $o .= $controller->render_grade($this->page, $gradeitem->id, $gradinginfo, '', false);
                    }
                }
                $o .= \html_writer::end_tag('div');
            }
        }
        $o .= \html_writer::end_tag('div');

        $this->page->requires->js_call_amd('mod_videoassessment/module', 'reportCombineRubrics');

        return $o;
    }
}
