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
 * Behat steps and page resolvers for mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @category   test
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat helpers for mod_videoassessment scenarios.
 *
 * Provides custom page resolvers so a feature can navigate to the
 * activity's secondary action URLs (upload form, assess form, etc.)
 * without hard-coding the cmid + action query string.
 *
 * Recognised page identifiers (use as
 * `Given I am on the "ACTIVITY" "videoassessment > TYPE" page logged in as USER`):
 *
 *   - upload  -> view.php?id={cmid}&action=upload   (the student-facing
 *                video submission form, where the in-browser recording
 *                option with its 2-minute cap label lives)
 *   - assess  -> view.php?id={cmid}&action=assess   (the per-student
 *                rubric grading screen)
 */
class behat_videoassessment extends behat_base {
    /**
     * Resolve a page identifier ("upload" / "assess") to a moodle_url
     * pointing at the matching activity action.
     *
     * @param string $type Page-instance identifier from the feature file.
     * @param string $identifier The videoassessment activity name.
     * @return moodle_url Target URL for the navigation step.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cmid = $this->get_cm_by_videoassessment_name($identifier)->id;
        switch (strtolower($type)) {
            case 'upload':
                return new moodle_url(
                    '/mod/videoassessment/view.php',
                    ['id' => $cmid, 'action' => 'upload']
                );
            case 'assess':
                return new moodle_url(
                    '/mod/videoassessment/view.php',
                    ['id' => $cmid, 'action' => 'assess']
                );
        }
        throw new Exception(
            "Unrecognised mod_videoassessment page instance type: '{$type}'."
        );
    }

    /**
     * Look up the course module record for a videoassessment activity by name.
     *
     * @param string $name The activity name as supplied to the data generator.
     * @return stdClass The course module record (with ->id).
     */
    protected function get_cm_by_videoassessment_name(string $name): stdClass {
        global $DB;
        $va = $DB->get_record(
            'videoassessment',
            ['name' => $name],
            '*',
            MUST_EXIST
        );
        return get_coursemodule_from_instance(
            'videoassessment',
            $va->id,
            0,
            false,
            MUST_EXIST
        );
    }
}
