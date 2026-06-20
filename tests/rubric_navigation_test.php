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
 * Unit tests for the rubric -> assess navigation helper.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\rubric_navigation}.
 *
 * Item #12 of the 2026-04 fix programme: there is no easy way to get
 * from the rubric edit screen to the assess screen. The customer
 * asked for a "Finish making rubric -> Go to Assess" button.
 *
 * The contract pinned by this test:
 *
 * - is_rubric_edit_pagetype() returns true only for the core rubric
 *   edit page-type (`grade-grading-form-rubric-edit`).
 * - videoassessment_cmid_from_page() returns the cmid only when the
 *   page is the rubric edit form AND the page context is a
 *   videoassessment activity's coursemodule context; null otherwise.
 * - finish_rubric_url() builds the assess URL for a given course-
 *   module id, mirroring the URL produced by va::view_redirect().
 */
final class rubric_navigation_test extends \advanced_testcase {
    /**
     * Provider with pagetype strings to classify.
     *
     * @return array<string, array{string, bool}>
     */
    public static function pagetype_provider(): array {
        return [
            'core rubric edit pagetype' => ['grade-grading-form-rubric-edit', true],
            'guide edit pagetype' => ['grade-grading-form-guide-edit', false],
            'rubric manage pagetype' => ['grade-grading-manage', false],
            'view pagetype' => ['mod-videoassessment-view', false],
            'empty pagetype' => ['', false],
            // Boundary: pagetype prefix match must not fire.
            'edit-suffix lookalike' => ['grade-grading-form-rubric-edit-extra', false],
            // Boundary: trailing whitespace must not match.
            'trailing whitespace' => ['grade-grading-form-rubric-edit ', false],
            // Boundary: case sensitivity (Moodle pagetypes are lowercase).
            'uppercase variant' => ['GRADE-GRADING-FORM-RUBRIC-EDIT', false],
        ];
    }

    /**
     * Confirm the pagetype classifier returns the expected answer.
     *
     * @dataProvider pagetype_provider
     * @param string $pagetype Input pagetype string.
     * @param bool $expected Expected classification result.
     * @covers \mod_videoassessment\rubric_navigation::is_rubric_edit_pagetype
     */
    public function test_is_rubric_edit_pagetype(string $pagetype, bool $expected): void {
        $this->assertSame(
            $expected,
            rubric_navigation::is_rubric_edit_pagetype($pagetype)
        );
    }

