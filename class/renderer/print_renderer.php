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
 * Renderer used to display special elements of the videoassessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

// use \videoassess;
use \videoassess\va;
use \videoassess\rubric;

defined('MOODLE_INTERNAL') || die();

/**
 * @method string header() header()
 * @method string footer() footer()
 */
class mod_videoassessment_print_renderer extends plugin_renderer_base {
    /**
     *
     * @param renderable $widget
     * @return string
     */
    public function render(renderable $widget) {
        $rendermethod = 'render_'.str_replace('videoassess\renderable\\', '', get_class($widget));
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        return $this->output->render($widget);
    }

    protected function render_rubrics() {
        global $PAGE;

        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this);

        $o .= \html_writer::start_tag('div', array('class' => 'report-rubrics'));
        foreach ($this->timings as $timing) {
            $o .= $OUTPUT->heading(va::str($timing.'marks'));
            foreach ($this->gradertypes as $gradertype) {
                $gradingarea = $timing.$gradertype;
                $o .= $OUTPUT->heading(
                        va::str($timing).' - '.va::str($gradertype),
                        2, 'main', 'heading-'.$gradingarea);
                $gradinginfo = grade_get_grades($this->course->id, 'mod', 'videoassessment',
                        $this->instance, $userid);
                $o .= \html_writer::start_tag('div', array('id' => 'rubrics-'.$gradingarea, 'class' => 'rubrics-page-down'));
                if ($controller = $rubric->get_available_controller($gradingarea)) {
                    $gradeitems = $this->get_grade_items($gradingarea, $userid);
                    foreach ($gradeitems as $gradeitem) {
                        $o .= $controller->render_grade($PAGE, $gradeitem->id, $gradinginfo, '', false);
                    }
                }
                $o .= \html_writer::end_tag('div');
            }
        }
        $o .= \html_writer::end_tag('div');

        $PAGE->requires->js_init_call('M.mod_videoassessment.report_combine_rubrics', null, false,
                $this->va->jsmodule);

        return $o;
    }
}
