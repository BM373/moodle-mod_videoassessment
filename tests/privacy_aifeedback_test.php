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
 * Privacy tests for AI speech feedback attempts.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_videoassessment\aifeedback\attempt_manager;
use mod_videoassessment\privacy\provider;

/**
 * Recordings, transcripts and AI comments are personal data: the provider
 * declares where they go, finds, exports and deletes them.
 *
 * @covers \mod_videoassessment\privacy\provider
 */
final class privacy_aifeedback_test extends \core_privacy\tests\provider_testcase {
    /** @var \context_module */
    private $context;
    /** @var int */
    private $vaid;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $other;

    /**
     * One activity in which two learners each sent a recording.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('videoassessment', ['course' => $course->id, 'aifeedback' => 1]);
        $this->context = \context_module::instance($activity->cmid);
        $this->vaid = (int)$activity->id;
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->other = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $manager = new attempt_manager($DB->get_record('videoassessment', ['id' => $this->vaid]), $this->context);
        foreach ([$this->student, $this->other] as $user) {
            $path = make_request_directory() . '/a.webm';
            file_put_contents($path, 'audio-of-' . $user->id);
            $attempt = $manager->create_attempt($user->id, $path, 'a.webm', 30);
            $DB->update_record(attempt_manager::TABLE, (object)[
                'id' => $attempt->id,
                'status' => attempt_manager::STATUS_DONE,
                'transcript' => 'Transcript of ' . $user->id,
                'feedback' => json_encode(['summary' => 'Good', 'strengths' => [], 'improvements' => []]),
            ]);
        }
    }

    /**
     * The table, both transcription services, AWS and the AI subsystem are declared.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_videoassessment'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('videoassessment_aifb_attempts', $names);
        $this->assertContains('aitranscription', $names);
        $this->assertContains('aws', $names);
        $this->assertContains('core_ai', $names);
    }

    /**
     * A learner whose only data is an AI feedback attempt is found.
     */
    public function test_attempt_alone_is_found(): void {
        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->student->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_videoassessment');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student->id, $this->other->id], $userlist->get_userids());
    }

    /**
     * The transcript, the feedback and the recording are exported.
     */
    public function test_export(): void {
        provider::export_user_data(new approved_contextlist($this->student, 'mod_videoassessment', [$this->context->id]));

        $subcontext = [
            get_string('pluginname', 'mod_videoassessment'),
            get_string('aifeedback', 'mod_videoassessment'),
            get_string('aifb_attempt', 'mod_videoassessment', 1),
        ];
        $writer = writer::with_context($this->context);
        $data = $writer->get_data($subcontext);
        $this->assertSame('Transcript of ' . $this->student->id, $data->transcript);
        $this->assertSame('Good', $data->feedback->summary);
        $this->assertNotEmpty($writer->get_files($subcontext));
    }

    /**
     * Deleting one learner keeps the other's attempt; deleting the context removes all.
     */
    public function test_delete(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_videoassessment', [$this->context->id]));
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE, ['userid' => $this->student->id]));
        $this->assertSame(1, $DB->count_records(attempt_manager::TABLE, ['userid' => $this->other->id]));

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_videoassessment', [$this->other->id]));
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE));
        $files = get_file_storage()->get_area_files($this->context->id, 'mod_videoassessment', attempt_manager::FILEAREA);
        $this->assertCount(0, $files);
    }

    /**
     * Deleting the whole context removes every attempt and recording.
     */
    public function test_delete_for_all_users(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE, ['videoassessment' => $this->vaid]));
        $files = get_file_storage()->get_area_files($this->context->id, 'mod_videoassessment', attempt_manager::FILEAREA);
        $this->assertCount(0, $files);
    }
}
