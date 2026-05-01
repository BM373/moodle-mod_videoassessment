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

defined('MOODLE_INTERNAL') || die();

/**
 * Video object representing a video submission in video assessment.
 *
 * This class encapsulates video data, file handling, thumbnail management,
 * and rendering functionality for video submissions and YouTube videos.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video implements \renderable {
    /**
     * Video data record from database.
     *
     * Contains all video metadata including filename, path, and type information.
     *
     * @var \stdClass
     */
    public $data;

    /**
     * Stored file object for the video file.
     *
     * Represents the actual video file stored in Moodle's file system.
     *
     * @var \stored_file
     */
    public $file;

    /**
     * Stored file object for the video thumbnail.
     *
     * Represents the thumbnail image file associated with the video.
     *
     * @var \stored_file
     */
    public $thumbnail;

    /**
     * Module context for file operations.
     *
     * Provides the context needed for file storage and retrieval operations.
     *
     * @var \context_module
     */
    public $context;

    /**
     * Ready status flag for the video object.
     *
     * Indicates whether the video is properly initialized and ready for use.
     *
     * @var bool
     */
    public $ready = false;

    /**
     * Initialize video object with context and data.
     *
     * Sets up the video object by loading associated files and thumbnails
     * from the file storage system and determining readiness status.
     *
     * @param \context_module $context Module context for file operations
     * @param \stdClass $data Video data record from database
     * @return void
     */
    public function __construct(\context_module $context, \stdClass $data) {
        $this->context = $context;
        $this->data = $data;

        $fs = get_file_storage();

        if ($file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $data->filepath,
                $data->filename)) {
            $this->file = $file;
            $this->ready = true;
        }
        if ($data->tmpname == 'Youtube') {
            $this->ready = true;
        }
        if ($data->thumbnailname
                && $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $data->filepath,
                        $data->thumbnailname)) {
            $this->thumbnail = $file;
        }
    }

    /**
     * Convert video object to string representation.
     *
     * Returns the file object as a string for display purposes.
     *
     * @return string String representation of the video file
     */
    public function __toString() {
        return $this->file;
    }

    /**
     * Get URL for the video file.
     *
     * Generates a Moodle URL for accessing the video file with optional
     * force download parameter.
     *
     * @param bool $forcedownload Whether to force download instead of streaming
     * @return \moodle_url|null Video file URL or null if no file exists
     */
    public function get_url($forcedownload = false) {
        if (empty($this->file)) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
                $this->context->id, 'mod_videoassessment', 'video', 0,
                $this->file->get_filepath(), $this->file->get_filename(), $forcedownload);
    }

    /**
     * Get URL for the video thumbnail.
     *
     * Generates a Moodle URL for accessing the thumbnail image file.
     *
     * @return \moodle_url|null Thumbnail URL or null if no thumbnail exists
     */
    public function get_thumbnail_url() {
        if ($this->thumbnail) {
            return \moodle_url::make_pluginfile_url(
                    $this->context->id, 'mod_videoassessment', 'video', 0,
                    $this->thumbnail->get_filepath(), $this->thumbnail->get_filename());
        }
        return null;
    }

    /**
     * Render thumbnail image HTML.
     *
     * Creates HTML img tag for the thumbnail with fallback to default content
     * if no thumbnail is available.
     *
     * @param string|null $defaultcontent Default content to show if no thumbnail
     * @return string HTML img tag or default content
     */
    public function render_thumbnail($defaultcontent = null) {
        if ($url = $this->get_thumbnail_url()) {
            return \html_writer::empty_tag('img', array('src' => $url));
        }
        return $defaultcontent;
    }

    /**
     * Render thumbnail with preview link.
     *
     * Creates a clickable thumbnail that links to the video with appropriate
     * CSS classes and data attributes for JavaScript handling.
     *
     * @param string|null $defaultcontent Default content to show if no thumbnail
     * @return string HTML anchor tag with thumbnail image
     */
    public function render_thumbnail_with_preview($defaultcontent = null) {
        return \html_writer::tag('a', $this->render_thumbnail($defaultcontent), array(
                'href' => $this->get_url(),
                'class' => 'videolink',
                'data-videoid' => $this->data->id,
        ));
    }

    /**
     * Check if video has an associated file.
     *
     * Determines whether the video object has a valid file associated with it.
     *
     * @return bool True if file exists, false otherwise
     */
    public function has_file() {
        return !empty($this->file);
    }

    /**
     * Delete associated video and thumbnail files.
     *
     * Removes both the main video file and thumbnail from the file storage
     * system if they exist.
     *
     * @return void
     */
    public function delete_file() {
        if ($this->file) {
            $this->file->delete();
        }
        if ($this->thumbnail) {
            $this->thumbnail->delete();
        }
    }

    /**
     * Create video object from database ID.
     *
     * Static factory method that creates a video object by loading
     * the video data from the database using the provided video ID.
     *
     * @param \context_module $context Module context for file operations
     * @param int $videoid Video ID from the database
     * @return \mod_videoassessment\video Video object instance
     * @throws \dml_exception If video record not found
     */
    public static function from_id(\context_module $context, $videoid) {
        global $DB;

        $data = $DB->get_record('videoassessment_videos', array('id' => $videoid), '*', MUST_EXIST);
        return new self($context, $data);
    }
}
