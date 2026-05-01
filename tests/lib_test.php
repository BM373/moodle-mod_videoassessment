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
 * Unit tests for the lib.php public Moodle activity API.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/videoassessment/lib.php');

/**
 * Tests for the top-level Moodle hooks declared in `mod/videoassessment/lib.php`.
 *
 * The hooks are reached by Moodle through string lookup of
 * `videoassessment_<name>` and `mod_videoassessment_<name>` functions, so
 * the test names are kept verbatim to make grep-based audits easy.
 */
final class lib_test extends \advanced_testcase {
    /**
     * `videoassessment_supports()` must answer the canonical feature
     * matrix Moodle uses to decide which UI affordances to show.
     *
     * @covers ::videoassessment_supports
     */
    public function test_supports_known_features_return_true(): void {
        $this->assertTrue(videoassessment_supports(FEATURE_GROUPS));
        $this->assertTrue(videoassessment_supports(FEATURE_GROUPINGS));
        $this->assertTrue(videoassessment_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(videoassessment_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(videoassessment_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(videoassessment_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertTrue(videoassessment_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(videoassessment_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(videoassessment_supports(FEATURE_ADVANCED_GRADING));
        $this->assertTrue(videoassessment_supports(FEATURE_IDNUMBER));
    }

    /**
     * The activity is purposed for assessment, so MOD_PURPOSE must be
     * `MOD_PURPOSE_ASSESSMENT` (Moodle 4.0+ uses this for activity
     * chooser categorisation).
     *
     * @covers ::videoassessment_supports
     */
    public function test_supports_mod_purpose_is_assessment(): void {
        $this->assertSame(MOD_PURPOSE_ASSESSMENT, videoassessment_supports(FEATURE_MOD_PURPOSE));
    }

    /**
     * Unknown features must return `null`, not `false`. Moodle uses
     * the null sentinel to fall back to the framework default.
     *
     * @covers ::videoassessment_supports
     */
    public function test_supports_unknown_feature_returns_null(): void {
        $this->assertNull(videoassessment_supports('this_feature_does_not_exist'));
        $this->assertNull(videoassessment_supports(''));
    }

    /**
     * `videoassessment_grading_areas_list()` must return a non-empty
     * map of grading-area keys to display labels covering at least
     * before/after × self/peer/teacher/class.
     *
     * @covers ::videoassessment_grading_areas_list
     */
    public function test_grading_areas_list_covers_all_combinations(): void {
        $areas = videoassessment_grading_areas_list();
        $this->assertIsArray($areas);
        // 2 timings × 4 grader types = 8 areas at minimum.
        $this->assertGreaterThanOrEqual(8, count($areas));
        foreach (['before', 'after'] as $timing) {
            foreach (['self', 'peer', 'teacher', 'class'] as $grader) {
                $this->assertArrayHasKey(
                    $timing . $grader,
                    $areas,
                    "Missing grading area: {$timing}{$grader}"
                );
            }
        }
    }

    /**
     * `videoassessment_handle_post_save_rubric_redirect()` must NOT
     * set the redirect-to-grading user preference when neither the
     * `redirect_to_rubric` form field nor the corresponding `$_POST`
     * key is present.
     *
     * @covers ::videoassessment_handle_post_save_rubric_redirect
     */
    public function test_redirect_helper_no_preference_when_not_clicked(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // No redirect_to_rubric anywhere.
        $va = (object) ['id' => 1234, 'peerassignments' => '{}'];
        unset($_POST['redirect_to_rubric']);
        videoassessment_handle_post_save_rubric_redirect($va);

        $this->assertEmpty(get_user_preferences('videoassessment_redirect_to_grading'));
    }

    /**
     * The helper must persist the preference (with a timestamp suffix)
     * when the form data carries the redirect flag.
     *
     * @covers ::videoassessment_handle_post_save_rubric_redirect
     */
    public function test_redirect_helper_sets_preference_when_form_field_set(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $va = (object) [
            'id' => 4242,
            'peerassignments' => '{}',
            'redirect_to_rubric' => '1',
        ];
        unset($_POST['redirect_to_rubric']);
        videoassessment_handle_post_save_rubric_redirect($va);

        $stored = get_user_preferences('videoassessment_redirect_to_grading');
        $this->assertNotEmpty($stored);
        // Format: "<vaid>:<timestamp>".
        $this->assertMatchesRegularExpression('/^4242:\d+$/', $stored);
    }

    /**
     * The helper must also pick up the redirect flag straight off
     * `$_POST` (the canonical path on a real form submission).
     *
     * @covers ::videoassessment_handle_post_save_rubric_redirect
     */
    public function test_redirect_helper_sets_preference_when_post_field_set(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $va = (object) ['id' => 5555, 'peerassignments' => '{}'];
        $_POST['redirect_to_rubric'] = '1';
        try {
            videoassessment_handle_post_save_rubric_redirect($va);
            $stored = get_user_preferences('videoassessment_redirect_to_grading');
            $this->assertNotEmpty($stored);
            $this->assertMatchesRegularExpression('/^5555:\d+$/', $stored);
        } finally {
            unset($_POST['redirect_to_rubric']);
        }
    }

    /**
     * When the user previously had the preference set but did not
     * click the rubric button this time, the helper must clear the
     * stale preference so they don't get redirected unexpectedly.
     *
     * @covers ::videoassessment_handle_post_save_rubric_redirect
     */
    public function test_redirect_helper_clears_stale_preference(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Pre-existing preference from an earlier session.
        set_user_preference('videoassessment_redirect_to_grading', '999:0');

        // No redirect indicator on this submission.
        $va = (object) ['id' => 1, 'peerassignments' => '{}'];
        unset($_POST['redirect_to_rubric']);
        videoassessment_handle_post_save_rubric_redirect($va);

        $this->assertEmpty(get_user_preferences('videoassessment_redirect_to_grading'));
    }

    /**
     * When the redirect flag is NOT set, advancedgradingmethod_*
     * fields must be stripped both from the activity data and from
     * `$_POST` so Moodle core does not redirect to its grading
     * management page.
     *
     * @covers ::videoassessment_handle_post_save_rubric_redirect
     */
    public function test_redirect_helper_strips_advancedgradingmethod(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $va = (object) [
            'id' => 1,
            'peerassignments' => '{}',
            'advancedgradingmethod_beforeself' => 'rubric',
            'advancedgradingmethod_afterteacher' => 'rubric',
            'unrelated_field' => 'kept',
        ];
        $_POST['advancedgradingmethod_beforeself'] = 'rubric';
        $_POST['unrelated_post_field'] = 'kept';
        unset($_POST['redirect_to_rubric']);

        try {
            videoassessment_handle_post_save_rubric_redirect($va);
            $this->assertObjectNotHasProperty('advancedgradingmethod_beforeself', $va);
            $this->assertObjectNotHasProperty('advancedgradingmethod_afterteacher', $va);
            $this->assertObjectHasProperty('unrelated_field', $va);
            $this->assertArrayNotHasKey('advancedgradingmethod_beforeself', $_POST);
            $this->assertArrayHasKey('unrelated_post_field', $_POST);
        } finally {
            unset($_POST['advancedgradingmethod_beforeself'], $_POST['unrelated_post_field']);
        }
    }

    /**
     * `videoassessment_show_intro()` must return a truthy value when
     * `showdescription` is set; the new ?? guard means the function
     * works even when the activity row predates the column.
     *
     * @covers ::videoassessment_show_intro
     */
    public function test_show_intro_with_showdescription_set(): void {
        $va = (object) ['showdescription' => 1, 'allowsubmissionsfromdate' => 0];
        $this->assertNotEmpty(videoassessment_show_intro($va));
    }

    /**
     * `videoassessment_show_intro()` must NOT throw when
     * showdescription is missing entirely (legacy schema rows).
     *
     * @covers ::videoassessment_show_intro
     */
    public function test_show_intro_without_showdescription_does_not_throw(): void {
        $va = new \stdClass();
        // No properties; helper must coerce the missing column to 0.
        $result = videoassessment_show_intro($va);
        // Either 0 or empty/null is acceptable; "no exception" is the
        // whole contract of the regression we're guarding here.
        $this->assertTrue(in_array($result, [0, false, null], true) || empty($result));
    }

    /**
     * `videoassessment_check_has_grade()` returns a map
     * `gradingarea => bool` covering whether at least one row exists
     * in `videoassessment_grade_items` for that area.
     *
     * For a freshly-created activity with no grades yet, every area
     * must be reported false.
     *
     * @covers ::videoassessment_check_has_grade
     */
    public function test_check_has_grade_for_empty_instance_is_all_false(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $va = $generator->create_instance(['course' => $course->id]);

        $result = videoassessment_check_has_grade($va->id);
        $this->assertIsArray($result);
        // Every grading area must be present and false.
        foreach (videoassessment_grading_areas_list() as $key => $name) {
            $this->assertArrayHasKey($key, $result);
            $this->assertFalse(
                $result[$key],
                "Area {$key} should be reported as 'no grade' for a fresh activity."
            );
        }
    }

    /**
     * After persisting a grade_items row for one of the grading areas,
     * `videoassessment_check_has_grade()` must report that single area
     * as true and the rest as false.
     *
     * @covers ::videoassessment_check_has_grade
     */
    public function test_check_has_grade_reports_only_seeded_area(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $va = $generator->create_instance(['course' => $course->id]);

        $DB->insert_record('videoassessment_grade_items', (object) [
            'videoassessment' => $va->id,
            'type' => 'beforeteacher',
            'gradeduser' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = videoassessment_check_has_grade($va->id);
        $this->assertTrue($result['beforeteacher']);
        $this->assertFalse($result['afterteacher']);
        $this->assertFalse($result['beforepeer']);
    }

    /**
     * `videoassessment_save_peer_assignments()` must accept a JSON
     * payload of `{userid: [peerids...]}` and persist it.
     *
     * @covers ::videoassessment_save_peer_assignments
     */
    public function test_save_peer_assignments_persists_records(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $va = $generator->create_instance(['course' => $course->id]);

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        // Two peers for u1, one peer for u2.
        $payload = json_encode([
            (string) $u1->id => [$u2->id, $u3->id],
            (string) $u2->id => [$u3->id],
        ]);
        videoassessment_save_peer_assignments($va->id, $payload);

        $rows = $DB->get_records('videoassessment_peers', ['videoassessment' => $va->id]);
        $this->assertCount(3, $rows);
    }

    /**
     * The empty-JSON-object case is a no-op: the persist function
     * must not raise when nothing is mapped.
     *
     * @covers ::videoassessment_save_peer_assignments
     */
    public function test_save_peer_assignments_empty_payload_is_noop(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $va = $generator->create_instance(['course' => $course->id]);

        videoassessment_save_peer_assignments($va->id, '{}');
        $rows = $DB->get_records('videoassessment_peers', ['videoassessment' => $va->id]);
        $this->assertCount(0, $rows);
    }

    /**
     * Boundary: invalid JSON in the peer payload is silently ignored
     * (the function must not throw a parse error that would 500 the
     * whole save).
     *
     * @covers ::videoassessment_save_peer_assignments
     */
    public function test_save_peer_assignments_invalid_json_does_not_throw(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $va = $generator->create_instance(['course' => $course->id]);

        // Should be a no-op, not an exception.
        videoassessment_save_peer_assignments($va->id, 'this is not json');
        $rows = $DB->get_records('videoassessment_peers', ['videoassessment' => $va->id]);
        $this->assertCount(0, $rows);
    }

    /**
     * `videoassessment_get_assoc()` parses a stored_file path of the
     * form `<userid>/<timing>/...` and returns `[userid, timing]` or
     * null if the path does not match.
     *
     * Use a real stored_file built via core's file_storage to keep
     * the test isolated from any specific file fixture on disk.
     *
     * @covers ::videoassessment_get_assoc
     */
    public function test_get_assoc_extracts_userid_and_timing(): void {
        $this->resetAfterTest();
        $fs = get_file_storage();
        $context = \context_system::instance();
        $f = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/42/before/',
            'filename' => 'sample.webm',
        ], 'fake');
        $result = videoassessment_get_assoc($f);
        $this->assertSame([42, 'before'], $result);
    }

    /**
     * Boundary: a path with an invalid timing must yield null.
     *
     * @covers ::videoassessment_get_assoc
     */
    public function test_get_assoc_invalid_timing_returns_null(): void {
        $this->resetAfterTest();
        $fs = get_file_storage();
        $context = \context_system::instance();
        $f = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/42/middle/',
            'filename' => 'sample.webm',
        ], 'fake');
        $result = videoassessment_get_assoc($f);
        $this->assertNull($result);
    }

    /**
     * Boundary: a path with userid=0 yields null (userid must be > 0).
     *
     * @covers ::videoassessment_get_assoc
     */
    public function test_get_assoc_userid_zero_returns_null(): void {
        $this->resetAfterTest();
        $fs = get_file_storage();
        $context = \context_system::instance();
        $f = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'video',
            'itemid' => 0,
            'filepath' => '/0/before/',
            'filename' => 'sample.webm',
        ], 'fake');
        $result = videoassessment_get_assoc($f);
        $this->assertNull($result);
    }
}
