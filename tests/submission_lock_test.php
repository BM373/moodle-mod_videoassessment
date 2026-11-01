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
 * Tests for locking an assessed video and the teacher's reset.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * 2026-07 request #2: a video can be changed freely until someone
 * assesses it; after that only the teacher's "Delete video / link and
 * assessments" reset can change it, so feedback always matches the
 * video it was written for.
 *
 * @covers \mod_videoassessment\va
 */
final class submission_lock_test extends \advanced_testcase {
    /** @var va */
    private $va;
    /** @var \context_module */
    private $context;
    /** @var int */
    private $student;
    /** @var int */
    private $other;
    /** @var int */
    private $teacher;

    /**
     * Build a course with one activity, two learners and a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('videoassessment', [
            'course' => $course->id,
            'ratingteacher' => 80,
            'ratingself' => 10,
            'ratingpeer' => 10,
            'ratingclass' => 0,
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $activity->id, $course->id, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->va = new va($this->context, $cm, $course);

        $this->student = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->other = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->teacher = (int) $this->getDataGenerator()->create_and_enrol($course, 'editingteacher')->id;
    }

    /**
     * Clear request parameters set by tests.
     */
    protected function tearDown(): void {
        unset($_POST['videoid']);
        parent::tearDown();
    }

    /**
     * Store an uploaded video (with a real file) and associate it.
     *
     * @param int[] $userids Learners the video is associated with.
     * @return int videoassessment_videos.id
     */
    private function add_video(array $userids): int {
        global $DB;

        $name = 'video' . random_string(6);
        $videoid = $DB->insert_record('videoassessment_videos', (object) [
            'videoassessment' => $this->va->instance,
            'filepath' => '/',
            'filename' => $name . '.mp4',
            'thumbnailname' => $name . '.jpg',
            'tmpname' => $name,
            'originalname' => $name . '.mp4',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $fs = get_file_storage();
        foreach (['.mp4', '.jpg'] as $ext) {
            $fs->create_file_from_string([
                'contextid' => $this->context->id,
                'component' => 'mod_videoassessment',
                'filearea' => 'video',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $name . $ext,
            ], 'content');
        }
        foreach ($userids as $userid) {
            $DB->insert_record('videoassessment_video_assocs', (object) [
                'videoassessment' => $this->va->instance,
                'videoid' => $videoid,
                'associationtype' => 1,
                'timing' => 'before',
                'associationid' => $userid,
                'timemodified' => time(),
            ]);
        }
        return $videoid;
    }

    /**
     * Save an assessment row the way view_assess() does.
     *
     * @param int $gradeduser Learner.
     * @param int $grader Assessor.
     * @param string $gradertype self, peer, teacher, class or training.
     * @return int videoassessment_grades.id
     */
    private function add_grade(int $gradeduser, int $grader, string $gradertype): int {
        global $DB;

        $itemid = $this->va->get_grade_item('before' . $gradertype, $gradeduser, $grader);
        $gradeid = $DB->insert_record('videoassessment_grades', (object) [
            'videoassessment' => $this->va->instance,
            'gradeitem' => $itemid,
            'timemarked' => time(),
            'grade' => 70,
            'submissioncomment' => 'Good',
        ]);
        $this->va->aggregate_grades($gradeduser);
        return $gradeid;
    }

    /**
     * Grader types that lock the video.
     *
     * @return array
     */
    public static function locking_types(): array {
        return [['self'], ['peer'], ['teacher'], ['class']];
    }

    /**
     * Any saved self, peer, teacher or class assessment locks the video.
     *
     * @dataProvider locking_types
     * @param string $gradertype
     */
    public function test_saved_assessment_locks_video(string $gradertype): void {
        $this->assertFalse($this->va->has_started_assessment($this->student));

        $grader = $gradertype === 'self' ? $this->student : $this->teacher;
        $this->add_grade($this->student, $grader, $gradertype);

        $this->assertTrue($this->va->has_started_assessment($this->student));
        $this->assertFalse($this->va->has_started_assessment($this->other));
    }

    /**
     * Training pre-test grades and merely opened assess pages do not lock.
     */
    public function test_training_and_opened_form_do_not_lock(): void {
        $this->add_grade($this->student, $this->student, 'training');
        // Opening the assess page creates a grade item but no grade.
        $this->va->get_grade_item('beforeteacher', $this->student, $this->teacher);

        $this->assertFalse($this->va->has_started_assessment($this->student));
    }

    /**
     * The reset deletes the learner's video, assessments, rubric fillings,
     * comment files, aggregated grades and report visits, and nothing else.
     */
    public function test_reset_submission_clears_only_that_learner(): void {
        global $DB;

        $videoid = $this->add_video([$this->student]);
        $othervideo = $this->add_video([$this->other]);
        $gradeid = $this->add_grade($this->student, $this->teacher, 'teacher');
        $this->add_grade($this->student, $this->other, 'peer');
        $this->add_grade($this->other, $this->teacher, 'teacher');
        report_tracker::mark_viewed((int) $this->va->instance, $this->student);
        report_tracker::mark_viewed((int) $this->va->instance, $this->other);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'submissioncomment',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'feedback.webm',
        ], 'feedback');

        $this->setUser($this->teacher);
        $rubricgenerator = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgenerator->get_test_rubric($this->context, 'mod_videoassessment', 'beforeteacher');
        $studentitem = $this->va->get_grade_item('beforeteacher', $this->student, $this->teacher);
        $otheritem = $this->va->get_grade_item('beforeteacher', $this->other, $this->teacher);
        foreach ([$studentitem, $otheritem] as $itemid) {
            $controller->create_instance($this->teacher, $itemid)->update(
                $rubricgenerator->get_test_form_data($controller, $itemid, 1, 'Remark', 2, 'Remark')
            );
        }

        $this->assertTrue($this->va->has_started_assessment($this->student));
        $this->va->reset_submission($this->student);

        $this->assertFalse($this->va->has_started_assessment($this->student));
        $this->assertNull($this->va->get_associated_video($this->student, 'before'));
        $this->assertFalse($DB->record_exists('videoassessment_videos', ['id' => $videoid]));
        $this->assertEmpty($fs->get_area_files($this->context->id, 'mod_videoassessment', 'submissioncomment', $gradeid));
        $this->assertFalse($DB->record_exists('grading_instances', ['itemid' => $studentitem]));
        $this->assertFalse($DB->record_exists('videoassessment_grade_items', ['gradeduser' => $this->student]));
        $this->assertSame(-1, (int) $this->va->get_aggregated_grades($this->student)->gradebeforeteacher);
        $this->assertSame(0, report_tracker::get_last_viewed((int) $this->va->instance, $this->student));

        $gradinginfo = grade_get_grades($this->va->course->id, 'mod', 'videoassessment', $this->va->instance, $this->student);
        $this->assertNull(reset($gradinginfo->items)->grades[$this->student]->grade);

        // The other learner is untouched.
        $this->assertTrue($DB->record_exists('videoassessment_videos', ['id' => $othervideo]));
        $this->assertTrue($this->va->has_started_assessment($this->other));
        $this->assertTrue($DB->record_exists('grading_instances', ['itemid' => $otheritem]));
        $this->assertGreaterThan(0, report_tracker::get_last_viewed((int) $this->va->instance, $this->other));
    }

