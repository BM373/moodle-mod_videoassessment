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

namespace mod_videoassessment\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a Video Assessment activity is viewed.
 *
 * Extends the core course module viewed event with Video Assessment
 * specific configuration and data.
 *
 * @package    mod_videoassessment
 * @category   event
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Initialize the event with Video Assessment specific data.
     *
     * Sets the object table, CRUD operation, and educational level
     * for the Video Assessment viewed event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'videoassessment'; // Your plugin's main table name
        $this->data['crud'] = 'r'; // r = read, c = create, u = update, d = delete
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }
}
