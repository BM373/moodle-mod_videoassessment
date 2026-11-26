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
 * Tests for the language packs of mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Keeps the English and Japanese packs complete and the code in step with them.
 *
 * @coversNothing
 */
final class lang_test extends \basic_testcase {
    /**
     * The strings of one language pack.
     *
     * @param string $lang Language code.
     * @return array<string, string> Identifier => text.
     */
    private function pack(string $lang): array {
        $string = [];
        require(__DIR__ . "/../lang/{$lang}/videoassessment.php");
        return $string;
    }

    /**
     * A string missing from a translation falls back to English in the middle of the page.
     *
     * @return void
     */
    public function test_packs_have_the_same_identifiers(): void {
        $en = $this->pack('en');
        $ja = $this->pack('ja');
        $this->assertSame([], array_keys(array_diff_key($en, $ja)), 'Missing from the Japanese pack.');
        $this->assertSame([], array_keys(array_diff_key($ja, $en)), 'Missing from the English pack.');
    }

    /**
     * A translation that drops or renames a placeholder shows the wrong text.
     *
     * @return void
     */
    public function test_placeholders_match_between_packs(): void {
        $placeholders = function (string $text): array {
            preg_match_all('/\{\$a(?:->\w+)?\}/', $text, $matches);
            $found = array_unique($matches[0]);
            sort($found);
            return $found;
        };
        $ja = $this->pack('ja');
        foreach ($this->pack('en') as $identifier => $text) {
            $this->assertSame($placeholders($text), $placeholders($ja[$identifier] ?? ''), $identifier);
        }
    }

    /**
     * A string the code asks for must exist, or the page shows [[identifier]] or "undefined".
     *
     * @return void
     */
    public function test_strings_used_in_the_code_exist(): void {
        $patterns = [
            '/\b(?:va|self)::str\(\s*\'([\w:]+)\'/',
            '/\bget_string\(\s*\'([\w:]+)\'\s*,\s*\'(?:mod_)?videoassessment\'/',
            '/M\.str\.videoassessment\.(\w+)/',
            '/M\.util\.get_string\(\s*\'(\w+)\'\s*,\s*\'mod_videoassessment\'/',
            '/\blangString\(\s*\'(\w+)\'/',
            '/\{\{#str\}\}\s*(\w+)\s*,\s*(?:mod_)?videoassessment/',
            '/key:\s*\'(\w+)\'\s*,\s*component:\s*\'mod_videoassessment\'/',
        ];
        $root = realpath(__DIR__ . '/..');
        $known = $this->pack('en');
        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            if (!preg_match('/\.(php|js|mustache)$/', $path) || preg_match('#^(amd/build|lang|tests|vendor|claudedocs)/#', $path)) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $source, $matches);
                foreach ($matches[1] as $identifier) {
                    if (!isset($known[$identifier])) {
                        $missing[] = "{$identifier} in {$path}";
                    }
                }
            }
        }
        $this->assertSame([], $missing);
    }

    /**
     * M.util.get_string() only finds strings the PHP page has loaded.
     *
     * @return void
     */
    public function test_amd_strings_are_loaded_by_their_pages(): void {
        $root = __DIR__ . '/..';

        $js = file_get_contents("{$root}/amd/src/peer_assignment.js");
        $php = file_get_contents("{$root}/mod_form.php");
        preg_match_all('/\blangString\(\s*\'(\w+)\'/', $js, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach (array_unique($matches[1]) as $identifier) {
            $this->assertStringContainsString("'{$identifier}'", $php, 'mod_form.php must load this string.');
        }

        $php = file_get_contents("{$root}/bulkupload/index.php");
        foreach (['error', 'queued', 'uploading', 'converting', 'complete'] as $status) {
            $this->assertStringContainsString("'bulkstatus_{$status}'", $php, 'bulkupload/index.php must load this string.');
        }

        $php = file_get_contents("{$root}/lib.php");
        foreach (["'createorselectrubric', 'mod_videoassessment'", "'gradingmanagement', 'core_grading'"] as $call) {
            $this->assertStringContainsString("string_for_js({$call})", $php);
        }

        $php = file_get_contents("{$root}/classes/va.php");
        $this->assertStringContainsString("'processingrecording'", $php, 'The recorder reads this label through M.str.');
    }
}
