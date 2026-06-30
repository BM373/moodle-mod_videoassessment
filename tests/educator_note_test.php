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
 * Educator landscape-recording note tests (Item #4).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for the educator landscape-recording note (Item #4).
 *
 * The PDF asks for a note on the assess screen advising educators:
 * "for best results advise learners to always record videos with
 * smartphone in landscape position (horizontal)". Reviewer Brendon
 * confirmed the Shorts embed / thumbnail work but reported he
 * "can't find message to tell students to record in landscape" — i.e.
 * the note was never added. These tests pin (a) the language string
 * exists in English and Japanese, and (b) the assess view renders it.
 */
final class educator_note_test extends \advanced_testcase {
    /**
     * The English string must exist and mention landscape.
     *
     * @coversNothing
     */
    public function test_landscape_note_string_en(): void {
        $note = get_string_manager()->get_string(
            'educatornote_landscape',
            'videoassessment',
            null,
            'en'
        );
        $this->assertNotEmpty($note);
        $this->assertStringContainsStringIgnoringCase('landscape', $note);
    }

    /**
     * The Japanese string must also exist (non-empty, distinct key).
     *
     * @coversNothing
     */
    public function test_landscape_note_string_ja(): void {
        $note = get_string_manager()->get_string(
            'educatornote_landscape',
            'videoassessment',
            null,
            'ja'
        );
        $this->assertNotEmpty($note);
        // Must not fall back to the bracketed [[key]] placeholder.
        $this->assertStringNotContainsString('[[', $note);
    }

    /**
     * The activity entry view (view_main) must reference the note
     * string so a teacher sees it without having to drill into a
     * per-student assess view. Static-contract check against the
     * source: educatornote_landscape must be emitted from the
     * is_teacher() branch of view_main().
     *
     * @coversNothing
     */
    public function test_view_main_renders_landscape_note(): void {
        $src = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString(
            'educatornote_landscape',
            $src,
            'classes/va.php must output the educatornote_landscape string '
                . 'so the advice is visible on the activity entry page.'
        );
        // Hard-pin location: the string must be reached from view_main,
        // which is where teachers land by default.
        $offsetnote = strpos($src, 'educatornote_landscape');
        $offsetmain = strpos($src, 'function view_main');
        $this->assertNotFalse($offsetnote);
        $this->assertNotFalse($offsetmain);
        $this->assertGreaterThan(
            $offsetmain,
            $offsetnote,
            'educatornote_landscape must be emitted from view_main() (not '
                . 'only from the deeper per-student view_assess).'
        );
    }
}
