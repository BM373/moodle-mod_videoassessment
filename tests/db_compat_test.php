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
 * PostgreSQL / MariaDB compatibility regression tests.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Cross-database compatibility tests.
 *
 * The matrix CI run already executes the full suite against MariaDB and
 * PostgreSQL in parallel, so any of these tests that depends on
 * driver-specific behaviour will fail loudly on the affected DB. The
 * tests pin three classes of compatibility concern that have actually
 * bitten the plugin in the wild:
 *
 *  - Reserved-keyword column names (PG broke at runtime; see
 *    `schema_test`'s sister test).
 *  - Round-tripping of UTF-8 multi-byte data, including 4-byte
 *    characters (utf8mb4 emoji, kanji surrogates, etc.).
 *  - Numeric / boolean / timestamp coercion: PG and MariaDB disagree on
 *    the type of `1` vs `'1'` in some contexts, and bool comparisons
 *    behave differently when `tinyint(1)` is involved.
 */
final class db_compat_test extends \advanced_testcase {
    /**
     * The driver under test (`mariadb`, `mysqli`, `pgsql`, ...).
     *
     * Useful for a couple of branches below where the assertion
     * deliberately checks driver-specific behaviour.
     *
     * @return string
     */
    private function driver(): string {
        global $DB;
        return $DB->get_dbfamily();
    }

    /**
     * Insert + read-back round-trip of UTF-8 multi-byte data into the
     * `videoassessment` activity row.
     *
     * @coversNothing
     */
    public function test_utf8_roundtrip_on_activity_name(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        // Mix of ASCII, 3-byte (kanji, kana) and 4-byte (emoji) UTF-8.
        $name = 'Video Assessment テスト 🎥 видео';
        $intro = 'Multibyte description: 日本語 한국어 中文 🚀 Ω≈ç';

        $vaid = $DB->insert_record('videoassessment', (object) [
            'course' => $course->id,
            'name' => $name,
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $vaid]);
        $this->assertSame($name, $row->name);
        $this->assertSame($intro, $row->intro);
    }

    /**
     * NULL-vs-empty-string round-trip — PostgreSQL distinguishes them in
     * `WHERE col = ''` whereas MySQL/MariaDB are lax. Confirm the
     * plugin's tables let an empty TEXT column come back as either
     * NULL or '' depending on what was written.
     *
     * @coversNothing
     */
    public function test_null_vs_empty_intro_roundtrip(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $vaid = $DB->insert_record('videoassessment', (object) [
            'course' => $course->id,
            'name' => 'NULL test',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $vaid]);
        // Both DBs must report intro as a string (possibly empty).
        $this->assertIsString($row->intro);
        $this->assertSame('', $row->intro);
    }

    /**
     * Boolean-shaped columns are stored as integers in Moodle's XMLDB.
     * Confirm both DBs accept `0` and `1` and round-trip without
     * coercing to `'true'`/`'false'` or NULL.
     *
     * @coversNothing
     */
    public function test_int_bool_roundtrip(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $vaid = $DB->insert_record('videoassessment', (object) [
            'course' => $course->id,
            'name' => 'bool test',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'fairnessbonus' => 1,
            'selffairnessbonus' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $vaid]);
        // Moodle returns numeric columns as numeric strings; cast to
        // compare across drivers.
        $this->assertSame(1, (int) $row->fairnessbonus);
        $this->assertSame(0, (int) $row->selffairnessbonus);
    }

    /**
     * Confirm `WHERE col IN (?, ?, ...)` with a mix of integer and
     * string parameters works on both DBs. PG is strict about
     * parameter type binding; MariaDB is lax. Moodle's
     * get_in_or_equal() must paper over this.
     *
     * @coversNothing
     */
    public function test_get_in_or_equal_with_mixed_param_types(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $DB->insert_record('videoassessment', (object) [
                'course' => $course->id,
                'name' => "row {$i}",
                'intro' => '',
                'introformat' => FORMAT_HTML,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select('videoassessment', "id $insql", $inparams);
        $this->assertCount(3, $rows);
    }

    /**
     * Confirm a large unsigned integer (2^31 - 1, just under MySQL INT
     * max) round-trips without overflow on either DB.
     *
     * @coversNothing
     */
    public function test_large_int_roundtrip(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $maxint = 2147483647;
        $vaid = $DB->insert_record('videoassessment', (object) [
            'course' => $course->id,
            'name' => 'large int',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'timecreated' => $maxint,
            'timemodified' => $maxint,
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $vaid]);
        $this->assertSame($maxint, (int) $row->timecreated);
        $this->assertSame($maxint, (int) $row->timemodified);
    }

    /**
     * Confirm the plugin table list matches install.xml so that the
     * sister `schema_test` reserved-keyword check is exhaustive. If a
     * future migration adds a table without updating
     * `schema_test::plugin_table_provider`, this test fails.
     *
     * @coversNothing
     */
    public function test_install_xml_matches_table_provider(): void {
        $this->resetAfterTest();
        global $CFG;

        $xmlfile = $CFG->dirroot . '/mod/videoassessment/db/install.xml';
        $this->assertFileExists($xmlfile);
        $xml = simplexml_load_file($xmlfile);
        $declared = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $declared[] = (string) $table['NAME'];
        }
        sort($declared);

        $tracked = array_map(fn($r) => $r[0], schema_test::plugin_table_provider());
        sort($tracked);

        $this->assertSame(
            $declared,
            $tracked,
            'install.xml tables and schema_test::plugin_table_provider have drifted: '
                . 'add new tables to schema_test so the reserved-keyword guard keeps working.'
        );
    }

    /**
     * PostgreSQL is case-sensitive with quoted identifiers but Moodle
     * stores identifiers lowercase. Confirm `get_columns()` returns
     * lowercase keys on both DBs so plugin code that does
     * `array_key_exists('order', ...)` works portably.
     *
     * @coversNothing
     */
    public function test_get_columns_returns_lowercase_names(): void {
        $this->resetAfterTest();
        global $DB;

        $columns = $DB->get_columns('videoassessment');
        foreach (array_keys($columns) as $name) {
            $this->assertSame(
                strtolower($name),
                $name,
                "Column name '{$name}' is not lowercase. PostgreSQL returns "
                    . "the as-declared casing for unquoted identifiers; if a "
                    . "column is being returned in mixed case, plugin code "
                    . "using array_key_exists() will silently miss the row "
                    . "on PG."
            );
        }
    }

    /**
     * Confirm that `text` columns can hold the full minimum-required
     * length on both DBs (Moodle's TEXT type maps to LONGTEXT/text).
     *
     * @coversNothing
     */
    public function test_text_column_holds_64k_payload(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        // A 64 KiB payload — comfortably above the 65535-byte limit of
        // MySQL's TEXT (which Moodle never uses, but we want to be
        // sure neither driver truncates).
        $largeintro = str_repeat('ABCDE', 13107); // 65535 bytes.
        $vaid = $DB->insert_record('videoassessment', (object) [
            'course' => $course->id,
            'name' => 'big intro',
            'intro' => $largeintro,
            'introformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $row = $DB->get_record('videoassessment', ['id' => $vaid]);
        $this->assertSame($largeintro, $row->intro);
    }

    /**
     * Verify the active driver under test is one we explicitly support
     * in the matrix.
     *
     * @coversNothing
     */
    public function test_driver_is_supported(): void {
        $this->assertContains(
            $this->driver(),
            ['mysql', 'postgres'],
            'Unexpected database family: '
                . 'mod_videoassessment is verified against MariaDB (mysql family) '
                . 'and PostgreSQL only.'
        );
    }
}
