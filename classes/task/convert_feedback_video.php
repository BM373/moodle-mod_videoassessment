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
 * Adhoc task converting WebM feedback videos to MP4 for iOS playback.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\task;

/**
 * Item #6 of the 2026-06 feedback round.
 *
 * Teacher feedback recorded with the rich editor's recorder is stored
 * as WebM (VP8/Opus), which desktop browsers play but iPhones cannot:
 * every iOS browser (Safari, Chrome, Firefox) uses WebKit, and WebKit
 * does not decode the editor's WebM output. The customers confirmed
 * the exact split — feedback videos play in "See Report" on desktop
 * and show a dead player on iPhone 15/16.
 *
 * This adhoc task transcodes WebM attachments in a grade's
 * `submissioncomment` filearea to MP4 (using the same admin-configured
 * FFmpeg command template the student-video pipeline uses), stores the
 * MP4 next to the original, and rewrites the @@PLUGINFILE@@ references
 * inside the saved comment HTML so every subsequent render serves the
 * iOS-playable file.
 */
class convert_feedback_video extends \core\task\adhoc_task {
    /**
     * Queue a conversion for one grade's feedback if (and only if) its
     * submissioncomment filearea contains a WebM file that has no MP4
     * counterpart yet.
     *
     * Called from the grade-save flow; cheap no-op when there is
     * nothing to convert.
     *
     * @param int $contextid Module context id owning the filearea.
     * @param int $gradeid videoassessment_grades.id (filearea itemid).
     * @return bool True when a task was queued.
     */
    public static function queue_if_needed(int $contextid, int $gradeid): bool {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            'mod_videoassessment',
            'submissioncomment',
            $gradeid,
            'itemid, filepath, filename',
            false
        );
        $needed = false;
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if (!preg_match('/\.webm$/i', $filename)) {
                continue;
            }
            $mp4name = preg_replace('/\.webm$/i', '.mp4', $filename);
            if (
                !$fs->file_exists(
                    $contextid,
                    'mod_videoassessment',
                    'submissioncomment',
                    $gradeid,
                    $file->get_filepath(),
                    $mp4name
                )
            ) {
                $needed = true;
                break;
            }
        }
        if (!$needed) {
            return false;
        }
        $task = new self();
        $task->set_custom_data((object) [
            'contextid' => $contextid,
            'gradeid' => $gradeid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /**
     * Swap every reference to a feedback file name inside the saved
     * comment HTML for the converted name.
     *
     * The editor stores @@PLUGINFILE@@ URLs with the filename
     * rawurlencoded, while the visible link text usually carries the
     * raw name, so both spellings are replaced.
     *
     * @param string $html Saved submissioncomment HTML.
     * @param string $oldname Original filename (e.g. recording.webm).
     * @param string $newname Converted filename (e.g. recording.mp4).
     * @return string Rewritten HTML.
     */
    public static function replace_filename_references(string $html, string $oldname, string $newname): string {
        $pairs = [];
        $oldencoded = rawurlencode($oldname);
        if ($oldencoded !== $oldname) {
            $pairs[$oldencoded] = rawurlencode($newname);
        }
        $pairs[$oldname] = $newname;
        return strtr($html, $pairs);
    }

    /**
     * Convert every WebM in the grade's submissioncomment filearea to
     * MP4 and rewrite the saved HTML to reference the MP4 files.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $contextid = (int) $data->contextid;
        $gradeid = (int) $data->gradeid;

        $grade = $DB->get_record('videoassessment_grades', ['id' => $gradeid]);
        if (!$grade) {
            return;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            'mod_videoassessment',
            'submissioncomment',
            $gradeid,
            'itemid, filepath, filename',
            false
        );

        $html = (string) $grade->submissioncomment;
        $changed = false;

        foreach ($files as $file) {
            $filename = $file->get_filename();
            if (!preg_match('/\.webm$/i', $filename)) {
                continue;
            }
            $mp4name = preg_replace('/\.webm$/i', '.mp4', $filename);

            $exists = $fs->file_exists(
                $contextid,
                'mod_videoassessment',
                'submissioncomment',
                $gradeid,
                $file->get_filepath(),
                $mp4name
            );
            if (!$exists) {
                $tmpdir = make_request_directory();
                $src = $tmpdir . '/source.webm';
                $dst = $tmpdir . '/converted.mp4';
                $file->copy_content_to($src);
                if (!$this->convert_file($src, $dst)) {
                    // Conversion is impossible (no FFmpeg configured,
                    // broken source, ...). Leave the WebM reference in
                    // place — desktop playback keeps working — rather
                    // than retrying this task forever.
                    debugging(
                        "Could not convert feedback video '{$filename}' "
                            . "(grade {$gradeid}) to MP4.",
                        DEBUG_DEVELOPER
                    );
                    continue;
                }
                $fs->create_file_from_pathname([
                    'contextid' => $contextid,
                    'component' => 'mod_videoassessment',
                    'filearea' => 'submissioncomment',
                    'itemid' => $gradeid,
                    'filepath' => $file->get_filepath(),
                    'filename' => $mp4name,
                ], $dst);
            }

            $newhtml = self::replace_filename_references($html, $filename, $mp4name);
            if ($newhtml !== $html) {
                $html = $newhtml;
                $changed = true;
            }
        }

        if ($changed) {
            $DB->set_field(
                'videoassessment_grades',
                'submissioncomment',
                $html,
                ['id' => $gradeid]
            );
        }
    }

    /**
     * Run the admin-configured FFmpeg command to transcode one file.
     *
     * Uses the same {INPUT}/{OUTPUT} command template (and the same
     * option fixer) as the student-video pipeline, so whatever command
     * path the site administrator configured on the settings page is
     * honoured here too. Protected so tests can substitute a fake
     * converter without spawning FFmpeg.
     *
     * @param string $src Absolute path of the WebM source.
     * @param string $dst Absolute path the MP4 must be written to.
     * @return bool True when the MP4 was produced.
     */
    protected function convert_file(string $src, string $dst): bool {
        global $CFG;

        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');

        $template = $CFG->videoassessment_ffmpegcommand ?? '';
        if (empty($template)) {
            return false;
        }
        try {
            $command = \videoassessment_bulkupload::fix_ffmpeg_options($template, '.mp4');
        } catch (\Exception $ex) {
            debugging('Invalid FFmpeg command template: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        $cmdline = strtr($command, [
            '{INPUT}' => escapeshellarg($src),
            '{OUTPUT}' => escapeshellarg($dst),
        ]) . ' > ' . escapeshellarg($dst . '.log') . ' 2>&1';

        $retval = \videoassessment_bulkupload::exec_nolimit($cmdline);

        return $retval === 0 && file_exists($dst);
    }
}
