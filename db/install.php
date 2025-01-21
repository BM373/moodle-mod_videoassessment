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
 * This file replaces the legacy STATEMENTS section in:
 *
 * db/install.xml,
 * lib.php/modulename_install()
 * post installation hook and partially defaults.php
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_videoassessment_install() {
    global $OUTPUT;
    $cmdline = '/usr/local/bin/ffmpeg -version';
    ignore_user_abort(true);
    set_time_limit(0);
    $output = array();
    $retval = 0;
    putenv('PATH=');
    putenv('LD_LIBRARY_PATH=');
    putenv('DYLD_LIBRARY_PATH=');
    exec($cmdline, $output, $retval);
    if($retval == 1 || empty($output)){
        echo $OUTPUT->notification("The default installation path of ffmpeg does not exist!", 'notifyproblem');
    }else{
        $arr = explode("\n",$output[0]);
        $ffmpegversioninfo = $arr[0];
        echo $OUTPUT->notification($ffmpegversioninfo, 'notifysuccess');
    }
}
