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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

$services = array(
    'videoassessment_pluginservice' => array(                      //the name of the web service
        'functions' => array ('mod_videoassessment_get_getallcomments', 'mod_videoassessment_get_coursesbycategory', 'mod_videoassessment_get_sectionsbycourse', 'mod_videoassessment_upload_mobile_video', 'mod_videoassessment_assignclass_sort_group'),
        'requiredcapability' => '',                //if set, the web service user need this capability to access
                                                    //any function of this service. For example: 'some/capability:specified'
        'restrictedusers' =>0,                      //if enabled, the Moodle administrator must link some user to this service
                                                    //into the administration
        'enabled'=>1,                               //if enabled, the service can be reachable on a default installation
        'shortname'=>'videoassessment_service' //the short name used to refer to this service from elsewhere including when fetching a token
    )
);

$functions = array(
    'mod_videoassessment_get_getallcomments' => array(
        'classname'		=> 'mod_videoassessment_external',
        'methodname'	=> 'get_getallcomments',
        'classpath'		=> 'mod/videoassessment/externallib.php',
        'description'	=> 'Returns a list of videoassessmen instances in a provided set of courses.',
        'type'			=> 'read',
        'ajax'          => true,
        'capabilities'	=> 'mod/videoassessment:viewdiscussion',
        'services' 		=> array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_videoassessment_get_coursesbycategory' => array(
        'classname'		=> 'mod_videoassessment_external',
        'methodname'	=> 'get_coursesbycategory',
        'classpath'		=> 'mod/videoassessment/externallib.php',
        'description'	=> 'Returns a list of videoassessmen instances in a provided set of courses.',
        'type'			=> 'read',
        'ajax'          => true,
        'capabilities'	=> 'mod/videoassessment:viewdiscussion',
        'services' 		=> array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_videoassessment_get_sectionsbycourse' => array(
        'classname'		=> 'mod_videoassessment_external',
        'methodname'	=> 'get_sectionsbycourse',
        'classpath'		=> 'mod/videoassessment/externallib.php',
        'description'	=> 'Returns a list of videoassessmen instances in a provided set of courses.',
        'type'			=> 'read',
        'ajax'          => true,
        'capabilities'	=> 'mod/videoassessment:viewdiscussion',
        'services' 		=> array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_videoassessment_upload_mobile_video' => array(
        'classname'		=> 'mod_videoassessment_external',
        'methodname'	=> 'upload_mobile_video',
        'classpath'		=> 'mod/videoassessment/externallib.php',
        'description'	=> 'Upload videoassessmen videos.',
        'type'			=> 'write',
        'ajax'			=> true,
        'capabilities'	=> 'mod/videoassessment:viewdiscussion',
        'services'		=> array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_videoassessment_assignclass_sort_group' => array(
        'classname'		=> 'mod_videoassessment_external',
        'methodname'	=> 'assignclass_sort_group',
        'classpath'		=> 'mod/videoassessment/externallib.php',
        'description'	=> 'Change the sort by using name,group or manually',
        'type'			=> 'read',
        'ajax'			=> true,
        'capabilities'	=> 'mod/videoassessment:viewdiscussion',
        'services'		=> array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);