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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Rubric management class for video assessment grading.
 *
 * This class handles the management of grading managers and controllers
 * for rubric-based assessment across different grading areas and timings.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric {
    /**
     * Collection of grading managers for different areas.
     *
     * Stores grading manager instances indexed by grading area names.
     *
     * @var \stdClass
     */
    private $managers;

    /**
     * Collection of grading controllers for different areas.
     *
     * Stores grading controller instances indexed by grading area names.
     *
     * @var \stdClass
     */
    private $controllers;

    /**
     * Initialize rubric management for video assessment.
     *
     * Sets up grading managers and controllers for all grading areas
     * in the video assessment, optionally filtered by specific areas.
     *
     * @param va $va Video assessment instance object
     * @param array|null $gradingareas Optional array of specific grading areas to initialize
     * @return void
     */
    public function __construct(va $va, array $gradingareas = null) {
        $this->managers = new \stdClass();
        $this->controllers = new \stdClass();

        // Auto-duplicate rubric before initializing controllers.
        // This ensures rubrics are available for all grader types.
        videoassessment_auto_duplicate_rubric($va->context->id);

        foreach ($va->gradingareas as $gradingarea) {
            if ($gradingareas && !in_array($gradingarea, $gradingareas)) {
                continue;
            }

            $this->managers->$gradingarea = get_grading_manager($va->context, 'mod_videoassessment', $gradingarea);
            $this->controllers->$gradingarea = null;
            
            // Check if there's an active method.
            $manager = $this->get_manager($gradingarea);
            $gradingmethod = $manager->get_active_method();
            
            // If no active method but a rubric definition exists, set it to 'rubric'.
            if (!$gradingmethod) {
                try {
                    $controller = $manager->get_controller('rubric');
                    if ($controller) {
                        $isdefined = $controller->is_form_defined();
                        $isavailable = $controller->is_form_available();
                        
                        if ($isdefined && $isavailable) {
                            // Rubric exists and is ready, so set it as the active method.
                            $manager->set_active_method('rubric');
                            $gradingmethod = 'rubric';
                        } else if ($isdefined && !$isavailable) {
                            // Definition exists but not available - might be DRAFT status.
                            // Try to set it anyway - the controller will handle it.
                            $manager->set_active_method('rubric');
                            $gradingmethod = 'rubric';
                        }
                    }
                } catch (Exception $e) {
                    // No rubric available, continue.
                }
            }
            
            if ($gradingmethod) {
                try {
                    $this->controllers->$gradingarea = $manager->get_controller($gradingmethod);
                } catch (Exception $e) {
                    // Controller creation failed, leave as null.
                    $this->controllers->$gradingarea = null;
                }
            }
        }
    }

    /**
     * Get grading manager for a specific grading area.
     *
     * Retrieves the grading manager instance for the specified
     * grading area if it exists.
     *
     * @param string $gradingarea The grading area identifier
     * @return \grading_manager|null Grading manager instance or null if not found
     */
    public function get_manager($gradingarea) {
        if (isset($this->managers->$gradingarea)) {
            return $this->managers->$gradingarea;
        }
        return null;
    }

    /**
     * Get grading controller for a specific grading area.
     *
     * Retrieves the grading controller instance for the specified
     * grading area if it exists and is configured.
     *
     * @param string $gradingarea The grading area identifier
     * @return \gradingform_rubric_controller|null Grading controller instance or null if not found
     */
    public function get_controller($gradingarea) {
        if (isset($this->controllers->$gradingarea)) {
            return $this->controllers->$gradingarea;
        }
        return null;
    }

    /**
     * Get available grading controller for a specific grading area.
     *
     * Retrieves the grading controller instance only if it exists,
     * is configured, and has an available form for grading.
     * Falls back to teacher's rubric if the requested area doesn't have one.
     *
     * @param string $gradingarea The grading area identifier
     * @return \gradingform_rubric_controller|null Available grading controller or null if not available
     */
    public function get_available_controller($gradingarea) {
        $controller = $this->get_controller($gradingarea);
        if ($controller && $controller->is_form_available()) {
            return $controller;
        }
        
        // If controller exists but form is not available, check why.
        if ($controller && $controller->is_form_defined() && !$controller->is_form_available()) {
            // Definition exists but not available - might be DRAFT status.
            // For now, still return it - the form will handle it.
            // TODO: Check if we should allow DRAFT rubrics or require READY status.
            return $controller;
        }
        
        // If controller wasn't created in constructor, try to create it now.
        // This can happen if the active method wasn't set when constructor ran.
        $manager = $this->get_manager($gradingarea);
        if ($manager) {
            // Check if rubric definition exists even if active method isn't set.
            try {
                $rubriccontroller = $manager->get_controller('rubric');
                if ($rubriccontroller && $rubriccontroller->is_form_defined()) {
                    // Rubric definition exists - check if it's available.
                    if ($rubriccontroller->is_form_available()) {
                        // Rubric exists and is ready, set it as active and cache it.
                        $manager->set_active_method('rubric');
                        $this->controllers->$gradingarea = $rubriccontroller;
                        return $rubriccontroller;
                    } else {
                        // Definition exists but not available (DRAFT status).
                        // Set active method anyway and return controller - form might still work.
                        $manager->set_active_method('rubric');
                        $this->controllers->$gradingarea = $rubriccontroller;
                        return $rubriccontroller;
                    }
                }
            } catch (Exception $e) {
                // No rubric available, continue to fallback.
            }
        }
        
        // Fallback: If this is not the teacher area and no rubric exists,
        // try to use the teacher's rubric as a fallback.
        if ($gradingarea != 'beforeteacher' && strpos($gradingarea, 'teacher') === false) {
            $teachermanager = $this->get_manager('beforeteacher');
            if ($teachermanager) {
                $teachercontroller = $this->get_controller('beforeteacher');
                if ($teachercontroller && $teachercontroller->is_form_available()) {
                    // Auto-duplicate the rubric to this area so it's available.
                    videoassessment_auto_duplicate_rubric($teachermanager->get_context()->id);
                    
                    // Reload the controller for this area after duplication.
                    $manager = $this->get_manager($gradingarea);
                    if ($manager) {
                        // Check if rubric is now available.
                        try {
                            $rubriccontroller = $manager->get_controller('rubric');
                            if ($rubriccontroller && $rubriccontroller->is_form_available()) {
                                // Set active method and cache controller.
                                $manager->set_active_method('rubric');
                                $this->controllers->$gradingarea = $rubriccontroller;
                                return $rubriccontroller;
                            }
                        } catch (Exception $e) {
                            // Still not available.
                        }
                    }
                }
            }
        }
        
        return null;
    }
}
