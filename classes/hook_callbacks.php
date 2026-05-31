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
 * Hook callbacks for mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Hook callbacks for mod_videoassessment.
 *
 * @package mod_videoassessment
 */
class hook_callbacks {
    /**
     * Inject the "Finish making rubric -> Go to Assess" button on the
     * rubric edit page when the page belongs to a mod_videoassessment
     * activity. Item #12 of the 2026-04 fix programme.
     *
     * The matching is delegated to
     * {@see rubric_navigation::videoassessment_cmid_from_page()} which
     * uses `$PAGE->pagetype` + `$PAGE->context` rather than the URL
     * query string (the core edit page only carries `?areaid=N` on
     * `$PAGE->url`, so URL-only matching never fires).
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function inject_finish_rubric_button(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE;
        $cmid = rubric_navigation::videoassessment_cmid_from_page(
            (string) $PAGE->pagetype,
            $PAGE->context
        );
        if ($cmid === null) {
            return;
        }
        $assessurl = rubric_navigation::finish_rubric_url($cmid);
        $PAGE->requires->js_call_amd(
            'mod_videoassessment/finish_rubric_button',
            'init',
            [$assessurl->out(false), get_string('finishmakingrubric', 'mod_videoassessment')]
        );
    }
}
