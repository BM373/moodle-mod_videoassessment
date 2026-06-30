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
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Navigation helper for the rubric -> assess flow.
 *
 * Item #12 of the 2026-04 fix programme. The customer asked for a
 * one-click "Finish making rubric -> Go to Assess" button on the
 * rubric edit page. That page is owned by Moodle core
 * (`/grade/grading/form/rubric/edit.php`); we cannot edit its
 * template, so the button is injected via the Moodle 4.5+ hook system
 * (see `db/hooks.php` and
 * `\mod_videoassessment\hook_callbacks::inject_finish_rubric_button`).
 *
 * The core edit page calls
 * `$PAGE->set_url(new moodle_url('/grade/grading/form/rubric/edit.php',
 * ['areaid' => $areaid]))` so the only query parameter that actually
 * reaches `$PAGE->url` is `areaid`. We therefore cannot detect "this
 * is our plugin's rubric" by reading the URL string; instead we use
 * the `$PAGE->pagetype` and `$PAGE->context` that Moodle sets at the
 * same time as the URL.
 *
 * Both the page-type matcher and the URL builder are factored into
 * this class so they can be unit-tested in isolation.
 */
final class rubric_navigation {
    /**
     * The page-type Moodle assigns to the rubric edit form.
     *
     * @var string
     */
    const RUBRIC_EDIT_PAGETYPE = 'grade-grading-form-rubric-edit';

    /**
     * The page-type Moodle assigns to the advanced-grading management
     * page (`/grade/grading/manage.php`), where the rubric Edit /
     * Delete action boxes live.
     *
     * @var string
     */
    const GRADING_MANAGE_PAGETYPE = 'grade-grading-manage';

    /**
     * Decide whether a pagetype identifies the rubric edit form.
     *
     * @param string $pagetype Value of `$PAGE->pagetype`.
     * @return bool
     */
    public static function is_rubric_edit_pagetype(string $pagetype): bool {
        return $pagetype === self::RUBRIC_EDIT_PAGETYPE;
    }

    /**
     * Decide whether a pagetype identifies the advanced-grading
     * management page.
     *
     * @param string $pagetype Value of `$PAGE->pagetype`.
     * @return bool
     */
    public static function is_grading_manage_pagetype(string $pagetype): bool {
        return $pagetype === self::GRADING_MANAGE_PAGETYPE;
    }

    /**
     * Resolve a course-module context to the cmid of the
     * videoassessment activity that owns it, or null.
     *
     * @param \context|null $context Page context.
     * @return int|null cmid, or null when the context is not a
     *                  videoassessment course-module context.
     */
    private static function videoassessment_cmid_from_context(?\context $context): ?int {
        if (!$context || $context->contextlevel !== CONTEXT_MODULE) {
            return null;
        }
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== 'videoassessment') {
            return null;
        }
        return (int) $cm->id;
    }

    /**
     * Decide whether the current page is the rubric edit form for a
     * mod_videoassessment activity, and return the matching cmid.
     *
     * Combines the pagetype check with a context-level / modname check
     * so the button is injected only when:
     *
     * - The page is the rubric edit form
     *   (`grade-grading-form-rubric-edit`).
     * - The page context is a course-module context.
     * - The course-module behind that context belongs to a
     *   videoassessment activity (not mod_assign, mod_forum, etc.).
     *
     * Returns `null` when any check fails so the caller can early-exit
     * without ever calling `$PAGE->requires->js_call_amd()`.
     *
     * @param string $pagetype Value of `$PAGE->pagetype`.
     * @param \context|null $context Value of `$PAGE->context`.
     * @return int|null The cmid of the videoassessment activity that
     *                  owns this rubric, or null when the button must
     *                  not be injected.
     */
    public static function videoassessment_cmid_from_page(string $pagetype, ?\context $context): ?int {
        if (!self::is_rubric_edit_pagetype($pagetype)) {
            return null;
        }
        return self::videoassessment_cmid_from_context($context);
    }

    /**
     * Same as {@see videoassessment_cmid_from_page()} but for the
     * advanced-grading management page (where the Edit / Delete action
     * boxes are), so the "Finish making rubric" action box can be added
     * alongside them.
     *
     * @param string $pagetype Value of `$PAGE->pagetype`.
     * @param \context|null $context Value of `$PAGE->context`.
     * @return int|null cmid, or null when the box must not be injected.
     */
    public static function videoassessment_cmid_for_manage_page(string $pagetype, ?\context $context): ?int {
        if (!self::is_grading_manage_pagetype($pagetype)) {
            return null;
        }
        return self::videoassessment_cmid_from_context($context);
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

    /**
     * Build the URL of the activity's main view page for a given
     * course-module id (no action), used by the management-page
     * "Finish making rubric" action box.
     *
     * @param int $cmid Course-module id of the videoassessment activity.
     * @return \moodle_url
     */
    public static function activity_view_url(int $cmid): \moodle_url {
        return new \moodle_url('/mod/videoassessment/view.php', ['id' => $cmid]);
    }
}
