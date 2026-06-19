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
 * Contract tests for the upload-screen help strings and labels (#1).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Item #1 follow-up (2026-06 feedback round).
 *
 * The French colleagues asked for: the visible external-link labels to
 * stop showing "YouTube" (Europeans dislike seeing the word; the
 * examples belong in the help text); the "Upload a Video" help to
 * mention phone / digital-camera footage; and the "Record New Video"
 * help to explain the in-browser recorder and its two-minute limit
 * instead of re-describing all three options.
 *
 * These tests pin the resulting contract so a later edit cannot
 * silently reintroduce the visible "YouTube" labels or revert the help
 * rewrites. The language strings are read straight from the EN / JA
 * lang files (the same approach the other contract tests use) so the
 * result does not depend on the PHPUnit lang cache being fresh.
 */
final class upload_help_strings_test extends \basic_testcase {
    /**
     * Load a plugin lang pack into a plain array.
     *
     * @param string $lang 'en' or 'ja'.
     * @return array<string, string>
     */
    private function load_strings(string $lang): array {
        $string = [];
        require(__DIR__ . "/../lang/{$lang}/videoassessment.php");
        return $string;
    }

    /**
     * Both visible "allow external links" labels must no longer carry a
     * parenthetical YouTube example; the examples live in the help text.
     *
     * @coversNothing
     */
    public function test_external_link_labels_drop_visible_youtube(): void {
        foreach (['en', 'ja'] as $lang) {
            $strings = $this->load_strings($lang);
            foreach (['allowexternallinks', 'allowyoutube'] as $key) {
                $label = $strings[$key];
                $this->assertStringNotContainsStringIgnoringCase(
                    'youtube',
                    $label,
                    "The visible '{$key}' label ({$lang}) must not name "
                        . 'YouTube — the examples belong in the help text.'
                );
                $this->assertStringNotContainsString(
                    '(',
                    $label,
                    "The visible '{$key}' label ({$lang}) must not carry "
                        . 'a parenthetical example.'
                );
            }
        }
    }

    /**
     * The external-link help must list the new platforms and answer
     * Don's "where do I configure the server?" question by stating that
     * no per-server configuration is needed.
     *
     * @coversNothing
     */
    public function test_external_link_help_lists_platforms(): void {
        $help = $this->load_strings('en')['uploadyoutube_help'];
        foreach (['PeerTube', 'Esup-Pod', 'Opencast'] as $platform) {
            $this->assertStringContainsString(
                $platform,
                $help,
                "The external-link help must mention {$platform}."
            );
        }
        $this->assertStringContainsStringIgnoringCase(
            'no server configuration',
            $help,
            'The help must tell the user no server configuration is '
                . 'needed (Don asked where the server is configured).'
        );
    }

    /**
     * The "Upload a Video File" help must mention the real-world
     * sources Don asked for: a mobile phone camera and a digital video
     * camera.
     *
     * @coversNothing
     */
    public function test_upload_file_help_mentions_camera_sources(): void {
        $help = $this->load_strings('en')['uploadfile_help'];
        $this->assertStringContainsStringIgnoringCase('mobile phone camera', $help);
        $this->assertStringContainsStringIgnoringCase('digital video camera', $help);
    }

    /**
     * The "Record New Video" help must describe the in-browser recorder
     * (camera permission, two-minute limit) and must NOT just
     * re-describe the three upload options as it used to.
     *
     * @coversNothing
     */
    public function test_record_help_describes_the_recorder(): void {
        $help = $this->load_strings('en')['recordnewvideo_help'];
        $this->assertStringContainsStringIgnoringCase('two minutes', $help);
        $this->assertStringContainsStringIgnoringCase('camera', $help);
        $this->assertStringNotContainsString(
            'Select "Insert External Video Link"',
            $help,
            'The record help must describe the recorder, not list the '
                . 'three options again.'
        );
    }

    /**
     * The two-minute limit named in the help must match the recorder's
     * actual enforced cap, so the help cannot drift from the code.
     *
     * @coversNothing
     */
    public function test_record_help_two_minute_limit_matches_code(): void {
        $this->assertSame(
            120,
            recording::max_length_seconds(),
            'The recorder cap must stay at 120s to match the help text '
                . '("two minutes") and the radio label.'
        );
    }

    /**
     * The Japanese pack must localise the same three help strings (no
     * silent fallback to English on the upload screen).
     *
     * @coversNothing
     */
    public function test_japanese_help_strings_are_localised(): void {
        $en = $this->load_strings('en');
        $ja = $this->load_strings('ja');
        foreach (['uploadfile_help', 'recordnewvideo_help', 'uploadyoutube_help'] as $key) {
            $this->assertArrayHasKey(
                $key,
                $ja,
                "The JA pack must define '{$key}' (no fallback to EN)."
            );
            $this->assertNotSame(
                $en[$key],
                $ja[$key],
                "The JA '{$key}' must be translated, not a copy of EN."
            );
            // The string must actually contain Japanese script
            // (hiragana, katakana or CJK ideographs), not just be a
            // different ASCII string.
            $this->assertMatchesRegularExpression(
                '~[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]~u',
                $ja[$key],
                "The JA '{$key}' should contain Japanese characters."
            );
        }
    }
}
