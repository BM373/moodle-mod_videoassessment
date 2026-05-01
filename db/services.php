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
 * Web service for mod video assessment.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The keys mirror the documented Moodle web service descriptor schema.
// See https://docs.moodle.org/dev/Web_service_API_functions for details
// on every field.
$services = [
    'videoassessment_pluginservice' => [
        'functions' => [
            'mod_videoassessment_get_getallcomments',
            'mod_videoassessment_get_coursesbycategory',
            'mod_videoassessment_get_sectionsbycourse',
            'mod_videoassessment_assignclass_sort_group',
        ],
        'requiredcapability' => '',
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'videoassessment_service',
    ],
];

$functions = [
    'mod_videoassessment_get_getallcomments' => [
        'classname'     => 'mod_videoassessment_external',
        'methodname'    => 'get_getallcomments',
        'classpath'     => 'mod/videoassessment/externallib.php',
        'description'   => 'Return all comments for a given user and grade item.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/videoassessment:viewcomments',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_videoassessment_get_coursesbycategory' => [
        'classname'     => 'mod_videoassessment_external',
        'methodname'    => 'get_coursesbycategory',
        'classpath'     => 'mod/videoassessment/externallib.php',
        'description'   => 'Returns courses under a specified category.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/videoassessment:fetchcourses',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_videoassessment_get_sectionsbycourse' => [
        'classname'     => 'mod_videoassessment_external',
        'methodname'    => 'get_sectionsbycourse',
        'classpath'     => 'mod/videoassessment/externallib.php',
        'description'   => 'Returns sections in a given course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/videoassessment:fetchsections',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_videoassessment_assignclass_sort_group' => [
        'classname'     => 'mod_videoassessment_external',
        'methodname'    => 'assignclass_sort_group',
        'classpath'     => 'mod/videoassessment/externallib.php',
        'description'   => 'Sorts students by name, group, or manual order.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/videoassessment:managesorting',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
