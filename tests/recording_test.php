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
 * Unit tests for the in-browser recording configuration.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\recording}.
 *
 * Item #3 of the 2026-04 fix programme. The original "Record New
 * Video" flow let users record indefinitely - field reports said the
 * resulting MediaRecorder blobs sometimes refused to upload, and the
 * customer asked for a clear "max length 2 minutes" indicator on the
 * UI label.
 *
 * The contract pinned by this test:
 *
 * - {@see recording::max_length_seconds} returns the integer 120
 *   (= 2 minutes) and is reachable from JS via a localised helper so
 *   the in-browser recorder and the UI label stay in sync.
 * - {@see recording::label_with_limit} returns a human-readable string
 *   that contains the duration (in minutes), suitable for use as the
 *   radio-button label.
 */
final class recording_test extends \basic_testcase {
    /**
     * The recording length limit must be 120 seconds.
     *
     * @covers \mod_videoassessment\recording::max_length_seconds
     */
    public function test_max_length_seconds_is_120(): void {
        $this->assertSame(120, recording::max_length_seconds());
    }

    /**
     * The label must mention the duration in minutes.
     *
     * @covers \mod_videoassessment\recording::label_with_limit
     */
    public function test_label_with_limit_contains_two_minutes(): void {
        $label = recording::label_with_limit('Record New Video');
        $this->assertStringContainsString('Record New Video', $label);
        $this->assertStringContainsString('2', $label);
        $this->assertStringContainsString('min', $label);
    }
}
