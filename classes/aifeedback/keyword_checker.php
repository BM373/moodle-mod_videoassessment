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

namespace mod_videoassessment\aifeedback;

/**
 * Checks which of the teacher's target words a transcript contains.
 *
 * This is done in code, not by the AI, so the checklist the learner sees
 * is exact and the same every time (the autograde idea: the teacher lists
 * the transitions or vocabulary the speech must use).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class keyword_checker {
    /** @var int Most target words read from the activity setting. */
    const MAX_KEYWORDS = 50;

    /**
     * Split the teacher's list into words or phrases.
     *
     * One per line; commas (including the Japanese 、) also separate, so a
     * teacher can paste "firstly, secondly, finally".
     *
     * @param string $raw The setting as typed.
     * @return string[] Unique entries, in the order typed.
     */
    public static function parse_list(string $raw): array {
        $out = [];
        foreach (preg_split('/[\r\n,、，]+/u', $raw) as $entry) {
            $entry = trim(preg_replace('/\s+/u', ' ', $entry));
            if ($entry === '') {
                continue;
            }
            $key = \core_text::strtolower($entry);
            if (!isset($out[$key])) {
                $out[$key] = $entry;
            }
            if (count($out) >= self::MAX_KEYWORDS) {
                break;
            }
        }
        return array_values($out);
    }

    /**
     * Mark each target word as used or not.
     *
     * Case is ignored. A word written in letters must stand on its own
     * ("in" does not match "inside"); text in scripts without spaces, such
     * as Japanese, matches anywhere.
     *
     * @param string $transcript What the learner said.
     * @param string[] $keywords Output of {@see self::parse_list()}.
     * @return array<int, array{keyword: string, used: bool}>
     */
    public static function check(string $transcript, array $keywords): array {
        $result = [];
        foreach ($keywords as $keyword) {
            $result[] = ['keyword' => $keyword, 'used' => self::contains($transcript, $keyword)];
        }
        return $result;
    }

    /**
     * Whether the transcript contains the word or phrase.
     *
     * @param string $transcript What the learner said.
     * @param string $keyword One target word or phrase.
     * @return bool
     */
    public static function contains(string $transcript, string $keyword): bool {
        $parts = preg_split('/\s+/u', trim($keyword));
        $pattern = implode('\s+', array_map(fn($p) => preg_quote($p, '/'), $parts));
        if (preg_match('/^[\p{Latin}\p{N}\s\'’-]+$/u', $keyword)) {
            $pattern = '(?<![\p{L}\p{N}])' . $pattern . '(?![\p{L}\p{N}])';
        }
        return (bool)preg_match('/' . $pattern . '/iu', $transcript);
    }
}
