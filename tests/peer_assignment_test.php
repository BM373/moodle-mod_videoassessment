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
}
