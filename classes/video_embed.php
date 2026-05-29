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
 * GDPR-aware external-video embed resolver.
 *
 * Item #1 of the 2026-04 fix programme. Centralises the "given a pasted
 * external URL, what iframe src should we render?" decision so the
 * renderer stays thin and the GDPR cookie-suppression behaviour is
 * unit-testable. Supports the two services the customer uses:
 *   - YouTube (incl. Shorts / youtu.be) -> youtube-nocookie.com when on
 *   - Vimeo                              -> player.vimeo.com ?dnt=1 when on
 * Any other URL returns null so the caller falls back to Moodle's
 * filter_mediaplugin.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class video_embed {
    /**
     * Resolve a pasted external URL to a provider + embed src.
     *
     * @param string $url The external video URL the user pasted.
     * @param bool $gdpr When true, use the cookie-suppressing host /
     *                   parameter for the detected provider.
     * @return array{provider: string, src: string, shorts: bool}|null
     *         Null when the URL is not a recognised embeddable provider.
     */
    public static function resolve(string $url, bool $gdpr): ?array {
        $youtubeid = youtube_url::extract_id($url);
        if ($youtubeid !== null) {
            return [
                'provider' => 'youtube',
                'src' => youtube_url::embed_url($youtubeid, $gdpr),
                'shorts' => youtube_url::is_shorts($url),
            ];
        }

        $vimeoid = vimeo_url::extract_id($url);
        if ($vimeoid !== null) {
            return [
                'provider' => 'vimeo',
                'src' => vimeo_url::embed_url($vimeoid, $gdpr),
                'shorts' => false,
            ];
        }

        return null;
    }

    /**
     * Whether GDPR cookie suppression is enabled site-wide.
     *
     * Defaults to ON (privacy by default) when the setting has never
     * been saved, which is the GDPR-aligned posture and is functionally
     * identical for playback.
     *
     * @return bool
     */
    public static function gdpr_enabled(): bool {
        $value = get_config('videoassessment', 'gdprcookiesuppression');
        return $value === false ? true : (bool) $value;
    }
}
