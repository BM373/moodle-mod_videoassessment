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
 * Unit tests for the ffmpeg path / version helpers in locallib.php.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

/**
 * Tests for the four ffmpeg helpers introduced by upstream PR
 * BM373/moodle-mod_videoassessment#57.
 *
 * The helpers expose two distinct contracts:
 *
 *  - When the admin has stored a value in `videoassessment_ffmpegcommand`,
 *    the helpers return the path/escaped command derived from that
 *    config without touching the filesystem. This branch is fully
 *    deterministic and is exercised by the bulk of the tests below.
 *  - When the config is empty, the helpers fall back to a system-level
 *    binary lookup. That branch is OS- and runtime-dependent so we
 *    only assert it returns a sensible value (string when ffmpeg is on
 *    PATH, false otherwise) without pinning a specific path.
 */
final class locallib_ffmpeg_test extends \advanced_testcase {
    /**
     * `videoassessment_get_ffmpeg_path()` returns the first whitespace-
     * separated token of the configured command, which is the binary
     * path the admin filled in. The trailing `-i {INPUT} ...` arguments
     * must NOT leak into the returned string.
     *
     * @covers ::videoassessment_get_ffmpeg_path
     */
    public function test_get_ffmpeg_path_from_configured_command(): void {
        $this->resetAfterTest();
        set_config('ffmpegcommand', '/usr/bin/ffmpeg -i {INPUT} {OUTPUT}', 'videoassessment');
        $this->assertSame('/usr/bin/ffmpeg', videoassessment_get_ffmpeg_path());
    }

    /**
     * Same path-extraction contract for a longer, modern-style command
     * with stream specifiers and `-movflags +faststart`. The space
     * after the binary path is the only thing that matters; the rest
     * of the command may contain colons / pluses / commas without
     * leaking into the returned path.
     *
     * @covers ::videoassessment_get_ffmpeg_path
     */
    public function test_get_ffmpeg_path_handles_complex_command(): void {
        $this->resetAfterTest();
        set_config(
            'ffmpegcommand',
            '/opt/ffmpeg/bin/ffmpeg -i {INPUT} -c:v libx264 -profile:v high'
                . ' -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart {OUTPUT}',
            'videoassessment'
        );
        $this->assertSame('/opt/ffmpeg/bin/ffmpeg', videoassessment_get_ffmpeg_path());
    }

    /**
     * Boundary: a bare-binary command (no leading slash) still
     * extracts to the binary name. This is the form Windows admins
     * typically use when ffmpeg is on PATH.
     *
     * @covers ::videoassessment_get_ffmpeg_path
     */
    public function test_get_ffmpeg_path_with_bare_binary(): void {
        $this->resetAfterTest();
        set_config('ffmpegcommand', 'ffmpeg -i {INPUT} {OUTPUT}', 'videoassessment');
        $this->assertSame('ffmpeg', videoassessment_get_ffmpeg_path());
    }

    /**
     * When the config is empty the helper falls back to a system
     * lookup. The result is OS- and runtime-dependent so we only
     * assert the *type* of the return value: either a string path or
     * boolean false.
     *
     * @covers ::videoassessment_get_ffmpeg_path
     */
    public function test_get_ffmpeg_path_with_no_config_returns_string_or_false(): void {
        $this->resetAfterTest();
        // Use unset_config so the empty-string check inside the helper
        // takes the auto-detect branch (an empty config row would be
        // "configured but empty" which set_config('') leaves intact).
        unset_config('ffmpegcommand', 'videoassessment');
        $result = videoassessment_get_ffmpeg_path();
        $this->assertTrue(
            is_string($result) || $result === false,
            'Expected string or false, got ' . gettype($result)
        );
    }

    /**
     * `videoassessment_is_ffmpeg_available()` is the boolean
     * companion of `_get_ffmpeg_path()`. When a config command is
     * present (any non-empty value) the helper returns true,
     * regardless of whether the path actually exists on disk -- the
     * runtime existence check is deferred to the actual conversion
     * pipeline later.
     *
     * @covers ::videoassessment_is_ffmpeg_available
     */
    public function test_is_ffmpeg_available_with_configured_command(): void {
        $this->resetAfterTest();
        set_config('ffmpegcommand', '/some/path/ffmpeg -i {INPUT} {OUTPUT}', 'videoassessment');
        $this->assertTrue(videoassessment_is_ffmpeg_available());
    }

    /**
     * Boolean shape check for the no-config branch.
     *
     * @covers ::videoassessment_is_ffmpeg_available
     */
    public function test_is_ffmpeg_available_returns_bool(): void {
        $this->resetAfterTest();
        unset_config('ffmpegcommand', 'videoassessment');
        $this->assertIsBool(videoassessment_is_ffmpeg_available());
    }

