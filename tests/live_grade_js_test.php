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
 * Static-contract tests for the live_grade_total AMD module (Item #13).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * The live rubric-total updater is browser JavaScript whose end-to-end
 * behaviour needs Selenium plus a fully populated rubric grading
 * session — a fixture mod_videoassessment has no Behat generator for.
 * To keep the wiring under cheap, container-only regression cover we
 * pin the source contract: the module must read the rubric scores the
 * way Moodle renders them and push the running total into the live
 * display element. The arithmetic itself is covered by
 * tests/rubric_total_test.php, and the ARIA markup by
 * tests/live_grade_aria_test.php.
 */
final class live_grade_js_test extends \basic_testcase {
    /**
     * Read the AMD source from the plugin root.
     *
     * @return string Source contents (line endings normalised to LF).
     */
    private function read_module(): string {
        $path = __DIR__ . '/../amd/src/live_grade_total.js';
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /**
     * The updater must bind to every server-rendered live display via
     * the data-vassmt-live-grade hook (the same attribute assess.php
     * emits).
     *
     * @coversNothing
     */
    public function test_binds_to_live_grade_hook(): void {
        $js = $this->read_module();
        $this->assertStringContainsString(
            '[data-vassmt-live-grade]',
            $js,
            'The module must query the data-vassmt-live-grade displays '
                . 'that assess.php renders.'
        );
    }

    /**
     * It must read the per-level point value out of the gradingform
     * rubric `.scorevalue` span.
     *
     * @coversNothing
     */
    public function test_reads_scorevalue(): void {
        $js = $this->read_module();
        $this->assertStringContainsString(
            '.scorevalue',
            $js,
            'The module must read the rubric point value from the '
                . '.scorevalue span Moodle renders.'
        );
    }

    /**
     * A level counts as selected when it carries Moodle's `.checked`
     * class or aria-checked="true".
     *
     * @coversNothing
     */
    public function test_detects_selected_level(): void {
        $js = $this->read_module();
        $this->assertMatchesRegularExpression(
            "~classList\.contains\('checked'\)~",
            $js,
            'The module must treat a .checked level as selected.'
        );
        $this->assertStringContainsString(
            'aria-checked',
            $js,
            'The module must also accept aria-checked="true" as a '
                . 'selected level (set by Moodle rubric.js).'
        );
    }

    /**
     * The computed total must be written back into the display text.
     *
     * @coversNothing
     */
    public function test_writes_total_to_display(): void {
        $js = $this->read_module();
        $this->assertMatchesRegularExpression(
            '~display\.textContent\s*=\s*format\(~',
            $js,
            'The module must push the formatted total into the live '
                . 'display element.'
        );
    }

    /**
     * Because Moodle rubric.js may toggle the selection without bubbling
     * a click to our root, the updater must also observe class /
     * aria-checked mutations.
     *
     * @coversNothing
     */
    public function test_observes_mutations(): void {
        $js = $this->read_module();
        $this->assertStringContainsString(
            'MutationObserver',
            $js,
            'The module must fall back to a MutationObserver so the total '
                . 'still updates if no click event reaches the root.'
        );
        $this->assertMatchesRegularExpression(
            "~attributeFilter\s*:\s*\[[^\]]*'aria-checked'~s",
            $js,
            'The observer must watch the aria-checked attribute.'
        );
    }
}
