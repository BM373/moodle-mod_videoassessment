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

namespace mod_videoassessment;

/**
 * Resolves which video-submission methods are available for an activity.
 *
 * Item #2 of the 2026-04 fix programme replaced the single site-admin
 * `preventvideouploads` toggle with three independent checkboxes:
 *   - allowexternallinks  (paste a YouTube / Vimeo / Pod URL)
 *   - allowvideouploads   (upload a file)
 *   - allowvideorecording (record in the browser)
 *
 * Each method is available only when BOTH the site-admin flag AND the
 * per-activity instance flag are enabled. The student-facing upload
 * form must enforce this AND at render time: an activity created before
 * a site admin disabled a feature keeps its instance flag at 1, so
 * consulting the instance flag alone would still offer the disabled
 * method. Both layers default to ON when unset, matching the install
 * default of 1 and preserving behaviour on sites that have not yet run
 * the upgrade migration.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upload_options {
    /**
     * Read a site-admin allow flag, defaulting to ON when unset.
     *
     * @param string $name Config name within the videoassessment plugin.
     * @return bool True when the feature is allowed site-wide.
     */
    private static function site_flag(string $name): bool {
        $value = get_config('videoassessment', $name);
        // A `false` return means the setting has never been saved on this
        // site (e.g. the upgrade migration has not run); treat that as the
        // documented default of enabled.
        return $value === false ? true : (bool) $value;
    }

    /**
     * Read a per-activity instance flag, defaulting to ON when missing.
     *
     * @param \stdClass $varow Activity record.
     * @param string $field Instance column name.
     * @return bool
     */
    private static function instance_flag(\stdClass $varow, string $field): bool {
        return !isset($varow->$field) || (bool) $varow->$field;
    }

    /**
     * Resolve the effective availability of each upload method for an
     * activity as (site flag AND instance flag).
     *
     * @param \stdClass $varow The videoassessment activity record.
     * @return array{external: bool, upload: bool, record: bool}
     */
    public static function for_instance(\stdClass $varow): array {
        return [
            'external' => self::site_flag('allowexternallinks')
                && self::instance_flag($varow, 'allowyoutube'),
            'upload' => self::site_flag('allowvideouploads')
                && self::instance_flag($varow, 'allowvideoupload'),
            'record' => self::site_flag('allowvideorecording')
                && self::instance_flag($varow, 'allowvideorecord'),
        ];
    }
}
