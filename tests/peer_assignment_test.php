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
 * Unit tests for the random peer-assignment algorithm in mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Item #5 of the 2026-04 fix programme.
 *
 * The original algorithm assigned each student `numpeers` peers but did
 * not track how often each student was *chosen as* a peer, so some
 * students were chosen multiple times more than others. The contract
 * fixed by this test:
 *
 * - Every student receives exactly `numpeers` peers.
 * - The number of times any given student is chosen as a peer is
 *   within ±1 of the expected mean (= `numpeers` for symmetric
 *   assignments where userCount > numpeers).
 * - A student is never their own peer.
 * - There are no duplicate peer assignments per user.
 */
final class peer_assignment_test extends \basic_testcase {
    /**
     * Build a fresh instance of {@see va} bypassing the constructor so
     * that the algorithm can be exercised in isolation.
     *
     * The algorithm under test, va::get_random_peers_for_users(), only
     * touches the array arguments it is given, so we can use a
     * reflection-based stub instead of having to spin up a real
     * activity instance.
     *
     * @return va
     */
    private function make_stub_va(): va {
        return (new \ReflectionClass(va::class))->newInstanceWithoutConstructor();
    }

    /**
     * Confirm the assignment produces equal frequency for each peer.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_assignments_are_evenly_distributed(): void {
        $va = $this->make_stub_va();
        $userids = range(1001, 1010); // 10 Users.
        $numpeers = 3;

        $mappings = $va->get_random_peers_for_users($userids, $numpeers);

        // Each user must receive exactly numpeers peers.
        foreach ($userids as $uid) {
            $this->assertCount(
                $numpeers,
                $mappings[$uid],
                "User {$uid} received the wrong number of peers"
            );
        }

        // Count how often each user is chosen as a peer.
        $chosen = array_fill_keys($userids, 0);
        foreach ($mappings as $peers) {
            foreach ($peers as $pid) {
                $chosen[$pid]++;
            }
        }

        $expected = $numpeers; // 10 Users * 3 peers / 10 = 3 per user.
        foreach ($chosen as $uid => $count) {
            $this->assertGreaterThanOrEqual(
                $expected - 1,
                $count,
                "User {$uid} was chosen as a peer only {$count} times"
            );
            $this->assertLessThanOrEqual(
                $expected + 1,
                $count,
                "User {$uid} was chosen as a peer {$count} times (expected ~{$expected})"
            );
        }
    }

    /**
     * Confirm a student is never their own peer.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_user_is_not_their_own_peer(): void {
        $va = $this->make_stub_va();
        $userids = range(1, 8);
        $numpeers = 4;
        $mappings = $va->get_random_peers_for_users($userids, $numpeers);
        foreach ($mappings as $uid => $peers) {
            $this->assertNotContains(
                $uid,
                $peers,
                "User {$uid} was assigned to themself"
            );
        }
    }

    /**
     * Confirm no user appears twice in another user's peer list.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_no_duplicate_peers_per_user(): void {
        $va = $this->make_stub_va();
        $userids = range(1, 12);
        $numpeers = 5;
        $mappings = $va->get_random_peers_for_users($userids, $numpeers);
        foreach ($mappings as $uid => $peers) {
            $this->assertSame(
                count($peers),
                count(array_unique($peers)),
                "User {$uid} has duplicate peer assignments"
            );
        }
    }

    /**
     * Boundary: empty user list returns an empty mapping.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_empty_user_list_returns_empty_mapping(): void {
        $va = $this->make_stub_va();
        $this->assertSame([], $va->get_random_peers_for_users([], 3));
    }

    /**
     * Boundary: numpeers == -1 means "everyone else", so each user is
     * paired with every other user in the list.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_numpeers_minus_one_assigns_everyone_else(): void {
        $va = $this->make_stub_va();
        $userids = [10, 20, 30, 40];
        $mappings = $va->get_random_peers_for_users($userids, -1);
        foreach ($userids as $uid) {
            $expected = array_values(array_diff($userids, [$uid]));
            sort($expected);
            $actual = $mappings[$uid];
            sort($actual);
            $this->assertSame(
                $expected,
                $actual,
                "User {$uid} expected to be paired with all others when numpeers = -1"
            );
        }
    }

    /**
     * Boundary: when there are fewer users than numpeers + 1 (i.e. not
     * enough people to fully populate the round-robin), the algorithm
     * falls back to "everyone else" so the activity still works.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_too_few_users_falls_back_to_all_others(): void {
        $va = $this->make_stub_va();
        // 3 Users but numpeers = 5 -> not enough.
        $userids = [1, 2, 3];
        $mappings = $va->get_random_peers_for_users($userids, 5);
        foreach ($userids as $uid) {
            $expected = array_values(array_diff($userids, [$uid]));
            sort($expected);
            $actual = $mappings[$uid];
            sort($actual);
            $this->assertSame($expected, $actual);
            $this->assertCount(2, $actual, "User {$uid} should have 2 peers (all others)");
        }
    }

    /**
     * Boundary: a single user → no peers (the user list contains only
     * themselves, so array_diff yields empty).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_single_user_has_no_peers(): void {
        $va = $this->make_stub_va();
        $mappings = $va->get_random_peers_for_users([42], 3);
        $this->assertSame([], $mappings[42]);
    }

    /**
     * Boundary: numpeers == 0 means each user gets an empty peer list.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_zero_peers_per_user(): void {
        $va = $this->make_stub_va();
        $mappings = $va->get_random_peers_for_users([1, 2, 3, 4], 0);
        foreach ($mappings as $uid => $peers) {
            $this->assertSame([], $peers, "User {$uid} should have no peers when numpeers = 0");
        }
    }

    /**
     * Boundary: numpeers exactly equal to count(users) - 1 -> every
     * user is paired with every other user (tight max).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_numpeers_equals_count_minus_one(): void {
        $va = $this->make_stub_va();
        $userids = [11, 22, 33, 44, 55];
        $mappings = $va->get_random_peers_for_users($userids, 4);
        foreach ($userids as $uid) {
            $expected = array_values(array_diff($userids, [$uid]));
            sort($expected);
            $actual = $mappings[$uid];
            sort($actual);
            $this->assertSame($expected, $actual);
        }
    }

    // Boundary value tests added in response to Brendon's spreadsheet
    // feedback. The legacy algorithm produced stu001(2), stu002(1),
    // stu003(6), stu004(1), stu005(0), stu006(2), stu007(2), stu008(1),
    // stu009(4), stu010(1): max-min = 6, stu005 was never chosen,
    // stu003 was chosen three times more than expected. These tests
    // pin every boundary of the new load-balancing algorithm so a
    // regression at any of them is caught locally before reaching the
    // demo site.

    /**
     * Run the algorithm and return the chosencount distribution.
     *
     * @param int[] $userids
     * @param int $numpeers
     * @param va $va
     * @return array<int,int> user id => number of times chosen as peer
     */
    private function chosen_count(array $userids, int $numpeers, va $va): array {
        $mappings = $va->get_random_peers_for_users($userids, $numpeers);
        $chosen = array_fill_keys($userids, 0);
        foreach ($mappings as $peers) {
            foreach ($peers as $pid) {
                $chosen[$pid]++;
            }
        }
        return $chosen;
    }

