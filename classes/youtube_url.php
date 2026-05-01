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
 * YouTube URL parser.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Helper that normalises any of the common YouTube URL forms into a
 * canonical 11-character video id and produces playback / thumbnail URLs.
 *
 * Item #4 of the 2026-04 fix programme. The original code in
 * {@see \mod_videoassessment\va} extracted the video id with a naive
 * `explode('=', $url)[1]`, which only worked for `?v=ID` and broke for
 * `youtu.be/ID` and the increasingly-common `/shorts/ID` form pushed
 * by smartphones in landscape-recording mode.
 */
final class youtube_url {
    /** @var string Regex matching an 11-character YouTube video id. */
    private const VIDEO_ID = '[A-Za-z0-9_-]{11}';

    /**
     * Extract the canonical video id from any common YouTube URL form.
     *
     * @param string $url Input URL.
     * @return string|null 11-character video id, or null when the URL
     *                     does not point to a YouTube video.
     */
    public static function extract_id(string $url): ?string {
        if ($url === '') {
            return null;
        }
        $patterns = [
            // YouTube Shorts URLs (with or without "www.").
            '~^https?://(?:www\.|m\.)?youtube\.com/shorts/(' . self::VIDEO_ID . ')(?:[?&].*)?$~',
            // Canonical embed URLs (also covers youtube-nocookie).
            '~^https?://(?:www\.|m\.)?youtube(?:-nocookie)?\.com/embed/(' . self::VIDEO_ID . ')(?:[?&].*)?$~',
            // Standard watch URLs (with or without other query parameters).
            '~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:[^#]*&)?v=(' . self::VIDEO_ID . ')(?:[&#].*)?$~',
            // Short youtu.be form.
            '~^https?://youtu\.be/(' . self::VIDEO_ID . ')(?:[?&].*)?$~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Detect whether a URL points to a YouTube Shorts video.
     *
     * Shorts are 9:16 portrait-oriented YouTube videos that need a
     * different iframe aspect ratio than landscape videos.
     *
     * @param string $url Input URL.
     * @return bool
     */
    public static function is_shorts(string $url): bool {
        return preg_match(
            '~^https?://(?:www\.|m\.)?youtube\.com/shorts/' . self::VIDEO_ID . '~',
            $url
        ) === 1;
    }

    /**
     * Build the canonical thumbnail URL for a given video id.
     *
     * Returns the high-resolution thumbnail by default; callers that
     * receive a 404 should fall back to ``hqdefault.jpg`` / ``0.jpg``.
     *
     * @param string $videoid 11-character YouTube video id.
     * @return string
     */
    public static function thumbnail_url(string $videoid): string {
        return "https://i.ytimg.com/vi/{$videoid}/maxresdefault.jpg";
    }

    /**
     * Build a playback (iframe) URL for a given video id.
     *
     * @param string $videoid 11-character YouTube video id.
     * @param bool $nocookie When true, use the privacy-enhanced
     *                       youtube-nocookie.com host (GDPR friendly).
     * @return string
     */
    public static function embed_url(string $videoid, bool $nocookie = false): string {
        $host = $nocookie ? 'www.youtube-nocookie.com' : 'www.youtube.com';
        return "https://{$host}/embed/{$videoid}";
    }
}
