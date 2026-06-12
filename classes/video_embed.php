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
 * Item #1 of the 2026-04 fix programme, extended for the 2026-06
 * platform-support request from the French colleagues. Centralises the
 * "given a pasted external URL, what iframe src should we render?"
 * decision so the renderer stays thin and every mapping stays
 * unit-testable.
 *
 * Supported platforms:
 * - YouTube (incl. Shorts / youtu.be)  -> youtube-nocookie.com when GDPR on
 * - Vimeo                              -> player.vimeo.com, ?dnt=1 when GDPR on
 * - PeerTube (any instance, e.g. tubes.apps.education.fr, exquisite.tube)
 *                                      -> /videos/embed/{id}, ?p2p=0 when GDPR on
 * - Esup-Pod (any instance)            -> /video/{id-slug}/?is_iframe=true
 * - Dailymotion (incl. dai.ly)         -> geo.dailymotion.com/player.html?video={id}
 * - Opencast players (/play/, Paella, Theodul) -> embedded as-is
 * - Generic embed/share URLs (https only: /embed/, .../iframe/,
 *   ?is_iframe=true, player.html) — covers Canal-U and Ubicast Nudgis
 *   share-dialog URLs and any future platform without code changes.
 *
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
     *                   parameter for providers that offer one.
     * @return array{provider: string, src: string, shorts: bool}|null
     *         Null when the URL is not a recognised embeddable provider.
     */
    public static function resolve(string $url, bool $gdpr): ?array {
        if ($url === '') {
            return null;
        }

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

        // Fixed-host providers before the host-agnostic patterns so a
        // Dailymotion /video/... path is never misread as Esup-Pod.
        $resolved = self::resolve_dailymotion($url)
            ?? self::resolve_peertube($url, $gdpr)
            ?? self::resolve_esuppod($url)
            ?? self::resolve_opencast($url)
            ?? self::resolve_generic_embed($url);

        return $resolved;
    }

    /**
     * Dailymotion: watch / short-link / embed forms.
     *
     * The legacy www.dailymotion.com/embed/video/{id} endpoint now 301s
     * to geo.dailymotion.com/player.html?video={id} (verified live), so
     * every form is normalised straight to the geo player.
     *
     * @param string $url Candidate URL.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_dailymotion(string $url): ?array {
        $patterns = [
            // Watch URL, optionally with _slug-title suffix.
            '~^https?://(?:www\.)?dailymotion\.com/video/([a-zA-Z0-9]+)(?:_[^?#]*)?(?:[?#].*)?$~',
            // Short link.
            '~^https?://dai\.ly/([a-zA-Z0-9]+)(?:[?#].*)?$~',
            // Legacy embed URL.
            '~^https?://(?:www\.)?dailymotion\.com/embed/video/([a-zA-Z0-9]+)(?:[?#].*)?$~',
            // Modern geo player (pass-through, id re-extracted).
            '~^https?://geo\.dailymotion\.com/player\.html\?video=([a-zA-Z0-9]+)(?:[&#].*)?$~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return [
                    'provider' => 'dailymotion',
                    'src' => 'https://geo.dailymotion.com/player.html?video=' . $matches[1],
                    'shorts' => false,
                ];
            }
        }
        return null;
    }

    /**
     * PeerTube: host-agnostic, detected by the platform's
     * characteristic paths. Covers tubes.apps.education.fr (French
     * Ministry of Education), exquisite.tube and every other instance.
     *
     * /w/{shortid} and /videos/watch/{uuid} are watch pages; the iframe
     * endpoint is /videos/embed/{id} (verified live on the customer's
     * exquisite.tube example). With GDPR on, p2p=0 stops the player
     * sharing the viewer's IP address with other viewers over WebRTC.
     *
     * @param string $url Candidate URL.
     * @param bool $gdpr Disable peer-to-peer delivery when true.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_peertube(string $url, bool $gdpr): ?array {
        $patterns = [
            '~^(https?://[^/?#]+)/w/([a-zA-Z0-9-]+)(?:[?#].*)?$~',
            '~^(https?://[^/?#]+)/videos/watch/([a-zA-Z0-9-]+)(?:[?#].*)?$~',
            '~^(https?://[^/?#]+)/videos/embed/([a-zA-Z0-9-]+)(?:[?#].*)?$~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                $src = $matches[1] . '/videos/embed/' . $matches[2];
                if ($gdpr) {
                    $src .= '?p2p=0';
                }
                return [
                    'provider' => 'peertube',
                    'src' => $src,
                    'shorts' => false,
                ];
            }
        }
        return null;
    }

    /**
     * Esup-Pod: host-agnostic. Pod video pages live at
     * /video/{numeric-id}-{slug}/ and the platform's own share dialog
     * embeds them with the documented ?is_iframe=true parameter.
     *
     * @param string $url Candidate URL.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_esuppod(string $url): ?array {
        $pattern = '~^(https?://[^/?#]+/video/\d+[a-zA-Z0-9_-]*/?)(?:\?([^#]*))?(?:#.*)?$~';
        if (preg_match($pattern, $url, $matches) !== 1) {
            return null;
        }
        $base = $matches[1];
        if (substr($base, -1) !== '/') {
            $base .= '/';
        }
        $query = $matches[2] ?? '';
        if (strpos($query, 'is_iframe=true') === false) {
            $query = ($query === '') ? 'is_iframe=true' : $query . '&is_iframe=true';
        }
        return [
            'provider' => 'esuppod',
            'src' => $base . '?' . $query,
            'shorts' => false,
        ];
    }

    /**
     * Opencast: host-agnostic player paths. Opencast has no single
     * canonical watch URL across versions, but its player endpoints
     * (/play/{id}, the Paella player, the legacy Theodul player) are
     * standalone pages designed to be embedded, so they are iframed
     * as-is.
     *
     * @param string $url Candidate URL.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_opencast(string $url): ?array {
        $patterns = [
            '~^https?://[^/?#]+/play/[a-zA-Z0-9-]+(?:[?#].*)?$~',
            '~^https?://[^/?#]+/paella/ui/watch\.html\?id=[a-zA-Z0-9-]+(?:[&#].*)?$~',
            '~^https?://[^/?#]+/engage/theodul/ui/core\.html\?id=[a-zA-Z0-9-]+(?:[&#].*)?$~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url) === 1) {
                return [
                    'provider' => 'opencast',
                    'src' => $url,
                    'shorts' => false,
                ];
            }
        }
        return null;
    }

    /**
     * Generic pass-through for URLs that are already an embed / player
     * endpoint: the share dialogs of Canal-U, Ubicast Nudgis
     * (/permalink/{oid}/iframe/) and most other platforms hand the user
     * exactly such a URL, so accepting them directly supports those
     * platforms without per-provider code. HTTPS only — these end up
     * as iframe src on our pages.
     *
     * @param string $url Candidate URL.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_generic_embed(string $url): ?array {
        if (!preg_match('~^https://~', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }
        $path = $parts['path'];
        $query = $parts['query'] ?? '';
        $isembed = strpos($path, '/embed/') !== false
            || preg_match('~/iframe/?$~', $path) === 1
            || strpos($query, 'is_iframe=true') !== false;
        if (!$isembed) {
            return null;
        }
        return [
            'provider' => 'embed',
            'src' => $url,
            'shorts' => false,
        ];
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
