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
 * This file contains the definition for the class videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Get the path to the ffmpeg executable.
 *
 * @return string|false The path to ffmpeg, or false if not found.
 */
function videoassessment_get_ffmpeg_path() {
    $cmd = get_config('videoassessment', 'ffmpegcommand');

    if (!empty($cmd)) {
        $parts = explode(' ', $cmd);
        return escapeshellcmd($parts[0]);
    }

    $sysiswindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($sysiswindows) {
        exec('where ffmpeg 2>NUL', $output, $returncode);
    } else {
        exec('command -v ffmpeg 2>/dev/null', $output, $returncode);
    }

    if ($returncode === 0 && !empty($output)) {
        return escapeshellcmd(trim($output[0]));
    }

    return false;
}

/**
 * Check if ffmpeg is available on the system.
 *
 * @return bool True if ffmpeg is available, false otherwise.
 */
function videoassessment_is_ffmpeg_available() {
    return videoassessment_get_ffmpeg_path() !== false;
}

/**
 * Get the ffmpeg version number.
 *
 * @return string|false The ffmpeg version number, or false if not available.
 */
function videoassessment_get_ffmpeg_version() {
    $path = videoassessment_get_ffmpeg_path();
    if (!$path) {
        return false;
    }

    $output = [];
    $returncode = 0;
    exec(escapeshellcmd($path) . ' -version 2>&1', $output, $returncode);

    if ($returncode === 0 && !empty($output)) {
        $versionline = $output[0];

        if (preg_match('/ffmpeg version ([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $versionline, $matches)) {
            return $matches[1];
        }
        // Return the first line if pattern does not match.
        return $versionline;
    }

    return false;
}

/**
 * Get the ffmpeg command.
 *
 * Returns the stored config command if available, otherwise falls back 
 * to the detected ffmpeg binary path.
 *
 * @return string|false Escaped ffmpeg command or binary path, or false if not available.
 */
function videoassessment_get_ffmpeg_command() {
    $cmd = get_config('videoassessment', 'ffmpegcommand');
    if (!empty($cmd)) {
        return escapeshellcmd($cmd);
    }

    // Fallback if not configured.
    $path = videoassessment_get_ffmpeg_path();
    return $path ? escapeshellcmd($path) : false;
}
