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
 * The e-mail templates stored with each activity.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_templates {
    /** @var string[] Columns of the videoassessment table that hold a template; also their string identifiers. */
    const COLUMNS = [
        'teachernotificationtemplate',
        'peertnotificationtemplate',
        'remindernotificationtemplate',
        'videonotificationtemplate',
    ];

    /**
     * Drop "insert" from the placeholders in stored templates.
     *
     * Web application firewalls take "[[insert link" for SQL and refuse the
     * activity form, so a stored template with the old names could not be saved.
     *
     * @return void
     */
    public static function rename_insert_placeholders(): void {
        global $DB;
        foreach (self::COLUMNS as $column) {
            $DB->execute("UPDATE {videoassessment} SET {$column} = REPLACE({$column}, ?, ?)", ['[[insert ', '[[']);
        }
    }
}
