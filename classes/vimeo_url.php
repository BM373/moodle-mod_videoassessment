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

namespace mod_videoassessment;

/**
 * Vimeo URL parser and GDPR-aware embed builder.
 *
 * Item #1 of the 2026-04 fix programme generalised the activity beyond
 * YouTube. Vimeo is one of the external services the customer uses, and
 * its player supports a privacy-enhancing Do-Not-Track parameter
 * (`?dnt=1`) that suppresses tracking cookies — the Vimeo equivalent of
 * youtube-nocookie.com.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class vimeo_url {
    /** @var string Regex matching a numeric Vimeo video id. */
    private const VIDEO_ID = '[0-9]+';

    /**
     * Extract the numeric video id from a common Vimeo URL form.
     *
     * @param string $url Input URL.
     * @return string|null Numeric id, or null when not a Vimeo video URL.
     */
    public static function extract_id(string $url): ?string {
        if ($url === '') {
            return null;
        }
        $patterns = [
            // Player embed URL.
            '~^https?://player\.vimeo\.com/video/(' . self::VIDEO_ID . ')(?:[?&].*)?$~',
            // Canonical watch URL (optionally with an unlisted hash segment).
            '~^https?://(?:www\.)?vimeo\.com/(' . self::VIDEO_ID . ')(?:/[0-9a-z]+)?(?:[?&#].*)?$~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Build a player embed URL for a given Vimeo video id.
     *
     * @param string $videoid Numeric Vimeo video id.
     * @param bool $dnt When true, append `?dnt=1` so the player does not
     *                  set tracking cookies (GDPR Do-Not-Track).
     * @return string
     */
    public static function embed_url(string $videoid, bool $dnt = false): string {
        $url = "https://player.vimeo.com/video/{$videoid}";
        if ($dnt) {
            $url .= '?dnt=1';
        }
        return $url;
    }
}