    /**
     * Brendon's exact spreadsheet reproduction: 10 students × 2 peers.
     * Run the algorithm 100 times and assert NO run produces the
     * legacy regression (max-min > 1, any user chosen 0 or ≥4 times).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_brendon_reproduction_10_users_2_peers(): void {
        $va = $this->make_stub_va();
        $userids = range(1001, 1010);
        $numpeers = 2;
        for ($i = 0; $i < 100; $i++) {
            $chosen = $this->chosen_count($userids, $numpeers, $va);
            $max = max($chosen);
            $min = min($chosen);
            $this->assertLessThanOrEqual(
                1,
                $max - $min,
                "Iteration {$i}: chosen-count spread (max - min = "
                    . ($max - $min) . ') exceeds the ±1 bound. '
                    . 'Distribution: ' . json_encode($chosen)
            );
            $this->assertGreaterThanOrEqual(
                1,
                $min,
                "Iteration {$i}: some user has 0 picks "
                    . '(legacy regression: stu005 was chosen 0 times). '
                    . 'Distribution: ' . json_encode($chosen)
            );
            $this->assertLessThanOrEqual(
                $numpeers + 1,
                $max,
                "Iteration {$i}: a user was chosen {$max} times "
                    . '(legacy regression: stu003 was chosen 6 times here). '
                    . 'Distribution: ' . json_encode($chosen)
            );
        }
    }

    /**
     * Conservation invariant: the total of all chosencounts across
     * every user must equal `count(users) × numpeers` for any normal
     * (non-fallback) configuration. A violation means the algorithm
     * either dropped a slot or double-assigned one.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_total_assignments_equals_count_times_numpeers(): void {
        $va = $this->make_stub_va();
        $cases = [
            [range(1, 10), 2],
            [range(1, 7), 3],
            [range(1, 20), 5],
            [range(1, 4), 2],
            [range(1, 100), 4],
        ];
        foreach ($cases as [$userids, $numpeers]) {
            $chosen = $this->chosen_count($userids, $numpeers, $va);
            $this->assertSame(
                count($userids) * $numpeers,
                array_sum($chosen),
                'count × numpeers conservation violated for '
                    . count($userids) . '×' . $numpeers . ': '
                    . json_encode($chosen)
            );
        }
    }

    /**
     * Two users × one peer is the smallest non-trivial case. Each user
     * must be paired with the other (deterministic), and each user
     * must be chosen exactly once.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_two_users_one_peer(): void {
        $va = $this->make_stub_va();
        $mappings = $va->get_random_peers_for_users([42, 43], 1);
        $this->assertSame([43], $mappings[42]);
        $this->assertSame([42], $mappings[43]);
    }

    /**
     * Three users × one peer (cyclic). Each user picks one peer; with
     * three users and three slots filled, the distribution must be
     * exactly 1-1-1 (max-min = 0) on every iteration.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_three_users_one_peer_is_perfectly_balanced(): void {
        $va = $this->make_stub_va();
        for ($i = 0; $i < 25; $i++) {
            $chosen = $this->chosen_count([1, 2, 3], 1, $va);
            $this->assertSame(
                [1 => 1, 2 => 1, 3 => 1],
                $chosen,
                "Iteration {$i}: 3×1 must be perfectly balanced 1-1-1. "
                    . 'Got: ' . json_encode($chosen)
            );
        }
    }

    /**
     * Three users × two peers (= count-1). Every user must be paired
     * with both other users (deterministic max case).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_three_users_two_peers_uses_all_others(): void {
        $va = $this->make_stub_va();
        $mappings = $va->get_random_peers_for_users([1, 2, 3], 2);
        foreach ($mappings as $uid => $peers) {
            $expected = array_values(array_diff([1, 2, 3], [$uid]));
            sort($peers);
            sort($expected);
            $this->assertSame(
                $expected,
                $peers,
                "User {$uid} should be paired with both other users."
            );
        }
    }

    /**
     * 5 users × 2 peers. The ±1 bound must hold across many iterations.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_five_users_two_peers_balance(): void {
        $va = $this->make_stub_va();
        for ($i = 0; $i < 50; $i++) {
            $chosen = $this->chosen_count(range(1, 5), 2, $va);
            $this->assertLessThanOrEqual(
                1,
                max($chosen) - min($chosen),
                "Iteration {$i}: 5×2 spread > 1. " . json_encode($chosen)
            );
        }
    }

    /**
     * 7 users × 3 peers. Total = 21 picks, average = 3 per user. The
     * ±1 bound must hold across many iterations.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_seven_users_three_peers_balance(): void {
        $va = $this->make_stub_va();
        for ($i = 0; $i < 50; $i++) {
            $chosen = $this->chosen_count(range(1, 7), 3, $va);
            $this->assertLessThanOrEqual(
                1,
                max($chosen) - min($chosen),
                "Iteration {$i}: 7×3 spread > 1. " . json_encode($chosen)
            );
        }
    }

    /**
     * Non-sequential user IDs (gaps from deleted users, custom IDs)
     * must produce a valid assignment. The algorithm must never invent
     * an ID outside the input set, and the ±1 bound must still hold.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_non_sequential_user_ids(): void {
        $va = $this->make_stub_va();
        $userids = [101, 205, 333, 444, 555, 7777, 8888, 9999];
        $numpeers = 3;
        for ($i = 0; $i < 30; $i++) {
            $mappings = $va->get_random_peers_for_users($userids, $numpeers);
            $chosen = array_fill_keys($userids, 0);
            foreach ($userids as $uid) {
                $this->assertCount($numpeers, $mappings[$uid]);
                foreach ($mappings[$uid] as $pid) {
                    $this->assertContains(
                        $pid,
                        $userids,
                        "User {$pid} appeared as a peer but is not in the input."
                    );
                    $chosen[$pid]++;
                }
            }
            $this->assertLessThanOrEqual(
                1,
                max($chosen) - min($chosen),
                "Iteration {$i}: spread > 1 with non-sequential IDs."
            );
        }
    }

    /**
     * numpeers exactly equal to count(users). Since a user cannot be
     * their own peer, this is impossible to satisfy literally — the
     * algorithm must fall back to count-1 peers (the "all others" set).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_numpeers_equals_count_falls_back_to_all_others(): void {
        $va = $this->make_stub_va();
        $userids = [1, 2, 3, 4];
        $mappings = $va->get_random_peers_for_users($userids, 4);
        foreach ($userids as $uid) {
            $expected = array_values(array_diff($userids, [$uid]));
            sort($expected);
            $actual = $mappings[$uid];
            sort($actual);
            $this->assertSame(
                $expected,
                $actual,
                "numpeers == count must fall back to count-1 peers."
            );
            $this->assertCount(3, $actual);
        }
    }

    /**
     * numpeers strictly greater than count: also falls back to all
     * others. Same behaviour as numpeers == count.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_numpeers_greater_than_count_falls_back(): void {
        $va = $this->make_stub_va();
        $userids = [1, 2, 3];
        $mappings = $va->get_random_peers_for_users($userids, 10);
        foreach ($userids as $uid) {
            $expected = array_values(array_diff($userids, [$uid]));
            sort($expected);
            $actual = $mappings[$uid];
            sort($actual);
            $this->assertSame($expected, $actual);
            $this->assertCount(2, $actual);
        }
    }

    /**
     * Negative numpeers other than -1 (e.g. -2): documents the current
     * defensive behaviour. The candidates loop `for ($slot = 0; $slot
     * < $numpeers; ...)` does not execute for a negative bound, so
     * every user receives an empty peer list (rather than crashing).
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_negative_numpeers_other_than_minus_one_yields_empty(): void {
        $va = $this->make_stub_va();
        $userids = [1, 2, 3, 4];
        $mappings = $va->get_random_peers_for_users($userids, -2);
        foreach ($mappings as $uid => $peers) {
            $this->assertSame(
                [],
                $peers,
                "Negative numpeers != -1 must defensively yield empty lists."
            );
        }
    }

    /**
     * Large symmetric scale (50 users × 5 peers): 250 total picks, 5
     * per user on average. The ±1 bound must still hold at scale, and
     * conservation must hold exactly.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_large_scale_50_users_5_peers(): void {
        $va = $this->make_stub_va();
        $userids = range(2001, 2050);
        $numpeers = 5;
        $chosen = $this->chosen_count($userids, $numpeers, $va);
        $this->assertLessThanOrEqual(
            1,
            max($chosen) - min($chosen),
            'At scale (50×5) the balance must still be ±1. Got max='
                . max($chosen) . ', min=' . min($chosen) . '.'
        );
        $this->assertSame(
            count($userids) * $numpeers,
            array_sum($chosen),
            'Total chosencount must equal count × numpeers at scale.'
        );
    }

    /**
     * Statistical fairness over many runs: each user's cumulative
     * chosencount across many invocations should be close to the
     * expected value (iterations × numpeers), within ±10%. This
     * catches a hypothetical bias where one user is systematically
     * over- or under-picked across the random seeds.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_statistical_fairness_no_systematic_bias(): void {
        $va = $this->make_stub_va();
        $userids = range(1, 10);
        $numpeers = 2;
        $iterations = 200;
        $cumulative = array_fill_keys($userids, 0);
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($this->chosen_count($userids, $numpeers, $va) as $uid => $count) {
                $cumulative[$uid] += $count;
            }
        }
        $expected = $iterations * $numpeers;
        $tolerance = (int)($expected * 0.10);
        foreach ($cumulative as $uid => $total) {
            $this->assertGreaterThan(
                $expected - $tolerance,
                $total,
                "User {$uid} systematically under-picked: {$total} vs ≈{$expected}."
            );
            $this->assertLessThan(
                $expected + $tolerance,
                $total,
                "User {$uid} systematically over-picked: {$total} vs ≈{$expected}."
            );
        }
    }

    /**
     * Exactness guarantee: the average chosen-count is always exactly
     * numpeers, so a max-min spread of <= 1 forces every user to be
     * chosen exactly numpeers times. 6 users x 2 peers is the
     * configuration where the swap post-pass alone proved
     * insufficient (the remaining swap can be topologically blocked,
     * caught as a 1-in-many flake by the full-suite run); the
     * regenerate-then-ring-fallback added afterwards makes the
     * guarantee unconditional. 200 iterations pin it.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_six_users_two_peers_always_exact(): void {
        $va = $this->make_stub_va();
        $userids = range(1, 6);
        for ($i = 0; $i < 200; $i++) {
            $chosen = $this->chosen_count($userids, 2, $va);
            foreach ($userids as $uid) {
                $this->assertSame(
                    2,
                    $chosen[$uid],
                    "Iteration {$i}: user {$uid} chosen {$chosen[$uid]} "
                        . 'times; 6x2 must always be exactly 2 each. '
                        . json_encode($chosen)
                );
            }
        }
    }

    /**
     * Even-distribution invariant for divisible totals: when
     * count × numpeers is divisible by count (which it always is on
     * the normal path, since avg = numpeers), the ideal spread is 0
     * (everyone chosen exactly numpeers times). The algorithm cannot
     * guarantee that strictly because a late-processed picker may be
     * blocked from picking already-chosen-low candidates, but most
     * runs of small even cases should hit it. Assert that the spread
     * is 0 in at least 50% of runs of a 6×3 case.
     *
     * @covers \mod_videoassessment\va::get_random_peers_for_users
     */
    public function test_six_users_three_peers_usually_achieves_zero_spread(): void {
        $va = $this->make_stub_va();
        $userids = range(1, 6);
        $numpeers = 3;
        $perfectruns = 0;
        $iterations = 50;
        for ($i = 0; $i < $iterations; $i++) {
            $chosen = $this->chosen_count($userids, $numpeers, $va);
            // Spread must always stay within the ±1 bound.
            $this->assertLessThanOrEqual(
                1,
                max($chosen) - min($chosen),
                "Iteration {$i}: 6×3 spread > 1. " . json_encode($chosen)
            );
            if (max($chosen) === min($chosen)) {
                $perfectruns++;
            }
        }
        // We expect a healthy fraction of perfect runs (in practice
        // every run, but allow plenty of slack to keep the test
        // non-flaky across PHP RNG implementations).
        $this->assertGreaterThanOrEqual(
            (int)($iterations * 0.5),
            $perfectruns,
            "Only {$perfectruns}/{$iterations} runs achieved a perfect "
                . '0-spread distribution; the algorithm should usually '
                . 'hit it for small divisible cases.'
        );
    }
}
