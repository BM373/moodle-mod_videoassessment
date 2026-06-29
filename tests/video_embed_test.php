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
 * GDPR-aware external video embed resolution tests (Item #1).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\vimeo_url} and
 * {@see \mod_videoassessment\video_embed} (Item #1, GDPR cookie
 * suppression).
 *
 * The plugin must let an admin opt into cookie-suppressing embeds:
 *   - YouTube  -> youtube-nocookie.com
 *   - Vimeo    -> player.vimeo.com/video/{id}?dnt=1
 * video_embed::resolve() centralises the provider detection + GDPR
 * decision so the renderer (which is hard to unit test) stays thin.
 */
final class video_embed_test extends \advanced_testcase {
    /**
     * Trust every host the resolution-matrix tests use, so those tests
     * exercise the URL -> embed-src logic independently of the
     * security allowlist (which has its own dedicated tests below).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('trustedembedhosts', implode("\n", [
            'youtube.com',
            'vimeo.com',
            'dailymotion.com',
            'dai.ly',
            'canal-u.tv',
            'tubes.apps.education.fr',
            'pod.esup-portail.org',
            'exquisite.tube',
            'univ.fr',
            'ubicast.tv',
            'example.edu',
        ]), 'videoassessment');
    }

    // Tests for the vimeo_url helper.

    /**
     * Vimeo id extraction from the common URL forms.
     *
     * @covers \mod_videoassessment\vimeo_url::extract_id
     */
    public function test_vimeo_extract_id(): void {
        $this->assertSame('123456789', vimeo_url::extract_id('https://vimeo.com/123456789'));
        $this->assertSame('123456789', vimeo_url::extract_id('https://player.vimeo.com/video/123456789'));
        $this->assertSame('123456789', vimeo_url::extract_id('https://vimeo.com/123456789?share=copy'));
        $this->assertNull(vimeo_url::extract_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(vimeo_url::extract_id('not a url'));
        $this->assertNull(vimeo_url::extract_id(''));
    }

    /**
     * Vimeo embed URL, with and without the GDPR Do-Not-Track flag.
     *
     * @covers \mod_videoassessment\vimeo_url::embed_url
     */
    public function test_vimeo_embed_url(): void {
        $this->assertSame(
            'https://player.vimeo.com/video/123456789',
            vimeo_url::embed_url('123456789', false)
        );
        $this->assertSame(
            'https://player.vimeo.com/video/123456789?dnt=1',
            vimeo_url::embed_url('123456789', true)
        );
    }

    // Tests for video_embed::resolve.

    /**
     * A YouTube URL resolves to a youtube provider with the cookie host
     * chosen by the GDPR flag.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_youtube(): void {
        $on = video_embed::resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ', true);
        $this->assertSame('youtube', $on['provider']);
        $this->assertStringContainsString('youtube-nocookie.com', $on['src']);
        $this->assertFalse($on['shorts']);

        $off = video_embed::resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false);
        $this->assertStringContainsString('www.youtube.com', $off['src']);
        $this->assertStringNotContainsString('nocookie', $off['src']);
    }

    /**
     * A Shorts URL is flagged shorts=true so the renderer can pick the
     * 9:16 portrait class.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_youtube_shorts_flag(): void {
        $r = video_embed::resolve('https://www.youtube.com/shorts/dQw4w9WgXcQ', false);
        $this->assertSame('youtube', $r['provider']);
        $this->assertTrue($r['shorts']);
    }

    /**
     * A Vimeo URL resolves to a vimeo provider; the dnt=1 flag follows
     * the GDPR toggle.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_vimeo(): void {
        $on = video_embed::resolve('https://vimeo.com/123456789', true);
        $this->assertSame('vimeo', $on['provider']);
        $this->assertStringContainsString('dnt=1', $on['src']);
        $this->assertFalse($on['shorts']);

        $off = video_embed::resolve('https://vimeo.com/123456789', false);
        $this->assertStringNotContainsString('dnt=1', $off['src']);
    }

    /**
     * A non-embeddable URL (e.g. a raw mp4 or an unknown host) resolves
     * to null so the renderer falls back to filter_mediaplugin.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_unknown_returns_null(): void {
        $this->assertNull(video_embed::resolve('https://example.com/clip.mp4', true));
        $this->assertNull(video_embed::resolve('', true));
    }

    /**
     * Provider matrix for the 2026-06 platform-support extension:
     * input URL -> expected provider + embed src (GDPR off).
     *
     * @return array<string, array{string, ?string, ?string}>
     */
    public static function platform_provider(): array {
        return [
            // PeerTube (host-agnostic): the customer's live example.
            'peertube /w/ shortid' => [
                'https://exquisite.tube/w/5P2RS53HxeMyVQ3n3wSGvu',
                'peertube',
                'https://exquisite.tube/videos/embed/5P2RS53HxeMyVQ3n3wSGvu',
            ],
            'peertube Tubes (education ministry)' => [
                'https://tubes.apps.education.fr/w/abc123XYZ',
                'peertube',
                'https://tubes.apps.education.fr/videos/embed/abc123XYZ',
            ],
            'peertube legacy /videos/watch/ uuid' => [
                'https://exquisite.tube/videos/watch/8d6a3c0f-25e7-4f6f-9f9e-12c44b3aef01',
                'peertube',
                'https://exquisite.tube/videos/embed/8d6a3c0f-25e7-4f6f-9f9e-12c44b3aef01',
            ],
            'peertube embed pass-through' => [
                'https://exquisite.tube/videos/embed/5P2RS53HxeMyVQ3n3wSGvu',
                'peertube',
                'https://exquisite.tube/videos/embed/5P2RS53HxeMyVQ3n3wSGvu',
            ],
            'peertube watch URL with query' => [
                'https://exquisite.tube/w/5P2RS53HxeMyVQ3n3wSGvu?start=10s',
                'peertube',
                'https://exquisite.tube/videos/embed/5P2RS53HxeMyVQ3n3wSGvu',
            ],
            // Boundary: /w/ path with characters outside the id charset
            // (e.g. MediaWiki /w/index.php) must NOT be treated as
            // PeerTube.
            'not peertube: wiki /w/index.php' => [
                'https://en.wikipedia.org/w/index.php?title=Video',
                null,
                null,
            ],
            // Esup-Pod (host-agnostic, id starts with digits).
            'esup-pod canonical' => [
                'https://pod.esup-portail.org/video/0001-ma-video/',
                'esuppod',
                'https://pod.esup-portail.org/video/0001-ma-video/?is_iframe=true',
            ],
            'esup-pod without trailing slash' => [
                'https://pod.univ.fr/video/123-titre',
                'esuppod',
                'https://pod.univ.fr/video/123-titre/?is_iframe=true',
            ],
            'esup-pod with existing query' => [
                'https://pod.univ.fr/video/123-titre/?start=5',
                'esuppod',
                'https://pod.univ.fr/video/123-titre/?start=5&is_iframe=true',
            ],
            'esup-pod is_iframe already present' => [
                'https://pod.univ.fr/video/123-titre/?is_iframe=true',
                'esuppod',
                'https://pod.univ.fr/video/123-titre/?is_iframe=true',
            ],
            // Boundary: /video/{non-digit} is not Pod (Pod ids are
            // numeric-prefixed slugs).
            'not esup-pod: alpha slug' => [
                'https://example.com/video/watch-me/',
                null,
                null,
            ],
            // Dailymotion.
            'dailymotion watch' => [
                'https://www.dailymotion.com/video/x9ekanc',
                'dailymotion',
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
            ],
            'dailymotion watch with slug suffix' => [
                'https://www.dailymotion.com/video/x9ekanc_some-title-here',
                'dailymotion',
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
            ],
            'dailymotion short link' => [
                'https://dai.ly/x9ekanc',
                'dailymotion',
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
            ],
            'dailymotion legacy embed normalised to geo player' => [
                'https://www.dailymotion.com/embed/video/x9ekanc',
                'dailymotion',
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
            ],
            'dailymotion geo player pass-through' => [
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
                'dailymotion',
                'https://geo.dailymotion.com/player.html?video=x9ekanc',
            ],
            // Opencast players (host-agnostic pass-through).
            'opencast /v/ tobira share link rewritten to embed route' => [
                'https://opencast.univ.fr/v/GlyZSol6GjU',
                'opencast',
                'https://opencast.univ.fr/~embed/v/GlyZSol6GjU',
            ],
            'opencast /v/ with query rewritten to embed route' => [
                'https://opencast.univ.fr/v/GlyZSol6GjU?order=old_to_new',
                'opencast',
                'https://opencast.univ.fr/~embed/v/GlyZSol6GjU',
            ],
            'opencast /play/' => [
                'https://opencast.univ.fr/play/8d6a3c0f-25e7-4f6f-9f9e-12c44b3aef01',
                'opencast',
                'https://opencast.univ.fr/play/8d6a3c0f-25e7-4f6f-9f9e-12c44b3aef01',
            ],
            'opencast paella player' => [
                'https://opencast.univ.fr/paella/ui/watch.html?id=8d6a3c0f-25e7-4f6f',
                'opencast',
                'https://opencast.univ.fr/paella/ui/watch.html?id=8d6a3c0f-25e7-4f6f',
            ],
            'opencast theodul player' => [
                'https://opencast.univ.fr/engage/theodul/ui/core.html?id=8d6a3c0f',
                'opencast',
                'https://opencast.univ.fr/engage/theodul/ui/core.html?id=8d6a3c0f',
            ],
            // Generic embed pass-through (Canal-U / Ubicast share
            // dialog URLs and friends). HTTPS only.
            'generic /embed/ path (canal-u style)' => [
                'https://www.canal-u.tv/embed/12345',
                'embed',
                'https://www.canal-u.tv/embed/12345',
            ],
            'generic ubicast permalink iframe' => [
                'https://uni.ubicast.tv/permalink/v1263abc/iframe/',
                'embed',
                'https://uni.ubicast.tv/permalink/v1263abc/iframe/',
            ],
            'generic is_iframe query' => [
                'https://media.example.edu/page?is_iframe=true',
                'embed',
                'https://media.example.edu/page?is_iframe=true',
            ],
            // Boundary: plain http is rejected for the generic
            // pass-through (it becomes an iframe src on our pages).
            'generic embed rejected over http' => [
                'http://www.canal-u.tv/embed/12345',
                null,
                null,
            ],
            // Boundary: ordinary watch pages of unsupported platforms
            // still fall through to null.
            'canal-u watch page is not auto-embeddable' => [
                'https://www.canal-u.tv/chaines/unit/some-video',
                null,
                null,
            ],
        ];
    }

    /**
     * Each supported platform URL resolves to its documented embed
     * form; lookalikes and unsupported forms fall through to null.
     *
     * @dataProvider platform_provider
     * @param string $url Input URL.
     * @param string|null $provider Expected provider key (null = no match).
     * @param string|null $src Expected embed src.
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_platforms(string $url, ?string $provider, ?string $src): void {
        $result = video_embed::resolve($url, false);
        if ($provider === null) {
            $this->assertNull($result, "URL must not resolve: {$url}");
            return;
        }
        $this->assertNotNull($result, "URL must resolve: {$url}");
        $this->assertSame($provider, $result['provider']);
        $this->assertSame($src, $result['src']);
        $this->assertFalse($result['shorts']);
    }

    /**
     * GDPR mode appends p2p=0 to PeerTube embeds (stops the player
     * sharing the viewer's IP address with other viewers over WebRTC)
     * and leaves the other new providers untouched.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_peertube_gdpr(): void {
        $on = video_embed::resolve('https://exquisite.tube/w/5P2RS53HxeMyVQ3n3wSGvu', true);
        $this->assertSame(
            'https://exquisite.tube/videos/embed/5P2RS53HxeMyVQ3n3wSGvu?p2p=0',
            $on['src']
        );

        $pod = video_embed::resolve('https://pod.univ.fr/video/123-titre/', true);
        $this->assertSame('https://pod.univ.fr/video/123-titre/?is_iframe=true', $pod['src']);

        $dm = video_embed::resolve('https://dai.ly/x9ekanc', true);
        $this->assertSame('https://geo.dailymotion.com/player.html?video=x9ekanc', $dm['src']);
    }

    /**
     * Regression: the platform extension must not change how YouTube
     * (incl. Shorts) and Vimeo resolve.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_resolve_platform_extension_regressions(): void {
        $yt = video_embed::resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false);
        $this->assertSame('youtube', $yt['provider']);

        $shorts = video_embed::resolve('https://www.youtube.com/shorts/dQw4w9WgXcQ', false);
        $this->assertTrue($shorts['shorts']);

        $vimeo = video_embed::resolve('https://vimeo.com/123456789', false);
        $this->assertSame('vimeo', $vimeo['provider']);
    }

    // Tests for the trusted-host security allowlist.

    /**
     * A host-agnostic embed (PeerTube / Esup-Pod / Opencast / generic)
     * whose host is NOT on the allowlist must resolve to null, so a
     * learner cannot turn the assess screen into an iframe pointing at
     * an arbitrary (phishing / clickjacking) host. The link then
     * degrades to filter_mediaplugin (a plain link).
     *
     * @covers \mod_videoassessment\video_embed::resolve
     * @covers \mod_videoassessment\video_embed::host_is_trusted
     */
    public function test_untrusted_host_is_rejected(): void {
        set_config('trustedembedhosts', "tubes.apps.education.fr", 'videoassessment');

        // PeerTube on an untrusted host: rejected.
        $this->assertNull(
            video_embed::resolve('https://evil.example.com/w/abcdef', false),
            'A PeerTube link on a non-allowlisted host must not embed.'
        );
        // Generic /embed/ pass-through on an untrusted host: rejected —
        // this is the broadest, most dangerous matcher.
        $this->assertNull(
            video_embed::resolve('https://phish.example.com/embed/login', false),
            'A generic embed link on a non-allowlisted host must not embed.'
        );
        // The one allowlisted host still works.
        $ok = video_embed::resolve('https://tubes.apps.education.fr/w/abcdef', false);
        $this->assertSame('peertube', $ok['provider']);
    }

    /**
     * An allowlist entry also covers sub-domains, but only on a dotted
     * boundary, so "youtube.com" must never trust "evilyoutube.com".
     *
     * @covers \mod_videoassessment\video_embed::host_is_trusted
     */
    public function test_host_is_trusted_boundaries(): void {
        set_config('trustedembedhosts', "univ.fr\n# a comment line\n\npod.esup-portail.org", 'videoassessment');

        $this->assertTrue(video_embed::host_is_trusted('univ.fr'));
        $this->assertTrue(video_embed::host_is_trusted('media.univ.fr'));
        $this->assertTrue(video_embed::host_is_trusted('MEDIA.UNIV.FR'), 'matching is case-insensitive');
        $this->assertTrue(video_embed::host_is_trusted('pod.esup-portail.org'));

        $this->assertFalse(video_embed::host_is_trusted('univ.fr.evil.com'));
        $this->assertFalse(video_embed::host_is_trusted('notuniv.fr'), 'suffix must break on a dot');
        $this->assertFalse(video_embed::host_is_trusted('evilunivxfr'));
        $this->assertFalse(video_embed::host_is_trusted(''));
    }

    /**
     * An empty / unset allowlist trusts nothing, so host-agnostic
     * embeds never render (fail closed).
     *
     * @covers \mod_videoassessment\video_embed::host_is_trusted
     */
    public function test_empty_allowlist_trusts_nothing(): void {
        unset_config('trustedembedhosts', 'videoassessment');
        $this->assertFalse(video_embed::host_is_trusted('tubes.apps.education.fr'));
        $this->assertNull(
            video_embed::resolve('https://tubes.apps.education.fr/w/abcdef', false),
            'With no allowlist configured, even a real PeerTube host must not embed.'
        );
    }

    /**
     * The shipped default allowlist must already cover every public
     * platform named in the feature request, so they work out of the
     * box; only self-hosted instances need an admin entry.
     *
     * @covers \mod_videoassessment\video_embed::default_trusted_hosts
     * @covers \mod_videoassessment\video_embed::host_is_trusted
     */
    public function test_default_allowlist_covers_named_platforms(): void {
        set_config('trustedembedhosts', video_embed::default_trusted_hosts(), 'videoassessment');
        $namedhosts = [
            'www.youtube.com',
            'player.vimeo.com',
            'geo.dailymotion.com',
            'www.canal-u.tv',
            'tubes.apps.education.fr',
            'pod.esup-portail.org',
            'exquisite.tube',
        ];
        foreach ($namedhosts as $host) {
            $this->assertTrue(
                video_embed::host_is_trusted($host),
                "The default allowlist should trust {$host}."
            );
        }
        // A random unrelated host is still not trusted by default.
        $this->assertFalse(video_embed::host_is_trusted('evil.example.com'));
    }

    /**
     * Fixed-host providers (YouTube / Vimeo / Dailymotion) emit a known
     * player host and must resolve even when the allowlist is empty —
     * the gate applies only to the host-agnostic providers.
     *
     * @covers \mod_videoassessment\video_embed::resolve
     */
    public function test_fixed_host_providers_bypass_allowlist(): void {
        unset_config('trustedembedhosts', 'videoassessment');
        $this->assertSame(
            'youtube',
            video_embed::resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false)['provider']
        );
        $this->assertSame(
            'vimeo',
            video_embed::resolve('https://vimeo.com/123456789', false)['provider']
        );
        $this->assertSame(
            'dailymotion',
            video_embed::resolve('https://dai.ly/x9ekanc', false)['provider']
        );
    }

    /**
     * Static guard: the renderer must sandbox the external embed iframe
     * to block the meaningful in-frame attacks (form POST, parent
     * navigation, modals) while still granting what real video players
     * need to play (popups + an origin-only referrer). A regression
     * here either re-opens the phishing surface or breaks playback.
     *
     * @coversNothing
     */
    public function test_embed_iframe_is_hardened(): void {
        $renderer = file_get_contents(__DIR__ . '/../classes/renderer/renderer.php');
        $this->assertStringContainsString(
            "'sandbox' =>",
            $renderer,
            'The external embed iframe must declare a sandbox.'
        );

        // The dangerous sandbox tokens must NOT be granted (these
        // strings only appear in the sandbox attribute).
        foreach (['allow-forms', 'allow-top-navigation', 'allow-modals'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $renderer,
                "The embed sandbox must not grant {$forbidden}."
            );
        }
        // Playback needs these.
        foreach (['allow-scripts', 'allow-same-origin', 'allow-popups'] as $needed) {
            $this->assertStringContainsString(
                $needed,
                $renderer,
                "The embed sandbox must grant {$needed} for the player to work."
            );
        }

        // The referrer must carry the origin (YouTube needs it to verify
        // the embedding domain) but not the full page URL: no-referrer
        // breaks YouTube (error 153), unsafe-url leaks the student id.
        $this->assertStringContainsString(
            "'referrerpolicy' => 'strict-origin-when-cross-origin'",
            $renderer,
            'The embed must send an origin-only referrer (no-referrer '
                . 'breaks YouTube embedding; the full URL would leak the '
                . 'student id).'
        );

        $this->assertStringNotContainsString(
            'clipboard-write',
            $renderer,
            'clipboard-write is a phishing aid and is not needed for '
                . 'video playback.'
        );
    }

    // Tests for video_embed::gdpr_enabled.

    /**
     * The GDPR toggle defaults to ON (privacy by default) when unset,
     * and reflects the stored config otherwise.
     *
     * @covers \mod_videoassessment\video_embed::gdpr_enabled
     */
    public function test_gdpr_enabled_setting(): void {
        $this->resetAfterTest();

        unset_config('gdprcookiesuppression', 'videoassessment');
        $this->assertTrue(video_embed::gdpr_enabled(), 'unset GDPR toggle defaults ON (privacy by default)');

        set_config('gdprcookiesuppression', 0, 'videoassessment');
        $this->assertFalse(video_embed::gdpr_enabled());

        set_config('gdprcookiesuppression', 1, 'videoassessment');
        $this->assertTrue(video_embed::gdpr_enabled());
    }
}
