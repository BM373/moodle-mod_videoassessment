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
 * Marks the AI's quoted phrases inside the transcript.
 *
 * The learner reads their own words with the good parts and the weak
 * parts highlighted and numbered, and the numbered comments below the
 * transcript explain each mark.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class highlighter {
    /**
     * Build the transcript HTML.
     *
     * A quote is searched case-insensitively and with flexible spacing;
     * if the model changed punctuation, a second search ignores
     * punctuation between words. A quote is placed where it does not
     * overlap an earlier mark if it can be; otherwise the shared words
     * carry both marks (e.g. a sentence quoted as a strength and as an
     * improvement). Only a quote that cannot be found is left unmarked;
     * its comment is still listed.
     *
     * @param string $transcript Plain-text transcript.
     * @param array $marks List of ['ref' => 'S1', 'type' => 'strength'|'improvement', 'quote' => '...'].
     * @return array{html: string, found: array<string, bool>} Escaped HTML and, per ref, whether it was marked.
     */
    public static function render(string $transcript, array $marks): array {
        $ranges = [];
        $found = [];
        foreach ($marks as $mark) {
            $found[$mark['ref']] = false;
            $range = self::locate($transcript, (string)$mark['quote'], $ranges);
            if ($range !== null) {
                $ranges[] = $range + ['ref' => $mark['ref'], 'type' => $mark['type']];
                $found[$mark['ref']] = true;
            }
        }

        // Cut the transcript wherever a mark starts or ends, so that each piece
        // is covered by a fixed set of marks and no <mark> has to be nested.
        $cuts = [0, strlen($transcript)];
        foreach ($ranges as $range) {
            $cuts[] = $range['start'];
            $cuts[] = $range['end'];
        }
        $cuts = array_values(array_unique($cuts));
        sort($cuts);

        $html = '';
        for ($i = 0; $i < count($cuts) - 1; $i++) {
            [$from, $to] = [$cuts[$i], $cuts[$i + 1]];
            $text = s(substr($transcript, $from, $to - $from));
            $covering = array_filter($ranges, fn($r) => $r['start'] <= $from && $r['end'] >= $to);
            if (!$covering) {
                $html .= $text;
                continue;
            }
            $html .= \html_writer::tag('mark', $text, [
                'class' => 'va-aifb-mark ' . self::classes(array_column($covering, 'type')),
                'data-ref' => implode(' ', array_column($covering, 'ref')),
            ]);
            foreach ($ranges as $range) {
                if ($range['end'] === $to) {
                    $class = 'va-aifb-ref ' . self::classes([$range['type']]);
                    $html .= \html_writer::tag('span', s($range['ref']), ['class' => $class]);
                }
            }
        }
        return ['html' => $html, 'found' => $found];
    }

    /**
     * CSS classes for the mark types covering a piece of the transcript.
     *
     * @param string[] $types 'strength' and/or 'improvement'.
     * @return string Strength first, so a shared piece always gets the same class list.
     */
    private static function classes(array $types): string {
        $classes = [];
        if (in_array('strength', $types, true)) {
            $classes[] = 'va-aifb-strength';
        }
        if (in_array('improvement', $types, true)) {
            $classes[] = 'va-aifb-improvement';
        }
        return implode(' ', $classes);
    }

    /**
     * Find a quote, preferring a place that the earlier marks have not taken.
     *
     * @param string $transcript Plain-text transcript.
     * @param string $quote The phrase to find.
     * @param array $taken Byte ranges ['start' => int, 'end' => int] already marked.
     * @return array{start: int, end: int}|null Byte offsets, or null when not found.
     */
    private static function locate(string $transcript, string $quote, array $taken): ?array {
        $words = preg_split('/\s+/u', trim($quote), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return null;
        }
        $exact = implode('\s+', array_map(fn($w) => preg_quote($w, '/'), $words));

        $loose = [];
        foreach ($words as $word) {
            $word = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $word);
            if ($word !== '') {
                $loose[] = preg_quote($word, '/');
            }
        }
        $patterns = [$exact];
        if ($loose) {
            $patterns[] = implode('[\s\p{P}\p{S}]+', $loose);
        }

        // The first match, kept in case every match overlaps an earlier mark.
        $overlapping = null;
        foreach ($patterns as $pattern) {
            if (!preg_match_all('/' . $pattern . '/iu', $transcript, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as [$text, $start]) {
                $end = $start + strlen($text);
                $overlaps = false;
                foreach ($taken as $range) {
                    if ($start < $range['end'] && $end > $range['start']) {
                        $overlaps = true;
                        break;
                    }
                }
                if (!$overlaps) {
                    return ['start' => $start, 'end' => $end];
                }
                $overlapping ??= ['start' => $start, 'end' => $end];
            }
        }
        return $overlapping;
    }
}
