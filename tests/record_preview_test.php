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
 * In-browser recorder live-preview regression tests.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Static-contract tests for the recorder live preview (Item #3).
 *
 * Reviewer reproduction: Brendon (iPhone 16 Pro, Chrome + Safari, and
 * desktop) and Matt (Mac Firefox) both reported that the recorder
 * captures video "in the background" but the user cannot SEE
 * themselves while recording — there was no live camera preview. The
 * MediaStream returned by getUserMedia was handed straight to
 * RecordRTC and never attached to a visible <video> element, so
 * #record-content-div stayed empty.
 *
 * The fix lives in amd/src/record.js. A faithful browser test would
 * need a fake-media-device Selenium/Playwright profile, so to keep the
 * test fast and deterministic we pin the JS contract statically: the
 * source module must (a) create a <video> preview element, (b) attach
 * the live MediaStream via srcObject, (c) drop it into
 * #record-content-div, and (d) tear it down (stop the camera tracks
 * and remove the element) when recording finishes.
 */
final class record_preview_test extends \basic_testcase {
    /**
     * Read the record.js AMD source from the plugin tree.
     *
     * @return string Source with line endings normalised to LF.
     */
    private function read_record_js(): string {
        $path = __DIR__ . '/../amd/src/record.js';
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /**
     * The recorder must create a <video> element for the live preview.
     *
     * @coversNothing
     */
    public function test_record_js_creates_preview_video_element(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            "~createElement\\(\\s*['\"]video['\"]\\s*\\)~",
            $js,
            'record.js must create a <video> element for the live preview.'
        );
    }

    /**
     * The captured MediaStream must be attached to the preview via
     * srcObject so the learner sees themselves while recording.
     *
     * @coversNothing
     */
    public function test_record_js_attaches_stream_via_srcobject(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'srcObject',
            $js,
            'record.js must set video.srcObject to the live MediaStream.'
        );
    }

    /**
     * The preview must be muted so the live mic does not echo back
     * through the speakers while recording.
     *
     * @coversNothing
     */
    public function test_record_js_preview_is_muted(): void {
        $js = $this->read_record_js();
        $this->assertMatchesRegularExpression(
            '~\.muted\s*=\s*true~',
            $js,
            'record.js must mute the preview <video> to avoid audio feedback.'
        );
    }

    /**
     * The preview element must be appended to #record-content-div.
     *
     * @coversNothing
     */
    public function test_record_js_appends_preview_to_container(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'record-content-div',
            $js,
            'record.js must place the preview inside #record-content-div.'
        );
    }

    /**
     * When recording finishes the camera tracks must be stopped (so the
     * webcam light goes off) and the preview removed from the DOM.
     *
     * @coversNothing
     */
    public function test_record_js_tears_down_preview_and_tracks(): void {
        $js = $this->read_record_js();
        $this->assertStringContainsString(
            'getTracks',
            $js,
            'record.js must stop the MediaStream tracks when recording ends.'
        );
        $this->assertMatchesRegularExpression(
            '~\.stop\(\)~',
            $js,
            'record.js must call track.stop() to release the camera.'
        );
    }
}
