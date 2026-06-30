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
 * Unit tests for the live rubric total calculator.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\rubric_total}.
 *
 * Item #13 of the 2026-04 fix programme. The customer asked that the
 * "Current grade in gradebook" indicator on the assess screen update
 * live as the teacher clicks rubric levels, instead of waiting for
 * the Save Changes round-trip. This test fixes the contract of the
 * pure-PHP calculator that the AMD live_grade_total module mirrors;
 * keeping the math in PHP makes it unit-testable and rules out a
 * silent JS-only drift.
 */
final class rubric_total_test extends \basic_testcase {
    /**
     * Provider with rubric definitions and expected totals.
     *
     * Each input is `[array $criteria, array $selected]` where
     * `$criteria` is a map criterion-id => list of level scores and
     * `$selected` maps criterion-id => the score chosen by the grader.
     *
     * @return array<string, array{array<int, int[]>, array<int, int>, int, int, int}>
     */
    public static function totals_provider(): array {
        return [
            'all maximum levels selected' => [
                [10 => [0, 1, 2, 3, 4], 11 => [0, 2, 4]],
                [10 => 4, 11 => 4],
                8,
                8,
                100,
            ],
            'half-way selection' => [
                [10 => [0, 1, 2, 3, 4], 11 => [0, 2, 4]],
                [10 => 2, 11 => 2],
                4,
                8,
                50,
            ],
            'nothing selected yet' => [
                [10 => [0, 1, 2, 3, 4], 11 => [0, 2, 4]],
                [],
                0,
                8,
                0,
            ],
            'partial selection (only one criterion picked)' => [
                [10 => [0, 1, 2, 3, 4], 11 => [0, 2, 4]],
                [10 => 4],
                4,
                8,
                50,
            ],
            'empty rubric' => [[], [], 0, 0, 0],
            // Boundary: a single criterion with zero-only levels.
            'all-zero levels yield 0/0/0' => [
                [10 => [0, 0, 0]],
                [10 => 0],
                0,
                0,
                0,
            ],
            // Boundary: high level scores (typical rubric uses 0..4 but
            // some sites use 0..100). Confirm percentage is rounded.
            'large rubric scores -> rounded percentage' => [
                [10 => [0, 50, 100]],
                [10 => 33],
                33,
                100,
                33,
            ],
            // Boundary: percentage rounding. PHP's round() uses banker's
            // rounding by default but Math.round() in JS rounds half up;
            // the calculator already pins this so re-confirm.
            'rounding boundary 1/2 = 50%' => [
                [10 => [0, 1, 2]],
                [10 => 1],
                1,
                2,
                50,
            ],
            // Boundary: a criterion with only one level (degenerate but
            // legal in Moodle's rubric editor).
            'single-level criterion' => [
                [10 => [4]],
                [10 => 4],
                4,
                4,
                100,
            ],
            // Boundary: many criteria; confirm summation works.
            'ten criteria each scored max' => [
                array_fill_keys(range(1, 10), [0, 1, 2]),
                array_fill_keys(range(1, 10), 2),
                20,
                20,
                100,
            ],
            // Boundary: selected score lower than max but higher than 0
            // gives a non-trivial fractional percentage that rounds to
            // the nearest int.
            '7 / 12 rounds to 58%' => [
                [10 => [0, 4], 11 => [0, 4], 12 => [0, 4]],
                [10 => 4, 11 => 3, 12 => 0],
                7,
                12,
                58,
            ],
            // Boundary: selected score for a criterion not present in the
            // rubric definition is silently ignored (no exception).
            'selection for unknown criterion is ignored' => [
                [10 => [0, 1, 2]],
                [10 => 1, 99 => 2],
                1,
                2,
                50,
            ],
        ];
    }

    /**
     * Confirm the calculator returns the expected total/max/percent.
     *
     * @dataProvider totals_provider
     * @param array $criteria Criterion id => list of level scores.
     * @param array $selected Criterion id => chosen score.
     * @param int $expectedtotal Sum of chosen level scores.
     * @param int $expectedmax Sum of the maximum level score per criterion.
     * @param int $expectedpct Rounded percentage (or 0 when max == 0).
     * @covers \mod_videoassessment\rubric_total::calculate
     */
    public function test_calculate(
        array $criteria,
        array $selected,
        int $expectedtotal,
        int $expectedmax,
        int $expectedpct
    ): void {
        $result = rubric_total::calculate($criteria, $selected);
        $this->assertSame($expectedtotal, $result['total']);
        $this->assertSame($expectedmax, $result['max']);
        $this->assertSame($expectedpct, $result['percentage']);
    }

    /**
     * Format helper produces "X / Y (Z%)" or a placeholder when empty.
     *
     * @covers \mod_videoassessment\rubric_total::format
     */
    public function test_format(): void {
        $this->assertSame('-', rubric_total::format(['total' => 0, 'max' => 0, 'percentage' => 0]));
        $this->assertSame(
            '4 / 8 (50%)',
            rubric_total::format(['total' => 4, 'max' => 8, 'percentage' => 50])
        );
    }

    /**
     * Boundary tests for format(): negative or oddly-shaped result rows.
     *
     * @covers \mod_videoassessment\rubric_total::format
     */
    public function test_format_boundaries(): void {
        // Boundary: max == 0 always returns the placeholder regardless
        // of the total value (the live indicator should not blink "X / 0"
        // when the rubric is empty).
        $this->assertSame(
            '-',
            rubric_total::format(['total' => 5, 'max' => 0, 'percentage' => 0])
        );
        // Boundary: 100% / full mark.
        $this->assertSame(
            '20 / 20 (100%)',
            rubric_total::format(['total' => 20, 'max' => 20, 'percentage' => 100])
        );
        // Boundary: 0% with non-zero max (no level picked yet).
        $this->assertSame(
            '0 / 8 (0%)',
            rubric_total::format(['total' => 0, 'max' => 8, 'percentage' => 0])
        );
    }
}
