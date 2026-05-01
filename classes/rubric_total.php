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
 * Live rubric-total calculator.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Pure-PHP rubric total calculator that mirrors the math used by the
 * AMD `live_grade_total` module.
 *
 * Item #13 of the 2026-04 fix programme. The customer asked that the
 * "Current grade in gradebook" indicator on the assess screen update
 * live as the teacher clicks rubric levels instead of waiting for the
 * Save Changes round trip. The actual UI update is done in JavaScript
 * (`amd/src/live_grade_total.js`); keeping the math also reachable in
 * PHP makes it unit-testable and rules out the JS implementation
 * drifting silently from the documented contract.
 */
final class rubric_total {
    /**
     * Compute the running rubric total.
     *
     * @param array<int|string, int[]> $criteria Map criterion-id =>
     *     list of available level scores for that criterion.
     * @param array<int|string, int> $selected Map criterion-id =>
     *     score that the grader has currently picked. Criteria absent
     *     from this map count as zero towards the running total but
     *     still contribute their max to the denominator.
     * @return array{total:int,max:int,percentage:int} Running totals
     *     plus the rounded percentage (0 when `max == 0`).
     */
    public static function calculate(array $criteria, array $selected): array {
        $total = 0;
        $max = 0;
        foreach ($criteria as $criterionid => $levels) {
            $maxforcrit = empty($levels) ? 0 : (int) max($levels);
            $max += $maxforcrit;
            if (array_key_exists($criterionid, $selected)) {
                $total += (int) $selected[$criterionid];
            }
        }
        $pct = $max > 0 ? (int) round(($total / $max) * 100) : 0;
        return [
            'total' => $total,
            'max' => $max,
            'percentage' => $pct,
        ];
    }

    /**
     * Format a running total in the canonical "X / Y (Z%)" form.
     *
     * @param array{total:int,max:int,percentage:int} $result
     * @return string Display text (or "-" when no rubric has been provided).
     */
    public static function format(array $result): string {
        if (($result['max'] ?? 0) <= 0) {
            return '-';
        }
        return $result['total'] . ' / ' . $result['max']
            . ' (' . $result['percentage'] . '%)';
    }
}
