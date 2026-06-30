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
 * Upload-option resolution tests (site-admin AND instance flags).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

/**
 * Tests for {@see \mod_videoassessment\upload_options} (Item #2).
 *
 * Item #2 replaced the single site-admin `preventvideouploads` toggle
 * with three checkboxes (allowexternallinks / allowvideouploads /
 * allowvideorecording). The audit found that the student-facing
 * upload form (classes/form/video_upload.php) only consulted the
 * per-activity instance flags, so an activity created BEFORE a site
 * admin disabled a feature still offered it — the site policy was not
 * enforced at render time.
 *
 * upload_options::for_instance() resolves the effective availability of
 * each upload method as the logical AND of the site-admin flag and the
 * activity-instance flag, with both defaulting to ON when unset
 * (matching the install default of 1 and the backward-compat
 * requirement).
 */
final class upload_options_test extends \advanced_testcase {
    /**
     * Build a fake activity row with the three instance flags.
     *
     * @param int|null $external
     * @param int|null $upload
     * @param int|null $record
     * @return \stdClass
     */
    private function row(?int $external, ?int $upload, ?int $record): \stdClass {
        $row = new \stdClass();
        if ($external !== null) {
            $row->allowyoutube = $external;
        }
        if ($upload !== null) {
            $row->allowvideoupload = $upload;
        }
        if ($record !== null) {
            $row->allowvideorecord = $record;
        }
        return $row;
    }

    /**
     * Set the three site-admin flags.
     *
     * @param int $external
     * @param int $upload
     * @param int $record
     * @return void
     */
    private function set_site(int $external, int $upload, int $record): void {
        set_config('allowexternallinks', $external, 'videoassessment');
        set_config('allowvideouploads', $upload, 'videoassessment');
        set_config('allowvideorecording', $record, 'videoassessment');
    }

    /**
     * When everything is enabled, all three methods are available.
     *
     * @covers \mod_videoassessment\upload_options::for_instance
     */
    public function test_all_enabled(): void {
        $this->resetAfterTest();
        $this->set_site(1, 1, 1);
        $opts = upload_options::for_instance($this->row(1, 1, 1));
        $this->assertTrue($opts['external']);
        $this->assertTrue($opts['upload']);
        $this->assertTrue($opts['record']);
    }

    /**
     * Site-level OFF must win over an instance-level ON for each of the
     * three methods independently (the reviewer-visible hole).
     *
     * @covers \mod_videoassessment\upload_options::for_instance
     */
    public function test_site_off_overrides_instance_on(): void {
        $this->resetAfterTest();

        $this->set_site(0, 1, 1);
        $opts = upload_options::for_instance($this->row(1, 1, 1));
        $this->assertFalse($opts['external'], 'site allowexternallinks=0 must hide YouTube even if instance allowyoutube=1');
        $this->assertTrue($opts['upload']);
        $this->assertTrue($opts['record']);

        $this->set_site(1, 0, 1);
        $opts = upload_options::for_instance($this->row(1, 1, 1));
        $this->assertTrue($opts['external']);
        $this->assertFalse($opts['upload'], 'site allowvideouploads=0 must hide file upload even if instance allowvideoupload=1');
        $this->assertTrue($opts['record']);

        $this->set_site(1, 1, 0);
        $opts = upload_options::for_instance($this->row(1, 1, 1));
        $this->assertTrue($opts['external']);
        $this->assertTrue($opts['upload']);
        $this->assertFalse($opts['record'], 'site allowvideorecording=0 must hide recording even if instance allowvideorecord=1');
    }

    /**
     * Instance-level OFF must win over a site-level ON.
     *
     * @covers \mod_videoassessment\upload_options::for_instance
     */
    public function test_instance_off_overrides_site_on(): void {
        $this->resetAfterTest();
        $this->set_site(1, 1, 1);
        $opts = upload_options::for_instance($this->row(0, 0, 0));
        $this->assertFalse($opts['external']);
        $this->assertFalse($opts['upload']);
        $this->assertFalse($opts['record']);
    }

    /**
     * Missing SITE settings (never saved) default to ON, so the
     * instance flag passes through unchanged (backward compatibility
     * with sites that have not run the migration).
     *
     * @covers \mod_videoassessment\upload_options::for_instance
     */
    public function test_missing_site_config_defaults_on(): void {
        $this->resetAfterTest();
        // Deliberately do NOT set any site config.
        unset_config('allowexternallinks', 'videoassessment');
        unset_config('allowvideouploads', 'videoassessment');
        unset_config('allowvideorecording', 'videoassessment');

        $opts = upload_options::for_instance($this->row(1, 0, 1));
        $this->assertTrue($opts['external'], 'unset site flag defaults ON');
        $this->assertFalse($opts['upload'], 'instance OFF still wins');
        $this->assertTrue($opts['record']);
    }

    /**
     * Missing INSTANCE fields (legacy rows predating the columns)
     * default to ON, so the site flag determines availability.
     *
     * @covers \mod_videoassessment\upload_options::for_instance
     */
    public function test_missing_instance_fields_default_on(): void {
        $this->resetAfterTest();
        $this->set_site(1, 1, 0);
        $opts = upload_options::for_instance($this->row(null, null, null));
        $this->assertTrue($opts['external'], 'missing instance flag defaults ON, site ON => available');
        $this->assertTrue($opts['upload']);
        $this->assertFalse($opts['record'], 'missing instance flag defaults ON, but site OFF => unavailable');
    }
}
