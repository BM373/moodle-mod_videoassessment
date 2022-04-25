<?php
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
