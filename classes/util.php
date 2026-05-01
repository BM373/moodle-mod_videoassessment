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

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class for video assessment helper functions.
 *
 * This class provides static utility methods for common operations
 * and helper functions used throughout the video assessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /**
     * Get the full name label using core user fields.
     *
     * Retrieves and formats the full name label by combining all required
     * name fields from the core user fields configuration.
     *
     * @return string Formatted full name label
     */
    public static function get_fullname_label() {
        $allnamefields = \core_user\fields::for_name()->get_required_fields();
        $namefields = [];
        foreach ($allnamefields as $field) {
            $namefields[$field] = get_string($field);
        }
        return fullname((object) $namefields);
    }
}
