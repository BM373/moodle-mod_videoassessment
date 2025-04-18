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
 * Defines the version of Video Assessment.
 *
 * This code fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025040100;
$plugin->requires  = 2011112900;
$plugin->component = 'mod_videoassessment';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0 (Build: 2025040100)'; // User-friendly version number.
$plugin->supported = [400, 403];
