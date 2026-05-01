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
 * Helper for navigating from the rubric edit screen to the assess screen.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Navigation helper for the rubric → assess flow.
 *
 * Item #12 of the 2026-04 fix programme. The customer asked for a
 * one-click "Finish making rubric → Go to Assess" button on the
 * rubric edit page. The page is owned by Moodle core
 * (`/grade/grading/form/rubric/edit.php`); we cannot edit its template,
 * so the button is injected via the Moodle 4.5+ hook system (see
 * `db/hooks.php` and `\mod_videoassessment\hook_callbacks::inject_finish_rubric_button`).
 *
 * To keep the JavaScript tiny and the URL detection unit-testable, the
 * matching logic and URL builder are factored into this class.
 */
final class rubric_navigation {
    /**
     * Decide whether a URL points to the rubric edit form for an
     * activity owned by mod_videoassessment.
     *
     * @param string $url Either an absolute URL or a Moodle relative
     *                    path with optional `?query` string.
     * @return bool
     */
    public static function is_videoassessment_rubric_edit_url(string $url): bool {
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['path'])) {
            return false;
        }
        // The page is /grade/grading/form/rubric/edit.php (or any
        // wwwroot prefix in front). We check for the path tail.
        if (
            substr($parts['path'], -strlen('/grade/grading/form/rubric/edit.php'))
            !== '/grade/grading/form/rubric/edit.php'
        ) {
            return false;
        }
        // The component=mod_videoassessment query parameter scopes the
        // edit form to a specific Moodle component.
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        return ($query['component'] ?? '') === 'mod_videoassessment';
    }

    /**
     * Build the URL of the assess screen for a given course-module id.
     *
     * @param int $cmid Course-module id of the videoassessment activity.
     * @return \moodle_url
     */
    public static function finish_rubric_url(int $cmid): \moodle_url {
        return new \moodle_url(
            '/mod/videoassessment/view.php',
            ['id' => $cmid, 'action' => 'assess']
        );
    }
}
