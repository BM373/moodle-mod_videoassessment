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
 * Tests for the placeholders of the notification templates.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * "[[insert link..." looks like SQL to web application firewalls: OWASP CRS rule 942360
 * answers 403 to the activity form when the default templates are posted.
 *
 * @covers \mod_videoassessment\notification_templates
 */
final class notification_placeholders_test extends \advanced_testcase {
    /**
     * New activities start with templates the firewall lets through.
     */
    public function test_default_templates_avoid_the_word_insert(): void {
        foreach (notification_templates::COLUMNS as $column) {
            $this->assertStringNotContainsString('[[insert', get_string($column, 'videoassessment', null, true), $column);
        }
    }

    /**
     * Every placeholder of the default templates is one the mail code replaces.
     */
    public function test_new_names_are_replaced_in_mails(): void {
        $vaphp = file_get_contents(__DIR__ . '/../classes/va.php');
        $names = ['assignment name', 'current date', 'link to student page to view assessment', 'link to self-assessment page'];
        foreach ($names as $name) {
            $this->assertStringContainsString('"[[' . $name . ']]"', $vaphp);
            // Templates in backups made before the rename still use the old names.
            $this->assertStringContainsString('"[[insert ' . $name . ']]"', $vaphp);
        }
    }

    /**
     * Stored templates are renamed, or the form could not be saved behind such a firewall.
     */
    public function test_rename_in_stored_templates(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $va = $this->getDataGenerator()->create_module('videoassessment', ['course' => $course->id]);
        $old = "Dear [[student name]],\n[[insert link to self-assessment page]]\nPlease insert a card. [[insert assignment name]]";
        foreach (notification_templates::COLUMNS as $column) {
            $DB->set_field('videoassessment', $column, $old, ['id' => $va->id]);
        }
        $DB->set_field('videoassessment', 'videonotificationtemplate', null, ['id' => $va->id]);

        notification_templates::rename_insert_placeholders();

        $record = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame(
            "Dear [[student name]],\n[[link to self-assessment page]]\nPlease insert a card. [[assignment name]]",
            $record->remindernotificationtemplate
        );
        $this->assertNull($record->videonotificationtemplate);
    }

    /**
     * The page reloads while a video converts, as the notice promises; the client saw it stuck on "Converting".
     *
     * @coversNothing
     */
    public function test_page_refreshes_while_converting(): void {
        $table = file_get_contents(__DIR__ . '/../classes/grade_table.php');
        $this->assertStringContainsString("js_call_amd('mod_videoassessment/conversion_refresh', 'init')", $table);
        $this->assertFileExists(__DIR__ . '/../amd/src/conversion_refresh.js');
        $this->assertFileExists(__DIR__ . '/../amd/build/conversion_refresh.min.js');
    }
}
