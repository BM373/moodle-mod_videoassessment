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
 * Smartphone-UI regression tests.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * CSS-level smoke tests for the smartphone UX work.
 *
 * Item #7 of the 2026-04 fix programme. SGU's recordings showed two
 * concrete smartphone problems:
 *
 *   a) The floating video container did not respect the iOS safe-area
 *      insets (notch / Home indicator), so its bottom edge landed
 *      under the on-screen toolbar.
 *   b) When the teacher tapped a `.remark textarea` (per-criterion
 *      feedback) or the final feedback editor on a phone, the iOS
 *      keyboard pushed the field out of the viewport and the text
 *      went behind the keyboard.
 *
 * The fix is mostly CSS, so a Behat regression test would require a
 * full mobile-emulating Playwright run. To keep the test fast we
 * settle for a static contract test: the css files must declare the
 * specific rules that implement (a) and (b).
 */
final class mobile_ui_test extends \basic_testcase {
    /**
     * Read a CSS file from the plugin root.
     *
     * @param string $relative Filename relative to the plugin root.
     * @return string CSS contents (with line endings normalised to LF).
     */
    private function read_css(string $relative): string {
        $path = __DIR__ . '/../' . $relative;
        $css = file_get_contents($path);
        return str_replace("\r\n", "\n", $css);
    }

    /**
     * The floating video container must respect iOS safe-area insets.
     *
     * @coversNothing
     */
    public function test_assess_css_has_safe_area_inset(): void {
        $css = $this->read_css('assess.css');
        $this->assertStringContainsString(
            'env(safe-area-inset',
            $css,
            'assess.css must reference env(safe-area-inset-*) so the '
                . 'floating video container does not slide under the '
                . 'iOS Home indicator / notch.'
        );
    }

    /**
     * A media query for phone-sized viewports must be declared.
     *
     * @coversNothing
     */
    public function test_assess_css_has_phone_breakpoint(): void {
        $css = $this->read_css('assess.css');
        $this->assertMatchesRegularExpression(
            '~@media\s*\([^)]*max-width:\s*768px~i',
            $css,
            'assess.css must declare a @media (max-width: 768px) breakpoint '
                . 'for the smartphone layout.'
        );
    }

    /**
     * The remark textareas must keep themselves visible when focused
     * (e.g. via `scroll-margin-top` so the iOS keyboard does not hide
     * them).
     *
     * @coversNothing
     */
    public function test_assess_css_handles_textarea_focus(): void {
        $css = $this->read_css('assess.css');
        $this->assertMatchesRegularExpression(
            '~\.remark\s+textarea[^{]*\{[^}]*scroll-margin~is',
            $css,
            'assess.css must set a scroll-margin on .remark textarea so '
                . 'the iOS keyboard does not hide the focused field.'
        );
    }
}
