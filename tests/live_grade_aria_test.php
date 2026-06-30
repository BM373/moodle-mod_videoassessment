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
 * Accessibility contract for the live rubric-total indicator (Item #13).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * The live-updating "current grade" indicator changes its text content
 * via JavaScript as the teacher clicks rubric levels. For a screen
 * reader to announce that change, the element must be an ARIA live
 * region. The form that renders it (classes/form/assess.php) is awkward
 * to instantiate in isolation, so this is a static-contract test: the
 * source must declare the live region on the live-grade-total span.
 */
final class live_grade_aria_test extends \basic_testcase {
    /**
     * Read the assess form source from the plugin root.
     *
     * @return string Source contents (line endings normalised to LF).
     */
    private function read_assess_form(): string {
        $path = __DIR__ . '/../classes/form/assess.php';
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /**
     * The live-grade-total span must be marked aria-live="polite" so the
     * grade is announced without stealing focus from the rubric.
     *
     * @coversNothing
     */
    public function test_live_total_is_polite_live_region(): void {
        $src = $this->read_assess_form();
        $this->assertMatchesRegularExpression(
            "~'live-grade-total'.*?'aria-live'\s*=>\s*'polite'~s",
            $src,
            "The live-grade-total span must declare aria-live='polite' so "
                . 'screen readers announce the running total.'
        );
    }

    /**
     * aria-atomic="true" makes the reader announce the whole value
     * ("4 / 8 (50%)") rather than just the changed characters.
     *
     * @coversNothing
     */
    public function test_live_total_is_atomic(): void {
        $src = $this->read_assess_form();
        $this->assertMatchesRegularExpression(
            "~'live-grade-total'.*?'aria-atomic'\s*=>\s*'true'~s",
            $src,
            "The live-grade-total span must declare aria-atomic='true' so "
                . 'the entire total is announced as one unit.'
        );
    }
}
