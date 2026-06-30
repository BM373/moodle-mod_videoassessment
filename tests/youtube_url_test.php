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
 * Unit tests for the YouTube URL parser used for Shorts compatibility.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\youtube_url}.
 *
 * Item #4 of the 2026-04 fix programme. The original code used
 * `explode('=', $url)[1]` to pull the video id out of an assumed
 * `?v=ID` query string. That breaks for YouTube Shorts (`/shorts/ID`)
 * and the short form (`youtu.be/ID`), which the customer needs because
 * smartphones now produce 9:16 Shorts as the default share format.
 *
 * The contract pinned by this test:
 *
 * - extract_id() returns the canonical 11-character video id from any
 *   common YouTube URL form (or null when the URL is not a YouTube URL),
 * - is_shorts() returns true for /shorts/ URLs, false otherwise,
 * - thumbnail_url() yields the i.ytimg.com URL with a sensible fallback
 *   chain (maxresdefault -> hqdefault -> 0.jpg),
 * - embed_url() returns a playback URL that works in an iframe and
 *   honours the privacy-enhanced (no-cookie) host when requested.
 */
final class youtube_url_test extends \basic_testcase {
    /**
     * Data provider with URLs and the video id they must yield.
     *
     * @return array<string, array{string, ?string}>
     */
    public static function id_extraction_provider(): array {
        return [
            'standard watch URL' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'standard watch URL with extra query' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&feature=share',
                'dQw4w9WgXcQ',
            ],
            'youtu.be short form' => [
                'https://youtu.be/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'youtu.be with query' => [
                'https://youtu.be/dQw4w9WgXcQ?si=abcd',
                'dQw4w9WgXcQ',
            ],
            'shorts URL' => [
                'https://www.youtube.com/shorts/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'shorts URL without www' => [
                'https://youtube.com/shorts/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'mobile m.youtube.com' => [
                'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'embed URL' => [
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'youtube-nocookie embed' => [
                'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'unrelated URL' => [
                'https://vimeo.com/123456789',
                null,
            ],
            'empty string' => ['', null],
            'malformed URL' => ['not a url', null],
            // Boundary: id length must be exactly 11 chars; 10 is too short.
            'id one char too short' => ['https://www.youtube.com/watch?v=dQw4w9WgXc', null],
            // Boundary: 12 chars must NOT match; the regex anchors the end.
            'id one char too long' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQQ', null],
            // Boundary: id with disallowed characters (`!`).
            'id with disallowed char' => ['https://www.youtube.com/watch?v=dQw4w9!gXcQ', null],
            // Common Shorts share form ends with extra `?si=`.
            'shorts with si=' => [
                'https://www.youtube.com/shorts/dQw4w9WgXcQ?si=AbCdEfG',
                'dQw4w9WgXcQ',
            ],
            // Watch URL with v= NOT in first position.
            'watch URL with v= in second position' => [
                'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            // Watch URL with v= followed by fragment.
            'watch URL with fragment' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ#t=10',
                'dQw4w9WgXcQ',
            ],
            // Mobile shorts.
            'mobile shorts URL' => [
                'https://m.youtube.com/shorts/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            // HTTP (not HTTPS) standard watch URL.
            'http watch URL' => [
                'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            // Defence-in-depth against XSS-style attempts: do not blindly
            // accept attacker-controlled fragments.
            'attacker injects javascript:' => [
                'javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ',
                null,
            ],
            'data: URL with v=' => [
                'data:text/html,<script>?v=dQw4w9WgXcQ',
                null,
            ],
            // Hostname must be youtube.com / youtu.be exactly; no
            // attacker-owned subdomain trick.
            'lookalike host evil.youtube.com.attacker.example' => [
                'https://youtube.com.attacker.example/watch?v=dQw4w9WgXcQ',
                null,
            ],
            'lookalike host www.youtube.com.evil' => [
                'https://www.youtube.com.evil/watch?v=dQw4w9WgXcQ',
                null,
            ],
            // Edge: youtu.be without leading slash inside the path.
            'youtu.be path traversal attempt' => [
                'https://youtu.be/../etc/passwd',
                null,
            ],
        ];
    }

    /**
     * Confirm extract_id handles every common YouTube URL form.
     *
     * @dataProvider id_extraction_provider
     * @param string $url Input URL.
     * @param string|null $expected Expected video id, or null when not a YT URL.
     * @covers \mod_videoassessment\youtube_url::extract_id
     */
    public function test_extract_id(string $url, ?string $expected): void {
        $this->assertSame($expected, youtube_url::extract_id($url));
    }

    /**
     * Confirm is_shorts() detects the /shorts/ form.
     *
     * @covers \mod_videoassessment\youtube_url::is_shorts
     */
    public function test_is_shorts(): void {
        $this->assertTrue(youtube_url::is_shorts('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertTrue(youtube_url::is_shorts('https://youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertFalse(youtube_url::is_shorts('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertFalse(youtube_url::is_shorts('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertFalse(youtube_url::is_shorts('https://vimeo.com/1'));
    }

    /**
     * Confirm thumbnail_url returns the i.ytimg.com canonical URL.
     *
     * @covers \mod_videoassessment\youtube_url::thumbnail_url
     */
    public function test_thumbnail_url(): void {
        $this->assertSame(
            'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
            youtube_url::thumbnail_url('dQw4w9WgXcQ')
        );
    }

    /**
     * Confirm embed_url returns a playback URL with optional no-cookie host.
     *
     * @covers \mod_videoassessment\youtube_url::embed_url
     */
    public function test_embed_url_default(): void {
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            youtube_url::embed_url('dQw4w9WgXcQ')
        );
    }

    /**
     * Confirm embed_url with the no-cookie flag uses youtube-nocookie.com.
     *
     * @covers \mod_videoassessment\youtube_url::embed_url
     */
    public function test_embed_url_nocookie(): void {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            youtube_url::embed_url('dQw4w9WgXcQ', true)
        );
    }

    /**
     * Boundary tests for is_shorts() with edge-case URLs.
     *
     * @covers \mod_videoassessment\youtube_url::is_shorts
     */
    public function test_is_shorts_boundaries(): void {
        // Empty input is not shorts.
        $this->assertFalse(youtube_url::is_shorts(''));
        // Mobile shorts is shorts.
        $this->assertTrue(youtube_url::is_shorts('https://m.youtube.com/shorts/dQw4w9WgXcQ'));
        // Embed is NOT shorts (the iframe form is a separate path).
        $this->assertFalse(youtube_url::is_shorts('https://www.youtube.com/embed/dQw4w9WgXcQ'));
        // The youtu.be short form is NOT a Shorts video.
        $this->assertFalse(youtube_url::is_shorts('https://youtu.be/dQw4w9WgXcQ'));
        // The /shorts/ path on a lookalike host must not be detected.
        $this->assertFalse(
            youtube_url::is_shorts('https://youtube.com.attacker.example/shorts/dQw4w9WgXcQ')
        );
    }

    /**
     * Boundary tests for thumbnail_url() — input is not validated, so the
     * helper must be safe with arbitrary 11-char-id-shaped strings.
     *
     * @covers \mod_videoassessment\youtube_url::thumbnail_url
     */
    public function test_thumbnail_url_boundaries(): void {
        // The helper does not re-validate the id; the contract is "build a
        // URL from this id". Confirm it works with the full range of
        // legal id characters.
        $this->assertSame(
            'https://i.ytimg.com/vi/abcdefghijk/maxresdefault.jpg',
            youtube_url::thumbnail_url('abcdefghijk')
        );
        $this->assertSame(
            'https://i.ytimg.com/vi/A_-1234567z/maxresdefault.jpg',
            youtube_url::thumbnail_url('A_-1234567z')
        );
    }

    /**
     * Boundary tests for embed_url(): default vs nocookie hosts both
     * produce the canonical /embed/ form.
     *
     * @covers \mod_videoassessment\youtube_url::embed_url
     */
    public function test_embed_url_boundaries(): void {
        // Default (cookie-bearing) host.
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            youtube_url::embed_url('dQw4w9WgXcQ', false)
        );
        // Privacy-enhanced (nocookie) host.
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/A_-1234567z',
            youtube_url::embed_url('A_-1234567z', true)
        );
    }
}
