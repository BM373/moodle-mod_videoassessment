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

abstract class page {
    /**
     *
     * @var va
     */
    protected $va;
    /**
     *
     * @var \moodle_url
     */
    protected $url;
    /**
     *
     * @var \mod_videoassessment_renderer|\core_renderer
     */
    protected $output;

    /**
     *
     * @param string $url
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

    public abstract function execute();

    /**
     *
     * @return string
     */
    protected function header() {
        return $this->output->header($this->va);
    }
}