    /**
     * `videoassessment_get_ffmpeg_version()` runs `<binary> -version`
     * via the helper. When the binary does not exist the underlying
     * call returns a non-zero retval and the helper must return false;
     * we cannot pin a specific version string in the success case
     * because the test container's ffmpeg version varies.
     *
     * @covers ::videoassessment_get_ffmpeg_version
     */
    public function test_get_ffmpeg_version_with_missing_binary(): void {
        $this->resetAfterTest();
        set_config(
            'ffmpegcommand',
            '/this/path/does/not/exist/ffmpeg -i {INPUT} {OUTPUT}',
            'videoassessment'
        );
        $this->assertFalse(videoassessment_get_ffmpeg_version());
    }

    /**
     * When the binary exists, the helper returns either
     *   - a semver-shaped string ("5.1.4", "7.1", ...) if the first
     *     line of the version banner matches the expected regex, or
     *   - the raw first line of output otherwise.
     *
     * In either case the result is a non-empty string. The test is
     * skipped on minimal runners that do not have ffmpeg installed.
     *
     * @covers ::videoassessment_get_ffmpeg_version
     */
    public function test_get_ffmpeg_version_with_existing_binary(): void {
        $this->resetAfterTest();
        if (!is_executable('/usr/bin/ffmpeg')) {
            $this->markTestSkipped('/usr/bin/ffmpeg not present on this runner');
        }
        set_config('ffmpegcommand', '/usr/bin/ffmpeg -i {INPUT} {OUTPUT}', 'videoassessment');
        $version = videoassessment_get_ffmpeg_version();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    /**
     * `videoassessment_get_ffmpeg_command()` returns the configured
     * command run through PHP's `escapeshellcmd()`. The wrapper
     * escapes `{` and `}` (PHP documents both as part of the
     * shell-special set), so the returned string contains the
     * backslash-escaped placeholder forms `\{INPUT\}` and
     * `\{OUTPUT\}`. The colon in `-c:v` is NOT escaped because
     * colon is not in PHP's escape set, which means modern
     * stream-specifier syntax still survives.
     *
     * Note: this helper is currently unused by the runtime
     * conversion path (`bulkupload/lib.php` reads
     * `$CFG->videoassessment_ffmpegcommand` directly and runs
     * `strtr` with `escapeshellarg()` on the values); the helper
     * is provided for future callers that need a single
     * shell-safe handle. The escape behaviour is therefore part
     * of the contract we want to pin so a future refactor does
     * not silently change it.
     *
     * @covers ::videoassessment_get_ffmpeg_command
     */
    public function test_get_ffmpeg_command_returns_escaped_config(): void {
        $this->resetAfterTest();
        $cmd = '/usr/bin/ffmpeg -i {INPUT} -c:v libx264 -movflags +faststart {OUTPUT}';
        set_config('ffmpegcommand', $cmd, 'videoassessment');
        $result = videoassessment_get_ffmpeg_command();
        $this->assertIsString($result);
        // Placeholders are wrapped in backslashes by escapeshellcmd.
        $this->assertStringContainsString('\\{INPUT\\}', $result);
        $this->assertStringContainsString('\\{OUTPUT\\}', $result);
        // Stream specifier ":" is left intact.
        $this->assertStringContainsString('-c:v', $result);
        // The binary path should also survive intact.
        $this->assertStringContainsString('/usr/bin/ffmpeg', $result);
    }

    /**
     * Boundary: when the config is empty, the helper falls back to
     * the auto-detected binary path. The result is either a string
     * path or false depending on whether ffmpeg is on PATH.
     *
     * @covers ::videoassessment_get_ffmpeg_command
     */
    public function test_get_ffmpeg_command_falls_back_to_detected_path(): void {
        $this->resetAfterTest();
        unset_config('ffmpegcommand', 'videoassessment');
        $result = videoassessment_get_ffmpeg_command();
        $this->assertTrue(
            is_string($result) || $result === false,
            'Expected string or false, got ' . gettype($result)
        );
    }

    /**
     * Boundary: the escape helper neutralises shell metacharacters
     * even though the command_validator allow-list rejects them
     * upstream. Confirm a hypothetical injection-shaped value
     * (which the validator would block before storage) is still
     * neutralised here as defence-in-depth.
     *
     * @covers ::videoassessment_get_ffmpeg_command
     */
    public function test_get_ffmpeg_command_neutralises_metacharacters(): void {
        $this->resetAfterTest();
        // This value would never pass the command_validator allow-list,
        // but if it ever ends up stored (e.g. via direct DB write), the
        // wrapper must still neutralise it before anything reaches the
        // shell.
        set_config(
            'ffmpegcommand',
            '/usr/bin/ffmpeg -i {INPUT} {OUTPUT}; rm -rf /tmp/notadrill',
            'videoassessment'
        );
        $result = videoassessment_get_ffmpeg_command();
        $this->assertIsString($result);
        // The escape helper backslash-escapes ; to \; so the shell sees
        // it as a literal, not a command separator.
        $this->assertStringContainsString('\;', $result);
    }
}
