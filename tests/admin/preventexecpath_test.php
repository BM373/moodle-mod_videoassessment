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
 * Smoke test that the locked-executable language strings exist.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\admin;

/**
 * Item #8 of the 2026-04 fix programme: honour `$CFG->preventexecpath`
 * on the FFmpeg / MP4Box admin settings (the spirit of upstream PR #58
 * by Adam Jenkins). The admin_setting_configtext_* subclasses defined
 * inline in settings.php now refuse changes when the global flag is
 * set; they look up the user-facing lock message via
 * `get_string('admin_settings_executable_locked', 'mod_videoassessment')`.
 *
 * This test confirms the localisation entry exists so the lock branch
 * does not crash with "missing string" on production sites.
 */
final class preventexecpath_test extends \basic_testcase {
    /**
     * The locked-executable string must be defined for English.
     *
     * @covers \mod_videoassessment
     */
    public function test_lock_message_exists(): void {
        $message = get_string('admin_settings_executable_locked', 'mod_videoassessment');
        $this->assertNotEmpty($message);
        $this->assertStringNotContainsString('[[', $message);
    }
}
