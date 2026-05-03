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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// This file intentionally defines two thin admin_setting_configtext_*
// subclasses inline (one for the FFmpeg commands, one for MP4Box). The
// PSR1.Classes.ClassDeclaration.MultipleClasses sniff would otherwise
// flag this as a violation; in Moodle settings.php, however, inline
// admin_setting_* classes are the documented convention, so the rule is
// suppressed for this file only.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('generalsettings', new lang_string('generalsettings', 'admin'), ''));

    require_once($CFG->dirroot . '/mod/videoassessment/lib.php');

    $formats = [
        '.mp4'  => '.mp4  - H.264',
        '.webm' => '.webm - WebM',
        '.ogv'  => '.ogv  - Theora',
        '.flv'  => '.flv  - Flash Video',
        ];
    $settings->add(
        new admin_setting_configselect(
            'videoassessment_videoformat',
            get_string('videoformat', 'videoassessment'),
            get_string('videoformatdesc', 'videoassessment'),
            key($formats),
            $formats
        )
    );

    if (!class_exists('admin_setting_configtext_ffmpegcommand')) {
        /**
         * Custom admin setting wrapping {@see \mod_videoassessment\admin\command_validator}.
         *
         * Item #9 of the 2026-04 fix programme. The previous implementation only
         * checked that ``{INPUT}`` / ``{OUTPUT}`` placeholders appeared after the
         * literal ``ffmpeg``, which let a site administrator inject arbitrary
         * shell metacharacters into the FFmpeg command line. This wrapper
         * delegates to the namespaced validator so the same allow-list is
         * applied uniformly and is also covered by PHPUnit data-driven tests.
         */
        class admin_setting_configtext_ffmpegcommand extends admin_setting_configtext {
            /**
             * Validate an FFmpeg command via the hardened command validator.
             *
             * Item #8 of the 2026-04 fix programme: honour the
             * `$CFG->preventexecpath` admin lockout. When that flag is
             * truthy, refuse any change of this setting from the Web UI
             * even if the new value would otherwise pass the validator.
             *
             * @param string $data The ffmpeg command to validate.
             * @return string|true True if validation passes; error string if it fails.
             */
            public function validate($data) {
                global $CFG;
                if (!empty($CFG->preventexecpath)) {
                    return get_string('admin_settings_executable_locked', 'mod_videoassessment');
                }
                return \mod_videoassessment\admin\command_validator::validate_ffmpeg((string)$data);
            }
        }
    }
    if (!class_exists('admin_setting_configtext_mp4boxcommand')) {
        /**
         * Custom admin setting for the MP4Box command line.
         *
         * Empty values are accepted (MP4Box is optional). When non-empty,
         * delegates to {@see \mod_videoassessment\admin\command_validator::validate_mp4box}.
         */
        class admin_setting_configtext_mp4boxcommand extends admin_setting_configtext {
            /**
             * Validate an MP4Box command via the hardened command validator.
             *
             * Item #8 of the 2026-04 fix programme: honour the
             * `$CFG->preventexecpath` admin lockout (see the FFmpeg
             * sibling class for the rationale).
             *
             * @param string $data The MP4Box command to validate.
             * @return string|true True if validation passes; error string if it fails.
             */
            public function validate($data) {
                global $CFG;
                if (!empty($CFG->preventexecpath)) {
                    return get_string('admin_settings_executable_locked', 'mod_videoassessment');
                }
                return \mod_videoassessment\admin\command_validator::validate_mp4box((string)$data);
            }
        }
    }
    $settings->add(
        new admin_setting_configtext_ffmpegcommand(
            'videoassessment_ffmpegcommand',
            get_string('ffmpegcommand', 'videoassessment'),
            get_string('ffmpegcommanddesc', 'videoassessment'),
            '/usr/local/bin/ffmpeg -i {INPUT} -c:v libx264 -profile:v high -preset fast -crf 23'
                . ' -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart {OUTPUT}',
            PARAM_RAW,
            120
        )
    );

    $settings->add(
        new admin_setting_configtext_ffmpegcommand(
            'videoassessment_ffmpegthumbnailcommand',
            get_string('ffmpegthumbnailcommand', 'videoassessment'),
            get_string('ffmpegthumbnailcommanddesc', 'videoassessment'),
            '/usr/local/bin/ffmpeg -i {INPUT} -vframes 1 -s 137x91 -ss 1 {OUTPUT}',
            PARAM_RAW,
            60
        )
    );

    $settings->add(
        new admin_setting_configtext_mp4boxcommand(
            'videoassessment_mp4boxcommand',
            get_string('mp4boxcommand', 'videoassessment'),
            get_string('mp4boxcommanddesc', 'videoassessment'),
            '/usr/local/bin/MP4Box',
            PARAM_RAW,
            60
        )
    );

    $settings->add(new admin_setting_heading('backupdefaults', new lang_string('backupdefaults', 'videoassessment'), ''));
    $settings->add(
        new admin_setting_configcheckbox(
            'videoassessment/backupusers',
            new lang_string('backupusers', 'videoassessment'),
            new lang_string('backupusersdesc', 'videoassessment'),
            0
        )
    );

    // File uploads section.
    // Item #2 of the 2026-04 fix programme: replace the single
    // `preventvideouploads` toggle with three independent allow-* flags
    // that mirror the activity-level "Video submissions" group, so that
    // site administrators can enable / disable each input channel
    // (external video links, file uploads, in-browser recording)
    // separately. The legacy `preventvideouploads` setting is kept for
    // one release as a fallback for sites that haven't run the upgrade
    // yet -- see db/upgrade.php for the migration that derives the new
    // flags from the old value.
    $settings->add(new admin_setting_heading(
        'fileuploadlinks',
        new lang_string('fileuploadlinks', 'videoassessment'),
        ''
    ));
    $settings->add(
        new admin_setting_configcheckbox(
            'videoassessment/allowexternallinks',
            new lang_string('allowexternallinks', 'videoassessment'),
            new lang_string('allowexternallinks_help', 'videoassessment'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'videoassessment/allowvideouploads',
            new lang_string('allowvideouploads', 'videoassessment'),
            new lang_string('allowvideouploads_help', 'videoassessment'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'videoassessment/allowvideorecording',
            new lang_string('allowvideorecording', 'videoassessment'),
            new lang_string('allowvideorecording_help', 'videoassessment'),
            1
        )
    );
}
