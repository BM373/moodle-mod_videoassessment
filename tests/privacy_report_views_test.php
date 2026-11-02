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
 * Privacy coverage of the report visit records.
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
use mod_videoassessment\privacy\provider;

/**
 * The report visit table added for 2026-07 request #1 holds personal
 * data, so the privacy provider must declare, find, export and delete it.
 *
 * @covers \mod_videoassessment\privacy\provider
 */
final class privacy_report_views_test extends \core_privacy\tests\provider_testcase {
    /** @var \context_module */
    private $context;
    /** @var int */
    private $vaid;
    /** @var int */
    private $student;
    /** @var int */
    private $other;

    /**
     * One activity in which two learners have opened their reports.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('videoassessment', ['course' => $course->id]);
        $this->context = \context_module::instance($activity->cmid);
        $this->vaid = (int) $activity->id;
        $this->student = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        $this->other = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;

        report_tracker::mark_viewed($this->vaid, $this->student, 1000);
        report_tracker::mark_viewed($this->vaid, $this->other, 2000);
    }

    /**
     * The table is declared in the metadata.
     */
    public function test_metadata_declares_table(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_videoassessment'));
        $tables = array_map(fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('videoassessment_report_views', $tables);
    }

    /**
     * A learner whose only data is a report visit is still found.
     */
    public function test_visit_alone_is_found(): void {
        $contextlist = provider::get_contexts_for_userid($this->student);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());

        $userlist = new userlist($this->context, 'mod_videoassessment');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student, $this->other], $userlist->get_userids());
    }

    /**
     * The visit is exported with the learner's data.
     */
    public function test_export(): void {
        $user = \core_user::get_user($this->student);
        provider::export_user_data(new approved_contextlist($user, 'mod_videoassessment', [$this->context->id]));

        $data = writer::with_context($this->context)->get_data([
            get_string('pluginname', 'mod_videoassessment'),
            'report_views',
        ]);
        $this->assertCount(1, $data->report_views);
        $this->assertEquals(1000, $data->report_views[0]->timeviewed);
    }

    /**
     * Deleting one learner's data leaves the other learner's visit.
     */
    public function test_delete_for_user_and_users(): void {
        $user = \core_user::get_user($this->student);
        provider::delete_data_for_user(new approved_contextlist($user, 'mod_videoassessment', [$this->context->id]));
        $this->assertSame(0, report_tracker::get_last_viewed($this->vaid, $this->student));
        $this->assertSame(2000, report_tracker::get_last_viewed($this->vaid, $this->other));

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_videoassessment', [$this->other]));
        $this->assertSame(0, report_tracker::get_last_viewed($this->vaid, $this->other));
    }

    /**
     * Deleting the whole context removes every visit.
     */
    public function test_delete_for_all_users(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertSame(0, $DB->count_records('videoassessment_report_views', ['videoassessment' => $this->vaid]));
    }
}
