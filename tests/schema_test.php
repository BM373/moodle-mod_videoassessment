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
 * Database schema regression tests.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Guards against database schema regressions that have bitten production sites.
 *
 * Item #11 in the 2026-04 fix programme: SGU's PostgreSQL site emitted
 *   ERROR: syntax error at or near "order"
 * during the {@see \mod_videoassessment\task\automatic_file_deletion} cron
 * because the videoassessment activity table declared an `order` column,
 * and `order` is a PostgreSQL reserved keyword. Subsequent
 * `$DB->update_record('videoassessment', ...)` therefore generated
 * unparsable SQL on PostgreSQL.
 *
 * This regression test enumerates every plugin-owned table and asserts that
 * no column name collides with PostgreSQL's reserved keyword list, so the
 * same class of bug cannot resurface silently.
 *
 */
final class schema_test extends \advanced_testcase {
    /**
     * Plugin-owned tables enumerated in db/install.xml.
     *
     * Hand-maintained mirror of the install.xml table list. If a new table
     * is added to the plugin, append its name here so the regression test
     * keeps catching reserved-keyword collisions.
     *
     * @return string[]
     */
    public static function plugin_table_provider(): array {
        return [
            ['videoassessment'],
            ['videoassessment_aggregation'],
            ['videoassessment_grade_items'],
            ['videoassessment_grades'],
            ['videoassessment_videos'],
            ['videoassessment_video_assocs'],
            ['videoassessment_peers'],
            ['videoassessment_sort_items'],
            ['videoassessment_sort_order'],
        ];
    }

    /**
     * Names that PostgreSQL treats as reserved (subset that has actually
     * been observed to clash with plugin column names in the wild).
     *
     * Source: PostgreSQL 16 SQL Key Words appendix, "reserved" / "reserved
     * (can be function or type)" entries. We keep the list narrow on
     * purpose: only the ones that bite UPDATE/INSERT statements without
     * quoting need to be guarded against here.
     *
     * @return string[]
     */
    private static function reserved_keywords(): array {
        return [
            'all', 'analyse', 'analyze', 'and', 'any', 'array', 'as', 'asc',
            'asymmetric', 'authorization', 'binary', 'both', 'case', 'cast',
            'check', 'collate', 'collation', 'column', 'concurrently',
            'constraint', 'create', 'cross', 'current_catalog', 'current_date',
            'current_role', 'current_schema', 'current_time', 'current_timestamp',
            'current_user', 'default', 'deferrable', 'desc', 'distinct', 'do',
            'else', 'end', 'except', 'false', 'fetch', 'for', 'foreign',
            'freeze', 'from', 'full', 'grant', 'group', 'having', 'ilike',
            'in', 'initially', 'inner', 'intersect', 'into', 'is', 'isnull',
            'join', 'lateral', 'leading', 'left', 'like', 'limit', 'localtime',
            'localtimestamp', 'natural', 'not', 'notnull', 'null', 'offset',
            'on', 'only', 'or', 'order', 'outer', 'overlaps', 'placing',
            'primary', 'references', 'returning', 'right', 'select',
            'session_user', 'similar', 'some', 'symmetric', 'system_user',
            'table', 'tablesample', 'then', 'to', 'trailing', 'true', 'union',
            'unique', 'user', 'using', 'variadic', 'verbose', 'when', 'where',
            'window', 'with',
        ];
    }

    /**
     * Test that no plugin-owned table declares a column whose name collides
     * with a PostgreSQL reserved keyword.
     *
     * @dataProvider plugin_table_provider
     * @param string $tablename Plugin table to inspect.
     * @coversNothing
     */
    public function test_no_postgresql_reserved_column_names(string $tablename): void {
        $this->resetAfterTest();
        global $DB;

        $columns = array_keys($DB->get_columns($tablename));
        $offending = array_values(array_intersect(
            array_map('strtolower', $columns),
            self::reserved_keywords()
        ));

        $this->assertSame(
            [],
            $offending,
            "Table '{$tablename}' declares column(s) whose name is a PostgreSQL reserved keyword: "
                . implode(', ', $offending)
                . ". Rename the column (e.g. 'order' -> 'sortorder') and add a "
                . "rename_field() migration in db/upgrade.php."
        );
    }
}
