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

        if ($resolved === null) {
            return null;
        }

        // Security gate: the host-agnostic providers reflect the user-
        // supplied host straight into an iframe src that other users
        // (teachers grading, peer reviewers) then load. A student with
        // the submit capability could otherwise embed a phishing or
        // clickjacking page on the assess screen. Require the host to
        // be on the admin trusted-host allowlist; an untrusted host
        // resolves to null so the link degrades to Moodle's
        // filter_mediaplugin (a plain link, never an arbitrary iframe).
        // The fixed-host providers (youtube / vimeo / dailymotion)
        // always emit a known-safe player host, so they are exempt.
        $hostagnostic = ['peertube', 'esuppod', 'opencast', 'embed'];
        if (in_array($resolved['provider'], $hostagnostic, true)) {
            $host = (string) parse_url($resolved['src'], PHP_URL_HOST);
            if (!self::host_is_trusted($host)) {
                return null;
            }
        }

        return $resolved;
    }

    /**
     * The host of an untrusted host-agnostic embed, for messaging.
     *
     * resolve() returns null both for a URL that is not a recognised
     * video and for a recognised host-agnostic provider (PeerTube,
     * Esup-Pod, Opencast, generic embed) whose host is not on the
     * trusted-embed allowlist. The renderer needs to tell those two
     * cases apart so it can show a helpful "host not trusted" notice
     * instead of silently degrading to a bare link. Returns the blocked
     * host in the second case, or null otherwise (fixed-host providers,
     * trusted hosts and unrecognised URLs all yield null).
     *
     * @param string $url Candidate URL.
     * @return string|null Untrusted host, or null when not host-blocked.
     */
    public static function blocked_host(string $url): ?string {
        if ($url === '' || youtube_url::extract_id($url) !== null || vimeo_url::extract_id($url) !== null) {
            return null;
        }
        $resolved = self::resolve_dailymotion($url)
            ?? self::resolve_peertube($url, false)
            ?? self::resolve_esuppod($url)
            ?? self::resolve_opencast($url)
            ?? self::resolve_generic_embed($url);
        if ($resolved === null) {
            return null;
        }
        $hostagnostic = ['peertube', 'esuppod', 'opencast', 'embed'];
        if (!in_array($resolved['provider'], $hostagnostic, true)) {
            return null;
        }
        $host = (string) parse_url($resolved['src'], PHP_URL_HOST);
        if ($host === '' || self::host_is_trusted($host)) {
            return null;
        }
        return $host;
    }

    /**
     * Best-effort thumbnail URL for an external video link.
     *
     * YouTube is handled by the caller (a static i.ytimg URL). Here we
     * cover the rest: Vimeo and Dailymotion expose a derivable still,
     * while PeerTube and Esup-Pod are read through their oEmbed
     * endpoints. Opencast and the generic embed pass-through have no
     * reliable thumbnail and return null (the caller then renders a
     * neutral placeholder). Any network or parse failure also yields null.
     *
     * @param string $url The external video URL.
     * @return string|null https thumbnail URL, or null when none.
     */
    public static function thumbnail_url(string $url): ?string {
        $vimeoid = vimeo_url::extract_id($url);
        if ($vimeoid !== null) {
            return vimeo_url::thumbnail_url($vimeoid);
        }
        // Detect the provider without the trust gate: thumbnails are
        // fetched at registration, independent of the embed allowlist.
        $resolved = self::resolve_dailymotion($url)
            ?? self::resolve_peertube($url, false)
            ?? self::resolve_esuppod($url)
            ?? self::resolve_opencast($url)
            ?? self::resolve_generic_embed($url);
        if ($resolved === null) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME)) ?: 'https';
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return null;
        }
        if ($resolved['provider'] === 'dailymotion') {
            if (preg_match('~[?&]video=([a-zA-Z0-9]+)~', $resolved['src'], $m) === 1) {
                return 'https://www.dailymotion.com/thumbnail/video/' . $m[1];
            }
            return null;
        }
        if ($resolved['provider'] === 'peertube') {
            $endpoint = $scheme . '://' . $host . '/services/oembed?format=json&url=' . rawurlencode($url);
            return self::oembed_thumbnail($endpoint, $host);
        }
        if ($resolved['provider'] === 'esuppod') {
            $endpoint = $scheme . '://' . $host . '/video/oembed/?format=json&url=' . rawurlencode($url);
            return self::oembed_thumbnail($endpoint, $host);
        }
        // Opencast and the generic embed pass-through: no derivable still.
        return null;
    }

    /**
     * Read a thumbnail URL from an oEmbed JSON endpoint.
     *
     * Network errors, malformed payloads and thumbnails on an unrelated
     * host all yield null. Some providers (Esup-Pod) return a host-
     * relative thumbnail such as "https:/media/...", which is resolved
     * against the video host.
     *
     * @param string $oembedurl The provider oEmbed endpoint (returns json).
     * @param string $videohost The video host, used to vet/resolve the result.
     * @return string|null https thumbnail URL, or null.
     */
    private static function oembed_thumbnail(string $oembedurl, string $videohost): ?string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        try {
            $curl = new \curl();
            $body = $curl->get($oembedurl, [], [
                'CURLOPT_TIMEOUT' => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
                'CURLOPT_FOLLOWLOCATION' => false,
            ]);
            if ($curl->get_errno() || !is_string($body) || $body === '') {
                return null;
            }
            $data = json_decode($body, true);
            $thumb = (is_array($data) && isset($data['thumbnail_url'])) ? $data['thumbnail_url'] : '';
            if (!is_string($thumb) || $thumb === '') {
                return null;
            }
            $videohost = strtolower($videohost);
            $thumbhost = strtolower((string) parse_url($thumb, PHP_URL_HOST));
            if ($thumbhost === '') {
                // Host-relative thumbnail: resolve against the video host.
                $path = (string) parse_url($thumb, PHP_URL_PATH);
                return $path === '' ? null : 'https://' . $videohost . $path;
            }
            if (strtolower((string) parse_url($thumb, PHP_URL_SCHEME)) !== 'https') {
                return null;
            }
            // Only trust a thumbnail on the video host or a sub-domain of it.
            $issamesite = $thumbhost === $videohost || substr($thumbhost, -strlen('.' . $videohost)) === '.' . $videohost;
            return $issamesite ? $thumb : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Decide whether a host is on the admin trusted-embed allowlist.
     *
     * The allowlist is the `trustedembedhosts` site setting: one host
     * per line (blank lines and `#` comments ignored). A host matches
     * an entry when it equals the entry or is a sub-domain of it, so
     * `media.univ.fr` matches an entry of `univ.fr`. Matching is
     * case-insensitive. An empty / unset list trusts nothing (the
     * host-agnostic providers then never embed) — the shipped default
     * covers the public platforms, so admins only edit it to add their
     * own self-hosted instances.
     *
     * @param string $host Host component of the candidate iframe src.
     * @return bool
     */
    public static function host_is_trusted(string $host): bool {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        $raw = (string) get_config('videoassessment', 'trustedembedhosts');
        foreach (preg_split('~\R~', $raw) as $line) {
            $entry = strtolower(trim($line));
            if ($entry === '' || $entry[0] === '#') {
                continue;
            }
            // Tolerate entries pasted as full URLs or with a leading dot.
            if (strpos($entry, '//') !== false) {
                $entry = (string) parse_url($entry, PHP_URL_HOST);
            }
            $entry = ltrim($entry, '.');
            if ($entry === '') {
                continue;
            }
            if ($host === $entry || self::str_ends_with_suffix($host, '.' . $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Suffix test (str_ends_with is PHP 8.0+, but kept explicit so the
     * dotted-boundary intent is obvious and host "evilyoutube.com"
     * never matches a ".youtube.com" suffix by accident).
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private static function str_ends_with_suffix(string $haystack, string $needle): bool {
        $len = strlen($needle);
        if ($len === 0 || strlen($haystack) < $len) {
            return false;
        }
        return substr($haystack, -$len) === $needle;
    }

    /**
     * The shipped default trusted-embed host list: the public platforms
     * named in the 2026-06 feature request. Self-hosted PeerTube /
     * Esup-Pod / Opencast instances are added by the site admin.
     *
     * @return string Newline-separated host list for the setting default.
     */
    public static function default_trusted_hosts(): string {
        return implode("\n", [
            '# One host per line. A host also matches its sub-domains',
            '# (an entry of "univ.fr" trusts "media.univ.fr"). Add your',
            '# institution\'s PeerTube / Esup-Pod / Opencast host here.',
            'youtube.com',
            'youtube-nocookie.com',
            'youtu.be',
            'vimeo.com',
            'dailymotion.com',
            'dai.ly',
            'canal-u.tv',
            'tubes.apps.education.fr',
            'pod.esup-portail.org',
            'exquisite.tube',
        ]);
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
     * (the Tobira /v/{id} share link and /play/{id}, the Paella player,
     * the legacy Theodul player) are standalone pages designed to be
     * embedded, so they are iframed as-is. The /v/ and /play/ ids are
     * Tobira's short base-something ids, hence the mixed-case class.
     *
     * @param string $url Candidate URL.
     * @return array{provider: string, src: string, shorts: bool}|null
     */
    private static function resolve_opencast(string $url): ?array {
        // A Tobira video page (/v/{id}) frame-busts inside an iframe
        // ("This page can't be embedded"); its dedicated, iframe-safe
        // player route is /~embed/!v/{id} (note the "!"; this is exactly
        // what Tobira's own Share -> Embed dialog hands out), so rewrite
        // that form and drop any query such as ?order=.... The other
        // player endpoints are already embeddable and are iframed verbatim.
        if (preg_match('~^(https?://[^/?#]+)/v/([a-zA-Z0-9_-]+)(?:[?#].*)?$~', $url, $m) === 1) {
            return [
                'provider' => 'opencast',
                'src' => $m[1] . '/~embed/!v/' . $m[2],
                'shorts' => false,
            ];
        }
        $patterns = [
            '~^https?://[^/?#]+/play/[a-zA-Z0-9_-]+(?:[?#].*)?$~',
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
