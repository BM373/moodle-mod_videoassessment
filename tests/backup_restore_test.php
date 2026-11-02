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
 * Tests for backing up and restoring a Video Assessment activity.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use mod_videoassessment\aifeedback\attempt_manager;

/**
 * A course backup copies every activity setting, and with user data the
 * videos, assessments, rubric fillings, peers, report visits and AI
 * speech feedback attempts.
 *
 * @covers \backup_videoassessment_activity_structure_step
 * @covers \restore_videoassessment_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var int Start date of the original course. */
    const START = 1775001600;

    /** @var string[] Date columns that move with the course start date. */
    const DATES = ['timedue', 'timeavailable', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'nextsendmaildate'];

    /** @var string[] Columns the restore sets itself, or that the generator owns. */
    const NOT_COMPARED = ['id', 'course', 'name', 'intro', 'introformat', 'grade', 'trainingvideo', 'trainingvideoid'];

    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Course with an activity whose every setting differs from its default, and a description image.
     *
     * @return array{course: \stdClass, cm: \stdClass, context: \context_module, va: \stdClass}
     */
    private function setup_activity(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => self::START]);
        $activity = $this->getDataGenerator()->create_module('videoassessment', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('videoassessment', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Set each column from the table definition, so a column added later
        // without a backup field makes this test fail.
        $record = ['id' => $activity->id];
        $day = 0;
        foreach ($DB->get_columns('videoassessment') as $name => $column) {
            if (in_array($name, self::NOT_COMPARED, true)) {
                continue;
            }
            if (in_array($name, self::DATES, true)) {
                $record[$name] = self::START + (++$day) * DAYSECS;
            } else if ($column->meta_type === 'I') {
                $record[$name] = $column->max_length == 1 ? 1 : 7;
            } else if ($column->meta_type === 'N') {
                $record[$name] = 12.5;
            } else if ($column->meta_type === 'C') {
                $record[$name] = substr('restored-' . $name, 0, $column->max_length);
            } else {
                $record[$name] = "Text of {$name}";
            }
        }
        $DB->update_record('videoassessment', (object)$record);

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'diagram.png',
        ], 'png');

        return [
            'course' => $course,
            'cm' => $cm,
            'context' => $context,
            'va' => $DB->get_record('videoassessment', ['id' => $activity->id]),
        ];
    }

    /**
     * Store a video row with its two files.
     *
     * @param \context_module $context Activity context.
     * @param int $vaid Activity id.
     * @param string $name Base file name.
     * @return int videoassessment_videos.id
     */
    private function add_video(\context_module $context, int $vaid, string $name): int {
        global $DB;

        $videoid = $DB->insert_record('videoassessment_videos', (object)[
            'videoassessment' => $vaid,
            'filepath' => '/',
            'filename' => $name . '.mp4',
            'thumbnailname' => $name . '.jpg',
            'tmpname' => $name,
            'originalname' => $name . '-original.mov',
            'timecreated' => self::START,
            'timemodified' => self::START,
        ]);
        foreach (['.mp4', '.jpg'] as $ext) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_videoassessment',
                'filearea' => 'video',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $name . $ext,
            ], $name . $ext);
        }
        return $videoid;
    }

    /**
     * Back the course up and restore it as a new course.
     *
     * @param \stdClass $course The course.
     * @param bool $users Whether the backup includes user data.
     * @param int $startdate Start date of the new course, 0 to keep the original one.
     * @return int The new course id.
     */
    private function backup_and_restore(\stdClass $course, bool $users, int $startdate = 0): int {
        global $CFG, $USER;

        // MODE_IMPORT leaves the backup unzipped for the restore; it locks
        // user data off, so the setting is unlocked first (as core tests do).
        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($users);
        if ($startdate) {
            $rc->get_plan()->get_setting('course_startdate')->set_value($startdate);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * The restored activity of a course.
     *
     * @param int $courseid Course id.
     * @return array{va: \stdClass, context: \context_module}
     */
    private function restored(int $courseid): array {
        global $DB;
        $va = $DB->get_record('videoassessment', ['course' => $courseid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, $courseid, false, MUST_EXIST);
        return ['va' => $va, 'context' => \context_module::instance($cm->id)];
    }

    /**
     * Every setting is copied and dates follow the new course start date;
     * without user data no video, assessment or file of a learner is copied.
     */
    public function test_settings_restored_without_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course, 'context' => $context, 'va' => $va] = $this->setup_activity();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $videoid = $this->add_video($context, $va->id, 'training');
        $DB->set_field('videoassessment', 'trainingvideoid', $videoid, ['id' => $va->id]);
        $DB->insert_record('videoassessment_report_views', ['videoassessment' => $va->id, 'userid' => $student->id,
            'timeviewed' => self::START]);

        $shift = 30 * DAYSECS;
        $newcourseid = $this->backup_and_restore($course, false, self::START + $shift);
        ['va' => $newva, 'context' => $newcontext] = $this->restored($newcourseid);

        foreach ($DB->get_columns('videoassessment') as $name => $column) {
            if (in_array($name, self::NOT_COMPARED, true)) {
                continue;
            }
            $expected = in_array($name, self::DATES, true) ? $va->$name + $shift : $va->$name;
            $this->assertEquals($expected, $newva->$name, "Column {$name}");
        }
        $this->assertSame($va->name, $newva->name);
        $this->assertEquals(0, $newva->trainingvideoid);

        $fs = get_file_storage();
        $this->assertTrue($fs->file_exists($newcontext->id, 'mod_videoassessment', 'intro', 0, '/', 'diagram.png'));
        $this->assertTrue($fs->is_area_empty($newcontext->id, 'mod_videoassessment', 'video'));
        foreach (['videoassessment_videos', 'videoassessment_report_views'] as $table) {
            $this->assertSame(0, $DB->count_records($table, ['videoassessment' => $newva->id]), $table);
        }
    }

    /**
     * Videos, their learners, peers, assessments with comment files and rubric
     * fillings, aggregated grades and report visits are restored and linked to
     * the restored records; the training video is linked again.
     */
    public function test_user_data_restored(): void {
        global $DB;
        $this->resetAfterTest();
        ['course' => $course, 'cm' => $cm, 'context' => $context, 'va' => $varecord] = $this->setup_activity();
        $learner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $peer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $va = new va($context, $cm, $course);

        $videoid = $this->add_video($context, $varecord->id, 'learner');
        $DB->insert_record('videoassessment_video_assocs', (object)[
            'videoassessment' => $varecord->id,
            'videoid' => $videoid,
            'associationtype' => 1,
            'timing' => 'before',
            'associationid' => $learner->id,
            'timemodified' => self::START,
        ]);
        $trainingid = $this->add_video($context, $varecord->id, 'training');
        $DB->set_field('videoassessment', 'trainingvideoid', $trainingid, ['id' => $varecord->id]);
        $DB->insert_record('videoassessment_peers', (object)[
            'videoassessment' => $varecord->id, 'userid' => $learner->id, 'peerid' => $peer->id,
        ]);

        $itemid = $va->get_grade_item('beforeteacher', $learner->id, $teacher->id);
        $gradeid = $DB->insert_record('videoassessment_grades', (object)[
            'videoassessment' => $varecord->id,
            'gradeitem' => $itemid,
            'timemarked' => self::START,
            'grade' => 70,
            'submissioncomment' => '<p>See the attached clip.</p>',
            'mailed' => 1,
            'isnotifystudent' => 1,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'filearea' => 'submissioncomment',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'feedback.webm',
        ], 'feedback');

        $this->setUser($teacher);
        $rubricgenerator = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgenerator->get_test_rubric($context, 'mod_videoassessment', 'beforeteacher');
        $controller->create_instance($teacher->id, $itemid)->update(
            $rubricgenerator->get_test_form_data($controller, $itemid, 1, 'Clear voice', 2, 'Good slides')
        );

        $DB->insert_record('videoassessment_aggregation', (object)[
            'videoassessment' => $varecord->id,
            'userid' => $learner->id,
            'timing' => 'before',
            'timemodified' => self::START,
            'grade' => 70,
            'gradebeforeteacher' => 70,
            'gradebeforeclass' => 55,
            'gradebeforetraining' => 66,
            'passtraining' => 1,
        ]);
        $DB->insert_record('videoassessment_report_views', (object)[
            'videoassessment' => $varecord->id, 'userid' => $learner->id, 'timeviewed' => self::START + 99,
        ]);

        $this->setAdminUser();
        $newcourseid = $this->backup_and_restore($course, true);
        ['va' => $newva, 'context' => $newcontext] = $this->restored($newcourseid);
        $fs = get_file_storage();

        $videos = $DB->get_records('videoassessment_videos', ['videoassessment' => $newva->id], '', 'tmpname, id, filename');
        $this->assertSame(['learner', 'training'], array_keys($videos));
        $this->assertEquals($videos['training']->id, $newva->trainingvideoid);
        foreach (['learner.mp4', 'learner.jpg', 'training.mp4', 'training.jpg'] as $filename) {
            $this->assertTrue($fs->file_exists($newcontext->id, 'mod_videoassessment', 'video', 0, '/', $filename), $filename);
        }
        $assoc = $DB->get_record('videoassessment_video_assocs', ['videoassessment' => $newva->id], '*', MUST_EXIST);
        $this->assertEquals($videos['learner']->id, $assoc->videoid);
        $this->assertEquals($learner->id, $assoc->associationid);
        $this->assertTrue($DB->record_exists('videoassessment_peers', [
            'videoassessment' => $newva->id, 'userid' => $learner->id, 'peerid' => $peer->id,
        ]));

        $newitem = $DB->get_record('videoassessment_grade_items', ['videoassessment' => $newva->id], '*', MUST_EXIST);
        $this->assertSame('beforeteacher', $newitem->type);
        $this->assertEquals($learner->id, $newitem->gradeduser);
        $this->assertEquals($teacher->id, $newitem->grader);
        $newgrade = $DB->get_record('videoassessment_grades', ['videoassessment' => $newva->id], '*', MUST_EXIST);
        $this->assertEquals($newitem->id, $newgrade->gradeitem);
        $this->assertEquals(70, $newgrade->grade);
        $this->assertEquals(1, $newgrade->isnotifystudent);
        $commentfiles = $fs->get_area_files($newcontext->id, 'mod_videoassessment', 'submissioncomment', $newgrade->id, '', false);
        $this->assertSame(['feedback.webm'], array_values(array_map(fn($f) => $f->get_filename(), $commentfiles)));

        $instance = $DB->get_record_sql(
            'SELECT gi.*
               FROM {grading_instances} gi
               JOIN {grading_definitions} gd ON gd.id = gi.definitionid
               JOIN {grading_areas} ga ON ga.id = gd.areaid
              WHERE ga.contextid = ? AND ga.component = ? AND ga.areaname = ?',
            [$newcontext->id, 'mod_videoassessment', 'beforeteacher'],
            MUST_EXIST
        );
        $this->assertEquals($newitem->id, $instance->itemid);
        $this->assertEquals($teacher->id, $instance->raterid);
        $this->assertSame(2, $DB->count_records('gradingform_rubric_fillings', ['instanceid' => $instance->id]));

        $aggregation = $DB->get_record('videoassessment_aggregation', ['videoassessment' => $newva->id], '*', MUST_EXIST);
        $this->assertEquals($learner->id, $aggregation->userid);
        $this->assertEquals(55, $aggregation->gradebeforeclass);
        $this->assertEquals(66, $aggregation->gradebeforetraining);
        $this->assertEquals(1, $aggregation->passtraining);
        $this->assertEquals(self::START + 99, $DB->get_field('videoassessment_report_views', 'timeviewed', [
            'videoassessment' => $newva->id, 'userid' => $learner->id,
        ]));
    }

    /**
     * Course with an AI feedback activity, one finished and one interrupted attempt.
     *
     * @return array{course: \stdClass, student: \stdClass, done: \stdClass}
     */
    private function setup_aifeedback(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $va = $this->getDataGenerator()->create_module('videoassessment', [
            'course' => $course->id,
            'aifeedback' => 1,
            'aifeedbackinstructions' => 'Introduce your satellite.',
            'aifeedbackkeywords' => "payload\norbit",
            'aitranscribelang' => 'en',
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $manager = new attempt_manager($DB->get_record('videoassessment', ['id' => $va->id]), \context_module::instance($cm->id));

        $path = make_request_directory() . '/recording.webm';
        file_put_contents($path, str_repeat("\x1a\x45\xdf\xa3", 64));
        $done = $manager->create_attempt($student->id, $path, 'recording.webm', 21);
        $done->status = attempt_manager::STATUS_DONE;
        $done->transcript = 'Hello everyone, our satellite has a payload.';
        $done->feedback = '{"summary": "Clear start."}';
        $done->timeprocessed = $done->timecreated + 20;
        $done->timeviewed = $done->timecreated + 30;
        $DB->update_record(attempt_manager::TABLE, $done);

        $interrupted = $manager->create_attempt($student->id, $path, 'recording.mp3');
        $DB->set_field(attempt_manager::TABLE, 'status', attempt_manager::STATUS_PROCESSING, ['id' => $interrupted->id]);

        return ['course' => $course, 'student' => $student, 'done' => $done];
    }

    /**
     * AI feedback attempts and their recordings are restored for the same learner; an
     * attempt caught mid-processing comes back queued so the learner's page resumes it.
     */
    public function test_aifeedback_attempts_restored_with_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course, 'student' => $student, 'done' => $done] = $this->setup_aifeedback();

        $newcourseid = $this->backup_and_restore($course, true);

        ['va' => $newva, 'context' => $newcontext] = $this->restored($newcourseid);
        $this->assertSame('1', (string)$newva->aifeedback);
        $this->assertSame("payload\norbit", $newva->aifeedbackkeywords);
        $attempts = array_values($DB->get_records(attempt_manager::TABLE, ['videoassessment' => $newva->id], 'attempt'));
        $this->assertCount(2, $attempts);

        [$first, $second] = $attempts;
        $this->assertEquals($student->id, $first->userid);
        $this->assertEquals(1, $first->attempt);
        $this->assertEquals(attempt_manager::STATUS_DONE, $first->status);
        $this->assertSame($done->transcript, $first->transcript);
        $this->assertSame($done->feedback, $first->feedback);
        $this->assertEquals($done->timeviewed, $first->timeviewed);
        $this->assertEquals(21, $first->duration);
        $this->assertEquals(attempt_manager::STATUS_QUEUED, $second->status);

        $manager = new attempt_manager($newva, $newcontext);
        $this->assertSame('attempt1.webm', $manager->get_audio_file($first)->get_filename());
        $this->assertSame(str_repeat("\x1a\x45\xdf\xa3", 64), $manager->get_audio_file($first)->get_content());
        $this->assertSame('attempt2.mp3', $manager->get_audio_file($second)->get_filename());
    }

    /**
     * Without user data only the AI feedback settings are copied, not the attempts.
     */
    public function test_aifeedback_attempts_left_out_without_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course] = $this->setup_aifeedback();

        $newcourseid = $this->backup_and_restore($course, false);

        ['va' => $newva, 'context' => $newcontext] = $this->restored($newcourseid);
        $this->assertSame('Introduce your satellite.', $newva->aifeedbackinstructions);
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE, ['videoassessment' => $newva->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($newcontext->id, 'mod_videoassessment', attempt_manager::FILEAREA));
    }
}