    /**
     * Helper: create a real videoassessment activity and return its
     * course-module record + context.
     *
     * @return array{cm:\stdClass,context:\context_module}
     */
    private function make_va_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(
            'videoassessment',
            ['course' => $course->id, 'name' => 'Speaking task']
        );
        $cm = get_coursemodule_from_instance(
            'videoassessment',
            $activity->id,
            $course->id,
            false,
            MUST_EXIST
        );
        $context = \context_module::instance($cm->id);
        return compact('cm', 'context');
    }

    /**
     * Right pagetype + videoassessment module context -> returns cmid.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_happy_path(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'context' => $context] = $this->make_va_activity();
        $this->assertSame(
            (int) $cm->id,
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-form-rubric-edit',
                $context
            )
        );
    }

    /**
     * Wrong pagetype + valid videoassessment context -> null. Guards
     * against firing the button on the rubric manage / view pages.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_wrong_pagetype(): void {
        $this->resetAfterTest();
        ['context' => $context] = $this->make_va_activity();
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-manage',
                $context
            )
        );
    }

    /**
     * Right pagetype + course-level context -> null. The rubric edit
     * page can in principle be opened against a course-level grading
     * area; we only want the button for the activity-level one.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_course_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-form-rubric-edit',
                $context
            )
        );
    }

    /**
     * Right pagetype + system context -> null. Defends against the
     * site-administration rubric template area firing the button.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_system_context(): void {
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-form-rubric-edit',
                \context_system::instance()
            )
        );
    }

    /**
     * Right pagetype + null context -> null. The hook fires very
     * early in the render pipeline; if context happens to be unset
     * we must not blow up with a TypeError.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_null_context(): void {
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-form-rubric-edit',
                null
            )
        );
    }

    /**
     * Right pagetype + module context of a *different* activity
     * (assign in this case) -> null. The button must only appear for
     * mod_videoassessment rubrics, not for any other plugin that
     * uses advanced grading.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_from_page
     */
    public function test_videoassessment_cmid_from_page_other_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module(
            'assign',
            ['course' => $course->id]
        );
        $cm = get_coursemodule_from_instance(
            'assign',
            $assign->id,
            $course->id,
            false,
            MUST_EXIST
        );
        $context = \context_module::instance($cm->id);
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_from_page(
                'grade-grading-form-rubric-edit',
                $context
            )
        );
    }

    /**
     * Confirm finish_rubric_url returns the correct assess URL.
     *
     * @covers \mod_videoassessment\rubric_navigation::finish_rubric_url
     */
    public function test_finish_rubric_url(): void {
        $url = rubric_navigation::finish_rubric_url(123);
        $this->assertStringContainsString('/mod/videoassessment/view.php', $url->out_as_local_url(false));
        $params = $url->params();
        $this->assertSame('123', (string) $params['id']);
        $this->assertSame('assess', $params['action']);
    }

    /**
     * Boundary: id=0 (degenerate but the helper should still return a
     * URL; downstream view.php will reject id=0 with an
     * `invaliddata` exception).
     *
     * @covers \mod_videoassessment\rubric_navigation::finish_rubric_url
     */
    public function test_finish_rubric_url_with_id_zero(): void {
        $url = rubric_navigation::finish_rubric_url(0);
        $params = $url->params();
        $this->assertSame('0', (string) $params['id']);
        $this->assertSame('assess', $params['action']);
    }

    /**
     * Boundary: large id (within PHP_INT_MAX); confirm there is no
     * silent integer overflow.
     *
     * @covers \mod_videoassessment\rubric_navigation::finish_rubric_url
     */
    public function test_finish_rubric_url_with_large_id(): void {
        $url = rubric_navigation::finish_rubric_url(2147483647);
        $params = $url->params();
        $this->assertSame('2147483647', (string) $params['id']);
    }

    // Advanced-grading management page (the Edit / Delete action boxes).

    /**
     * The manage-page matcher fires only on the manage pagetype, for a
     * videoassessment module context.
     *
     * @covers \mod_videoassessment\rubric_navigation::is_grading_manage_pagetype
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_for_manage_page
     */
    public function test_manage_page_matcher_happy_path(): void {
        $this->resetAfterTest();
        ['cm' => $cm, 'context' => $context] = $this->make_va_activity();

        $this->assertTrue(rubric_navigation::is_grading_manage_pagetype('grade-grading-manage'));
        $this->assertSame(
            (int) $cm->id,
            rubric_navigation::videoassessment_cmid_for_manage_page('grade-grading-manage', $context)
        );
    }

    /**
     * The manage matcher must not fire on the rubric edit pagetype, on
     * a course-level context, or on a non-videoassessment module — the
     * action box belongs only on our activity's manage page.
     *
     * @covers \mod_videoassessment\rubric_navigation::videoassessment_cmid_for_manage_page
     */
    public function test_manage_page_matcher_negatives(): void {
        $this->resetAfterTest();
        ['context' => $context] = $this->make_va_activity();

        // Wrong pagetype (the edit form) -> null.
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_for_manage_page('grade-grading-form-rubric-edit', $context)
        );

        // Course-level context -> null.
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_for_manage_page(
                'grade-grading-manage',
                \context_course::instance($course->id)
            )
        );

        // Null context -> null (no TypeError).
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_for_manage_page('grade-grading-manage', null)
        );

        // A different activity (mod_assign) -> null.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assigncm = get_coursemodule_from_instance('assign', $assign->id, $course->id, false, MUST_EXIST);
        $this->assertNull(
            rubric_navigation::videoassessment_cmid_for_manage_page(
                'grade-grading-manage',
                \context_module::instance($assigncm->id)
            )
        );
    }

    /**
     * The management-page action box links to the plain activity view
     * (view.php?id=cmid, no action), as specified.
     *
     * @covers \mod_videoassessment\rubric_navigation::activity_view_url
     */
    public function test_activity_view_url(): void {
        $url = rubric_navigation::activity_view_url(2);
        $this->assertStringContainsString('/mod/videoassessment/view.php', $url->out_as_local_url(false));
        $params = $url->params();
        $this->assertSame('2', (string) $params['id']);
        $this->assertArrayNotHasKey(
            'action',
            $params,
            'The manage-page box goes to the plain activity view, not the assess action.'
        );
    }

    /**
     * Static wiring guards: the hook injects the manage-page AMD module
     * with the plain-view URL + short label, and the AMD module builds
     * a three-star action box inserted into the core .actions row.
     *
     * @coversNothing
     */
    public function test_manage_button_wiring(): void {
        $hook = file_get_contents(__DIR__ . '/../classes/hook_callbacks.php');
        $this->assertStringContainsString(
            "'mod_videoassessment/finish_rubric_manage_button'",
            $hook,
            'The hook must queue the manage-page AMD module.'
        );
        $this->assertStringContainsString(
            'activity_view_url(',
            $hook,
            'The manage box must navigate to the plain activity view.'
        );
        $this->assertStringContainsString(
            "get_string('finishmakingrubricaction'",
            $hook,
            'The manage box must use the short "Finish making rubric" label.'
        );

        $js = file_get_contents(__DIR__ . '/../amd/src/finish_rubric_manage_button.js');
        $this->assertStringContainsString(
            "querySelector('#region-main .actions')",
            $js,
            'The box must be inserted into the core grading .actions row.'
        );
        $this->assertStringContainsString(
            'fa fa-star',
            $js,
            'The icon must be three stars.'
        );
        $this->assertStringContainsString(
            'action btn btn-lg',
            $js,
            'The box must reuse the core action-box classes so the card style matches.'
        );
    }
}
