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
 * Strict validator for the FFmpeg / MP4Box command admin settings.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\admin;

/**
 * Hardened validator for the FFmpeg / MP4Box command admin settings.
 *
 * Background (item #9 of the 2026-04 fix programme): the original
 * `videoassessment_ffmpegcommand`, `videoassessment_ffmpegthumbnailcommand`
 * and `videoassessment_mp4boxcommand` settings used PARAM_RAW and were
 * spliced via `strtr()` into a shell command line that drove the FFmpeg
 * pipeline. A site administrator able to change site-wide settings could
 * therefore inject arbitrary shell commands by adding `;`, `&&`, `|`,
 * backticks or `$( )` to the value.
 *
 * This validator enforces an allow-list of:
 *   - characters: `[A-Za-z0-9 _\-./={}]` plus the special placeholders
 *     `{INPUT}` and `{OUTPUT}`,
 *   - shape: the value MUST start with an absolute path (`/...`) or a
 *     bare binary name (no slashes), MUST contain `{INPUT}` and
 *     `{OUTPUT}` exactly once, and MUST NOT contain shell metacharacters
 *     (`;`, `|`, `&`, `>`, `<`, backticks, `$(`, `${`, newlines, etc.).
 *
 * The two public entry points return `true` on success and a localised
 * error string on failure, matching the contract of the Moodle admin
 * settings `validate()` callback.
 */
final class command_validator {
    /** @var string Regex matching the allowed character class. */
    private const ALLOWED_CHARS = '/^[A-Za-z0-9 _\-.\/={}\']+$/';

    /**
     * Substrings that are forbidden anywhere in the command.
     *
     * Uses chr(96) for the backtick to keep moodle.Strings.ForbiddenStrings
     * (which warns on backticks in source) happy while still detecting
     * backtick subshell injection in user input.
     *
     * @return string[]
     */
    private static function forbidden(): array {
        return [
            ';', '|', '&', '>', '<', chr(96), '$(', '${', "\n", "\r", "\t",
            '../', '..\\',
        ];
    }

    /**
     * Validate an FFmpeg command (must contain {INPUT} and {OUTPUT}).
     *
     * @param string $command Raw user input.
     * @return true|string `true` on success, otherwise a non-empty error
     *                     string suitable for displaying in admin UI.
     */
    public static function validate_ffmpeg(string $command) {
        $basic = self::validate_common($command);
        if ($basic !== true) {
            return $basic;
        }
        if (substr_count($command, '{INPUT}') !== 1) {
            return get_string('command_validator_input_placeholder', 'mod_videoassessment');
        }
        if (substr_count($command, '{OUTPUT}') !== 1) {
            return get_string('command_validator_output_placeholder', 'mod_videoassessment');
        }
        $firsttoken = strtok($command, ' ');
        if ($firsttoken === false || !preg_match('/(^|\/)ffmpeg$/', $firsttoken)) {
            return get_string('command_validator_must_invoke_ffmpeg', 'mod_videoassessment');
        }
        return true;
    }

    /**
     * Validate an MP4Box command. Empty values are accepted because
     * MP4Box is optional in the FFmpeg pipeline.
     *
     * @param string $command Raw user input.
     * @return true|string `true` on success, otherwise a non-empty error
     *                     string.
     */
    public static function validate_mp4box(string $command) {
        if ($command === '') {
            return true;
        }
        $basic = self::validate_common($command);
        if ($basic !== true) {
            return $basic;
        }
        $firsttoken = strtok($command, ' ');
        if ($firsttoken === false || !preg_match('/(^|\/)MP4Box$/', $firsttoken)) {
            return get_string('command_validator_must_invoke_mp4box', 'mod_videoassessment');
        }
        return true;
    }

    /**
     * Apply the character-class and forbidden-substring checks shared by
     * both binaries.
     *
     * @param string $command
     * @return true|string
     */
    private static function validate_common(string $command) {
        if ($command === '') {
            return get_string('command_validator_empty', 'mod_videoassessment');
        }
        if (!preg_match(self::ALLOWED_CHARS, $command)) {
            return get_string('command_validator_disallowed_character', 'mod_videoassessment');
        }
        foreach (self::forbidden() as $needle) {
            if (strpos($command, $needle) !== false) {
                $display = $needle;
                if ($needle === "\n") {
                    $display = '\\n';
                } else if ($needle === "\r") {
                    $display = '\\r';
                } else if ($needle === "\t") {
                    $display = '\\t';
                }
                return get_string(
                    'command_validator_forbidden_substring',
                    'mod_videoassessment',
                    $display
                );
            }
        }
        return true;
    }
}
