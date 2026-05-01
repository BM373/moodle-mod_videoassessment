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
 * @copyright  2026 Shinonome Labo Co., Ltd.
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
}
