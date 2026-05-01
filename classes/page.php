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

/**
 * Abstract base class for video assessment page controllers.
 *
 * This abstract class provides common functionality for all video assessment
 * page controllers including initialization, authentication, and rendering.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class page {
    /**
     * Video assessment instance object.
     *
     * Contains the main video assessment functionality and data.
     *
     * @var va
     */
    protected $va;

    /**
     * Current page URL object.
     *
     * Stores the Moodle URL for the current page with parameters.
     *
     * @var \moodle_url
     */
    protected $url;

    /**
     * Page renderer instance.
     *
     * Handles the rendering of page content and templates.
     *
     * @var \mod_videoassessment_renderer|\core_renderer
     */
    protected $output;

    /**
     * Initialize the page controller with required parameters.
     *
     * Sets up authentication, course module context, and initializes
     * the video assessment instance and page renderer.
     *
     * @param string $url Base URL for the page
     * @throws \moodle_exception If course module or course not found
     * @return void
     */
    public function __construct($url) {
        global $DB, $PAGE;

        $cmid = required_param('id', PARAM_INT);
        $cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cmid);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        require_login($course, true, $cm);

        $PAGE->set_title($cm->name);
        $PAGE->set_heading($cm->name);

        $this->va = new va($context, $cm, $course);
        $this->url = new \moodle_url($url, array('id' => $cm->id));
        $PAGE->set_url($this->url);
        $this->output = $PAGE->get_renderer('mod_videoassessment');
    }

    /**
     * Execute the main page logic.
     *
     * Abstract method that must be implemented by concrete page classes
     * to define the specific functionality for each page type.
     *
     * @return void
     */
    abstract public function execute();

    /**
     * Generate the page header HTML.
     *
     * Creates and returns the standard page header using the
     * video assessment renderer.
     *
     * @return string HTML content for the page header
     */
    protected function header() {
        return $this->output->header($this->va);
    }
}
