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
 * Regression tests for the feedback-comment video display path.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Item #6 of the 2026-04 fix programme: videos recorded inside the
 * teacher's "Feedback Box" editor were not playing back when the
 * student opened the comment again. The cause was the well-known
 * trio of `@@PLUGINFILE@@` placeholder, `file_rewrite_pluginfile_urls`
 * and `format_text` -- if any of those steps strips or fails to
 * rewrite the `<video>` tag, the resulting HTML loses the playable
 * source.
 *
 * These regression tests pin the contract by running the same pipeline
 * the activity uses (in `view.php`'s `getallcomments` AJAX endpoint
 * and the assessment screens) on a synthetic feedback string and
 * asserting that the resulting HTML still contains an HTML5 `<video>`
 * element with a URL pointing to the plugin's `submissioncomment`
 * file area.
 */
final class feedback_video_display_test extends \advanced_testcase {
    /**
     * Confirm `<video>` and `<source>` tags survive the rewrite
     * + format_text pipeline used to render feedback comments.
     *
     * @coversNothing
     */
    public function test_video_tag_preserved_through_display_pipeline(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Mimic the saved feedback content: a recorded WebM produced by
        // Moodle's RecordRTC editor plugin uses an @@PLUGINFILE@@
        // placeholder so the URL is portable across instances.
        $stored = '<p>Great work!</p>'
            . '<video controls="true">'
            . '<source src="@@PLUGINFILE@@/recording.webm" type="video/webm">'
            . '</video>';

        // The display path (mirroring view.php's getallcomments branch).
        $rewritten = file_rewrite_pluginfile_urls(
            $stored,
            'pluginfile.php',
            $context->id,
            'mod_videoassessment',
            'submissioncomment',
            42
        );
        $rendered = format_text($rewritten, FORMAT_HTML, ['context' => $context, 'noclean' => true]);

        // The placeholder must be expanded to a pluginfile URL pointing
        // at the submissioncomment area on this context.
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $rendered);
        $this->assertStringContainsString('pluginfile.php', $rendered);
        $this->assertStringContainsString('/mod_videoassessment/submissioncomment/', $rendered);
        $this->assertStringContainsString('recording.webm', $rendered);

        // The video markup must still be present so the browser can
        // pick it up (HTML purifier sometimes strips media tags when
        // not run with the right cleaning policy).
        $this->assertMatchesRegularExpression('~<video\b[^>]*>~i', $rendered);
        $this->assertMatchesRegularExpression('~<source\b[^>]*>~i', $rendered);
    }

    /**
     * Static guard for the mobile "show all comments" modal
     * (externallib get_getallcomments). On smartphones the modal showed
     * an escaped JSON string and a leaked @@PLUGINFILE@@ because the
     * handler json_encode()'d the HTML and skipped the rewrite/format
     * step.
     *
     * @coversNothing
     */
    public function test_getallcomments_returns_formatted_html_not_json(): void {
        $src = file_get_contents(__DIR__ . '/../externallib.php');

        // Isolate the get_getallcomments function body.
        $start = strpos($src, 'function get_getallcomments(');
        $this->assertNotFalse($start, 'get_getallcomments must exist.');
        $end = strpos($src, 'function ', $start + 1);
        $body = substr($src, $start, $end - $start);

        $this->assertStringNotContainsString(
            'json_encode(',
            $body,
            'get_getallcomments must return the HTML as-is; json_encode() '
                . 'double-encoded it so the modal body showed an escaped '
                . 'JSON string.'
        );
        $this->assertStringContainsString(
            'file_rewrite_pluginfile_urls(',
            $body,
            'get_getallcomments must rewrite @@PLUGINFILE@@ so the modal '
                . 'does not leak the placeholder and the video plays.'
        );
        $this->assertStringContainsString(
            'format_text(',
            $body,
            'get_getallcomments must format the comment HTML.'
        );
        $this->assertStringContainsString(
            "get_coursemodule_from_id('videoassessment'",
            $body,
            'get_getallcomments must resolve the passed cmid to the '
                . 'activity instance, not use it directly as the '
                . 'videoassessment id.'
        );
    }

    /**
     * Static guard: the mobile comment preview must be built from the
     * FORMATTED comment, the modal-opening button must carry the real
     * course-module id, and the "generalcomments" string must be loaded
     * for the modal title.
     *
     * @coversNothing
     */
    public function test_mobile_comment_preview_and_button_wiring(): void {
        $va = file_get_contents(__DIR__ . '/../classes/va.php');
        $this->assertStringContainsString(
            'strip_tags($formattedcomment)',
            $va,
            'The mobile comment preview must strip the FORMATTED comment '
                . 'so no @@PLUGINFILE@@ placeholder leaks into the teaser.'
        );
        $this->assertStringContainsString(
            '$this->cm->id',
            $va,
            'The mobile comment button must pass the real course-module '
                . 'id to the modal external function.'
        );
        $this->assertStringContainsString(
            "'generalcomments'",
            $va,
            'The generalcomments string must be loaded for JS so the '
                . 'modal title is not "undefined".'
        );
    }

    // The capability-check branch of mod_videoassessment_pluginfile()
    // ends in send_file_not_found(), which calls header(). PHPUnit
    // always has output already buffered, so a unit test that
    // exercises that branch produces a "Cannot modify header
    // information" warning that --fail-on-warning then promotes to a
    // failure. Coverage of the capability path is intentionally left
    // to a Behat scenario instead; this test pins the format_text +
    // file_rewrite_pluginfile_urls contract, which is the actual root
    // cause that #6 fixes.
}
