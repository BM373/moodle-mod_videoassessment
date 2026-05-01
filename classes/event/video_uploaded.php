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

namespace mod_videoassessment\event;

/**
 * Event triggered when a learner uploads (or links) a video to the activity.
 *
 * Item #10 of the 2026-04 fix programme: stream finer-grained activity
 * to Moodle's logstore so institutional analytics can track student
 * progress through the rubric loop.
 *
 * @package    mod_videoassessment
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array{videoassessmentid:int,filename?:string} $other
 *      Additional event data:
 *      - `videoassessmentid` - id of the videoassessment activity instance.
 *      - `filename` - optional name of the uploaded file or external URL.
 */
class video_uploaded extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'videoassessment_videos';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Localised event name shown in the log report.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_video_uploaded', 'mod_videoassessment');
    }

    /**
     * Localised description used by the log report.
     *
     * @return string
     */
    public function get_description() {
        $vaid = (int)($this->other['videoassessmentid'] ?? 0);
        return "The user with id '{$this->userid}' uploaded a video"
            . " to the videoassessment activity with id '{$vaid}'.";
    }
}
