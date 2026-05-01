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
 * Unit tests for the rubric → assess navigation helper.
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
 * asked for a "Finish making rubric → Go to Assess" button.
 *
 * The contract pinned by this test:
 *
 * - is_videoassessment_rubric_edit_url() returns true when given a
 *   URL pointing at /grade/grading/form/rubric/edit.php with a
 *   component=mod_videoassessment query parameter, false otherwise.
 * - finish_rubric_url() builds the assess URL for a given course-module
 *   id, mirroring the URL produced by va::view_redirect().
 */
final class rubric_navigation_test extends \basic_testcase {
    /**
     * Provider with URLs that should / should not be classified as
     * rubric edit URLs for our plugin.
     *
     * @return array<string, array{string, bool}>
     */
    public static function url_provider(): array {
        return [
            'rubric edit page for our plugin' => [
                '/grade/grading/form/rubric/edit.php?component=mod_videoassessment&contextid=42',
                true,
            ],
            'rubric edit page for our plugin with extra params' => [
                '/grade/grading/form/rubric/edit.php?contextid=42&component=mod_videoassessment&areaid=99',
                true,
            ],
            'rubric edit page for another plugin' => [
                '/grade/grading/form/rubric/edit.php?component=mod_assign&contextid=42',
                false,
            ],
            'rubric manage page (not edit)' => [
                '/grade/grading/manage.php?component=mod_videoassessment&contextid=42',
                false,
            ],
            'totally unrelated URL' => [
                '/mod/videoassessment/view.php?id=1',
                false,
            ],
            'empty URL' => ['', false],
        ];
    }

    /**
     * Confirm the URL classifier returns the expected answer.
     *
     * @dataProvider url_provider
     * @param string $url Input URL.
     * @param bool $expected Expected classification result.
     * @covers \mod_videoassessment\rubric_navigation::is_videoassessment_rubric_edit_url
     */
    public function test_is_videoassessment_rubric_edit_url(string $url, bool $expected): void {
        $this->assertSame(
            $expected,
            rubric_navigation::is_videoassessment_rubric_edit_url($url)
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
}
