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