    /**
     * A video shared with another learner keeps its file on reset.
     */
    public function test_reset_keeps_video_shared_with_other_learner(): void {
        global $DB;

        $videoid = $this->add_video([$this->student, $this->other]);
        $this->add_grade($this->student, $this->teacher, 'teacher');

        $this->va->reset_submission($this->student);

        $this->assertTrue($DB->record_exists('videoassessment_videos', ['id' => $videoid]));
        $this->assertNull($this->va->get_associated_video($this->student, 'before'));
        $this->assertNotNull($this->va->get_associated_video($this->other, 'before'));
    }

    /**
     * The assess form's video signature follows the association.
     */
    public function test_video_signature_tracks_association(): void {
        $this->assertSame('0-0', $this->va->get_video_signature($this->student));

        $videoid = $this->add_video([$this->student]);
        $this->assertSame($videoid . '-0', $this->va->get_video_signature($this->student));
    }

    /**
     * A learner cannot delete their video once it has been assessed.
     */
    public function test_learner_cannot_delete_assessed_video(): void {
        global $DB;

        $videoid = $this->add_video([$this->student]);
        $this->add_grade($this->student, $this->teacher, 'teacher');
        $this->setUser($this->student);
        $_POST['videoid'] = $videoid;

        try {
            $this->va->delete_one_video_by_id();
            $this->fail('Expected the locked video to be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('videolocked', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('videoassessment_videos', ['id' => $videoid]));
    }

    /**
     * A learner cannot delete another learner's video.
     */
    public function test_learner_cannot_delete_someone_elses_video(): void {
        global $DB;

        $videoid = $this->add_video([$this->other]);
        $this->setUser($this->student);
        $_POST['videoid'] = $videoid;

        try {
            $this->va->delete_one_video_by_id();
            $this->fail('Expected the foreign video to be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('videoassessment_videos', ['id' => $videoid]));
    }

    /**
     * Before any assessment the learner may still delete their own video.
     */
    public function test_learner_can_delete_unassessed_video(): void {
        global $DB;

        $videoid = $this->add_video([$this->student]);
        $this->setUser($this->student);
        $_POST['videoid'] = $videoid;

        try {
            $this->va->delete_one_video_by_id();
        } catch (\moodle_exception $e) {
            // The redirect back to the view page throws under PHPUnit.
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('videoassessment_videos', ['id' => $videoid]));
    }
}
