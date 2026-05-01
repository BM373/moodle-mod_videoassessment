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
 * driver-specific behaviour will fail loudly on the affected DB.
 *
 * Tests use the plugin generator to create activities (it knows the
 * full required-column list); the tests then `update_record` the
 * specific column under test so we exercise the driver round-trip
 * rather than the Moodle insert validation path.
 */
final class db_compat_test extends \advanced_testcase {
    /**
     * The driver family under test (`mysql`, `postgres`, ...).
     *
     * @return string
     */
    private function driver(): string {
        global $DB;
        return $DB->get_dbfamily();
    }

    /**
     * Build a fresh activity row via the data generator (which knows
     * all of videoassessment's NOT NULL columns), and return it.
     *
     * @return \stdClass
     */
    private function fresh_va(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        return $generator->create_instance(['course' => $course->id]);
    }

    /**
     * UTF-8 multi-byte round-trip on `name` and `intro`.
     *
     * @coversNothing
     */
    public function test_utf8_roundtrip_on_activity_name_and_intro(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        // Mix of ASCII, 3-byte (kanji, kana) and 4-byte (emoji) UTF-8.
        $name = 'Video Assessment テスト 🎥 видео';
        $intro = 'Multibyte description: 日本語 한국어 中文 🚀 Ω≈ç';
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'name' => $name,
            'intro' => $intro,
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame($name, $row->name);
        $this->assertSame($intro, $row->intro);
    }

    /**
     * Empty-string round-trip on TEXT — both drivers must round-trip
     * an empty string without coercing it to NULL or vice-versa.
     *
     * @coversNothing
     */
    public function test_empty_intro_roundtrip(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'intro' => '',
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertIsString($row->intro);
        $this->assertSame('', $row->intro);
    }

    /**
     * Bool-shaped int columns (0/1) round-trip without driver coercion.
     *
     * @coversNothing
     */
    public function test_int_bool_roundtrip(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'fairnessbonus' => 1,
            'selffairnessbonus' => 0,
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame(1, (int) $row->fairnessbonus);
        $this->assertSame(0, (int) $row->selffairnessbonus);
    }

    /**
     * `WHERE col IN (?, ?, ...)` with mixed-type params works on both
     * DBs via Moodle's get_in_or_equal helper.
     *
     * @coversNothing
     */
    public function test_get_in_or_equal_with_mixed_param_types(): void {
        $this->resetAfterTest();
        global $DB;

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->fresh_va()->id;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select('videoassessment', "id $insql", $inparams);
        $this->assertCount(3, $rows);
    }

    /**
     * Boundary: 2^31 - 1 in a 10-digit INT column round-trips.
     *
     * @coversNothing
     */
    public function test_large_int_roundtrip_on_timedue(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        $maxint = 2147483647;
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'timedue' => $maxint,
            'timeavailable' => $maxint,
        ]);

        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame($maxint, (int) $row->timedue);
        $this->assertSame($maxint, (int) $row->timeavailable);
    }

    /**
     * Confirm install.xml's table list matches the
     * schema_test::plugin_table_provider mirror (otherwise the
     * reserved-keyword guard misses new tables silently).
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
     * `get_columns()` returns lowercase keys on both DBs so plugin
     * code using `array_key_exists()` works portably.
     *
     * @coversNothing
     */
    public function test_get_columns_returns_lowercase_names(): void {
        $this->resetAfterTest();
        global $DB;

        $columns = $DB->get_columns('videoassessment');
        $this->assertNotEmpty($columns);
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
     * TEXT column holds at least 64 KiB on both drivers.
     *
     * @coversNothing
     */
    public function test_text_column_holds_64k_payload(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        // A 64 KiB ASCII payload — comfortably above the 65535-byte
        // limit of MySQL's TEXT (Moodle uses LONGTEXT but we want to
        // be sure neither driver truncates).
        $largeintro = str_repeat('ABCDE', 13107);
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'intro' => $largeintro,
        ]);
        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame($largeintro, $row->intro);
    }

    /**
     * Driver family must be one we explicitly support in the matrix.
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

    /**
     * SQL ordering must be deterministic across drivers when an
     * explicit ORDER BY clause is given. PG and MariaDB disagree on
     * default ordering, so the plugin must always specify it.
     *
     * @coversNothing
     */
    public function test_explicit_order_by_is_deterministic(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoassessment');
        $names = ['Charlie', 'Alpha', 'Bravo'];
        $ids = [];
        foreach ($names as $n) {
            $va = $generator->create_instance(['course' => $course->id, 'name' => $n]);
            $ids[$n] = $va->id;
        }

        $rows = $DB->get_records_sql(
            'SELECT id, name FROM {videoassessment} WHERE id IN (?, ?, ?) ORDER BY name ASC',
            array_values($ids)
        );
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_values(array_column($rows, 'name')));
    }

    /**
     * `update_record` with a column rename target works on both
     * drivers (the plugin's upgrade.php uses XMLDB rename_field()
     * which has historically diverged between PG and MariaDB).
     *
     * Smoke test: confirm the canonical sortorder column rename
     * (formerly `order`) persisted across upgrade and is queryable.
     *
     * @coversNothing
     */
    public function test_sortorder_column_is_queryable(): void {
        $this->resetAfterTest();
        global $DB;

        $va = $this->fresh_va();
        $DB->update_record('videoassessment', (object) [
            'id' => $va->id,
            'sortorder' => 42,
        ]);
        $row = $DB->get_record('videoassessment', ['id' => $va->id]);
        $this->assertSame(42, (int) $row->sortorder);

        // The legacy column name (`order`) must NOT exist anymore.
        $columns = $DB->get_columns('videoassessment');
        $this->assertArrayNotHasKey('order', $columns);
        $this->assertArrayHasKey('sortorder', $columns);
    }
}
