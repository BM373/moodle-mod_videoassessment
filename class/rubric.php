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

require_once $CFG->dirroot . '/grade/grading/lib.php';

class rubric {
    /**
     *
     * @var \stdClass
     */
    private $managers;
    /**
     *
     * @var \stdClass
     */
    private $controllers;

    public function __construct(va $va, array $gradingareas = null) {
        $this->managers = new \stdClass();
        $this->controllers = new \stdClass();

        foreach ($va->gradingareas as $gradingarea) {
            if ($gradingareas && !in_array($gradingarea, $gradingareas)) {
                continue;
            }

            $this->managers->$gradingarea = get_grading_manager($va->context, 'mod_videoassessment', $gradingarea);
            $this->controllers->$gradingarea = null;
            if ($gradingmethod = $this->get_manager($gradingarea)->get_active_method()) {
                $this->controllers->$gradingarea = $this->get_manager($gradingarea)->get_controller($gradingmethod);
            }
        }
    }

    /**
     *
     * @param string $gradingarea
     * @return \grading_manager
     */
    public function get_manager($gradingarea) {
        if (isset($this->managers->$gradingarea)) {
            return $this->managers->$gradingarea;
        }
        return null;
    }

    /**
     *
     * @param string $gradingarea
     * @return \gradingform_rubric_controller
     */
    public function get_controller($gradingarea) {
        if (isset($this->controllers->$gradingarea)) {
            return $this->controllers->$gradingarea;
        }
        return null;
    }

    /**
     *
     * @param string $gradingarea
     * @return \gradingform_rubric_controller
     */
    public function get_available_controller($gradingarea) {
        if ($controller = $this->get_controller($gradingarea) and $controller->is_form_available()) {
            return $controller;
        }
        return null;
    }
}
