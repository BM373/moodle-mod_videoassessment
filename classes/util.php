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
 * The videoassess namespace definition.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class util {
    /**
     * Returns the full name label.
     *
     * @return string
     */
    public static function get_fullname_label() {
        $allnamefields = \core_user\fields::for_name()->get_required_fields();
        $namefields =[];
        foreach ($allnamefields as $field) {
            $namefields[$field] = get_string($field);
        }
        return fullname((object) $namefields);
    }
}