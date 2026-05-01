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
 * @copyright  2026 Shinonome Labo Co., Ltd.
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
}
