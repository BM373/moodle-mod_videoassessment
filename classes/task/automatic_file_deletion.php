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

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for automatic file deletion at course end date.
 *
 * This task automatically deletes all video files associated with video
 * assessment activities when the course end date is reached and the
 * automatic deletion setting is enabled.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkleman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class automatic_file_deletion extends \core\task\scheduled_task {
    /**
     * Get the human-readable name of the scheduled task.
     *
     * Returns the localized name of this automatic file deletion task
     * for display in the admin interface.
     *
     * @return string Localized task name
     */
    public function get_name() {
        return get_string('task_automatic_file_deletion', 'mod_videoassessment');
    }

    /**
     * Execute the automatic file deletion task.
     *
     * Processes all video assessments with automatic deletion enabled,
     * checks if the course end date has been reached, and deletes all
     * associated video files and database records.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();

        // Get all video assessments with automatic deletion enabled.
        $videoassessments = $DB->get_records('videoassessment', ['autodeletefiles' => 1]);

        foreach ($videoassessments as $va) {
            // Get the course to check its end date.
            $course = $DB->get_record('course', ['id' => $va->course], 'id, enddate');

            if (!$course) {
                mtrace("Course not found for video assessment ID: {$va->id}");
                continue;
            }

            // Skip if course has no end date.
            if (empty($course->enddate) || $course->enddate == 0) {
                continue;
            }

            // Check if course end date has been reached.
            if ($now >= $course->enddate) {
                mtrace("Processing automatic file deletion for video assessment ID: {$va->id} (Course ID: {$course->id})");

                // Get the course module.
                $module = $DB->get_record('modules', ['name' => 'videoassessment'], 'id');
                if (!$module) {
                    mtrace("Module 'videoassessment' not found");
                    continue;
                }

                $cm = $DB->get_record('course_modules', [
                    'course' => $va->course,
                    'instance' => $va->id,
                    'module' => $module->id,
                ]);

                if (!$cm) {
                    mtrace("Course module not found for video assessment ID: {$va->id}");
                    continue;
                }

                // Get the context.
                $context = \context_module::instance($cm->id);

                // Get all videos for this activity.
                $videos = $DB->get_records('videoassessment_videos', ['videoassessment' => $va->id]);

                $deletedcount = 0;
                $fs = get_file_storage();

                foreach ($videos as $video) {
                    // Delete video file.
                    $file = $fs->get_file(
                        $context->id,
                        'mod_videoassessment',
                        'video',
                        0,
                        $video->filepath,
                        $video->filename
                    );
                    if ($file) {
                        $file->delete();
                    }

                    // Delete thumbnail file if it exists.
                    if (!empty($video->thumbnailname)) {
                        $thumbfile = $fs->get_file(
                            $context->id,
                            'mod_videoassessment',
                            'video',
                            0,
                            $video->filepath,
                            $video->thumbnailname
                        );
                        if ($thumbfile) {
                            $thumbfile->delete();
                        }
                    }

                    // Delete video associations.
                    $DB->delete_records('videoassessment_video_assocs', ['videoid' => $video->id]);

                    // Delete video record.
                    $DB->delete_records('videoassessment_videos', ['id' => $video->id]);

                    $deletedcount++;
                }

                mtrace("Deleted {$deletedcount} video file(s) for video assessment ID: {$va->id}");

                // Disable automatic deletion to prevent re-processing.
                $va->autodeletefiles = 0;
                $DB->update_record('videoassessment', $va);
            }
        }
    }
}
