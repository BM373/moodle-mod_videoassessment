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
 * Administration setting definitions for the Video Assessment module. .
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: settings.php 1039 2014-07-28 07:17:37Z malu $
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('generalsettings', new lang_string('generalsettings', 'admin'), ''));

    require_once($CFG->dirroot.'/mod/videoassessment/lib.php');

    $formats = [
        '.mp4'  => '.mp4  - H.264',
        '.webm' => '.webm - WebM',
        '.ogv'  => '.ogv  - Theora',
        '.flv'  => '.flv  - Flash Video',
        ];
    $settings->add(
        new admin_setting_configselect('videoassessment_videoformat',
            get_string('videoformat', 'videoassessment'),
            get_string('videoformatdesc', 'videoassessment'),
            key($formats), $formats)
        );

    if (!class_exists('admin_setting_configtext_ffmpegcommand')) {
        class admin_setting_configtext_ffmpegcommand extends admin_setting_configtext {
            public function validate($data) {
                if (strpos($data, '{INPUT}') <= stripos($data, 'ffmpeg') ||
                    strpos($data, '{OUTPUT}') <= stripos($data, 'ffmpeg')) {
                    return get_string('validateerror', 'admin');
                }
                return true;
            }
        }
    }
    $settings->add(
        new admin_setting_configtext_ffmpegcommand('videoassessment_ffmpegcommand',
            get_string('ffmpegcommand', 'videoassessment'),
            get_string('ffmpegcommanddesc', 'videoassessment'),
            '/usr/local/bin/ffmpeg -i {INPUT} {OUTPUT}', PARAM_RAW, 60)
        );

    $settings->add(
        new admin_setting_configtext_ffmpegcommand('videoassessment_ffmpegthumbnailcommand',
            get_string('ffmpegthumbnailcommand', 'videoassessment'),
            get_string('ffmpegthumbnailcommanddesc', 'videoassessment'),
            '/usr/local/bin/ffmpeg -i {INPUT} -vframes 1 -s 137x91 -ss 1 {OUTPUT}', PARAM_RAW, 60)
        );

    $settings->add(
        new admin_setting_configtext('videoassessment_mp4boxcommand',
            get_string('mp4boxcommand', 'videoassessment'),
            get_string('mp4boxcommanddesc', 'videoassessment'),
            '/usr/local/bin/MP4Box', PARAM_RAW, 60)
        );

    $settings->add(new admin_setting_heading('backupdefaults', new lang_string('backupdefaults', 'videoassessment'), ''));
    $settings->add(
        new admin_setting_configcheckbox('videoassessment/backupusers',
            new lang_string('backupusers', 'videoassessment'),
            new lang_string('backupusersdesc', 'videoassessment'),
            0)
        );
}
