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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once(__DIR__.'/../../../config.php');

require_once(__DIR__.'/lib.php');

try {
    $cmid = required_param('cmid', PARAM_INT);

    $bulkupload = new videoassessment_bulkupload($cmid);
    $bulkupload->require_capability();

    if (isset($_FILES['file'])) {
        $code = $bulkupload->start_async($_FILES['file']);
        send_headers('text/plain', false);
        echo $code;
        exit;
    }

    if ($code = optional_param('code', '', PARAM_TEXT)) {
        $progress = $bulkupload->get_progress($code);
        send_headers('text/plain', false);
        echo $progress;
        exit;
    }

} catch (Exception $ex) {
    header('HTTP/1.1 403 Forbidden');
    error_log($ex->__toString());
}
