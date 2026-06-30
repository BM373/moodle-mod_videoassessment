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
 * Recording configuration helpers.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Single source of truth for the in-browser recording length limit.
 *
 * Item #3 of the 2026-04 fix programme. The original "Record New
 * Video" flow let users record indefinitely, which produced very
 * large MediaRecorder blobs that sometimes refused to upload. The
 * customer asked for a clear 2-minute cap on the recorder and a
 * matching "(max. length 2 minutes)" indicator on the radio label.
 *
 * Both the PHP form code and the AMD record module read from this
 * class so the limit cannot drift out of sync.
 */
final class recording {
    /** @var int Recording length cap in seconds. */
    private const MAX_LENGTH_SECONDS = 120;

    /**
     * Return the recording length cap in seconds.
     *
     * @return int Always 120 (= 2 minutes).
     */
    public static function max_length_seconds(): int {
        return self::MAX_LENGTH_SECONDS;
    }

    /**
     * Return the recording length cap in minutes, rounded up.
     *
     * @return int
     */
    public static function max_length_minutes(): int {
        return (int) ceil(self::MAX_LENGTH_SECONDS / 60);
    }

    /**
     * Decorate a base label with the recording-length suffix.
     *
     * @param string $base Untranslated base label, e.g. "Record New Video".
     * @return string e.g. "Record New Video (max. length 2 minutes)".
     */
    public static function label_with_limit(string $base): string {
        $minutes = self::max_length_minutes();
        return $base . ' (max. length ' . $minutes . ' minutes)';
    }
}
