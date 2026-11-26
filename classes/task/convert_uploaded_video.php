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

namespace mod_videoassessment\task;

/**
 * Converts an uploaded video.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_uploaded_video extends \core\task\adhoc_task {
    /**
     * Run the task.
     *
     * A failure is not rethrown: convert() has recorded it, the user is told,
     * and trying again would fail the same way.
     *
     * @return void
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');

        $data = $this->get_custom_data();
        if (!get_coursemodule_from_id('videoassessment', $data->cmid ?? 0)) {
            return;
        }
        try {
            (new \videoassessment_bulkupload((int)$data->cmid))->convert($data->file);
        } catch (\Exception $ex) {
            mtrace('Video conversion failed: ' . $ex->getMessage());
        }
    }
}
