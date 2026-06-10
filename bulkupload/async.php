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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This endpoint is invoked server-to-server by
// videoassessment_bulkupload::async_http_get(), a raw cookieless
// fire-and-forget GET. There is no user session to authenticate:
// requiring login here (as an earlier sniff-appeasing revision did)
// redirected the cookieless request to the login page and the FFmpeg
// conversion silently never ran. Authentication is the md5 token
// derived from the site identifier, checked below.
define('NO_MOODLE_COOKIES', true);

// phpcs:disable moodle.Files.RequireLogin -- Token-authenticated server-to-server callback; no user session exists by design.
require_once(__DIR__ . '/../../../config.php');

require_once(__DIR__ . '/lib.php');

try {
    $cmid = required_param('cmid', PARAM_INT);
    $file = required_param('file', PARAM_FILE);
    $token = required_param('token', PARAM_ALPHANUM);

    // To avoid any unauthorized external request.
    // Only accept internal ajax request with valid token.
    if ($token !== md5($file . get_site_identifier())) {
        throw new moodle_exception('invalidtoken', 'videoassessment');
    }

    $bulkupload = new videoassessment_bulkupload($cmid);
    $bulkupload->convert($file);
} catch (Exception $ex) {
    header('HTTP/1.1 403 Forbidden');
    debugging($ex->__toString());
}
