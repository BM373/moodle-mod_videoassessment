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

defined('MOODLE_INTERNAL') || die();

/**
 * Bulk video upload and processing handler for Video Assessment activities.
 *
 * Handles asynchronous video conversion, thumbnail generation, and file storage
 * for uploaded video files in the Video Assessment module.
 *
 * @package   mod_videoassessment
 * @copyright 2024 Don Hinkleman (hinkelman@mac.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videoassessment_bulkupload {
    /**
     * Thumbnail file extension format.
     *
     * @var string
     */
    const THUMBNAIL_FORMAT = '.jpg';

    /**
     * Course module record for this activity.
     *
     * @var \stdClass
     */
    private $cm;
    /**
     * Course record for this activity.
     *
     * @var \stdClass
     */
    private $course;
    /**
     * Video Assessment activity record.
     *
     * @var \stdClass
     */
    private $assessment;
    /**
     * Module context for this activity.
     *
     * @var \context_module
     */
    private $context;

    /**
     * Magic getter to access assessment properties directly.
     *
     * @param string $name Property name to access
     * @return mixed Property value from assessment object
     */
    public function __get($name) {
        return $this->assessment->$name;
    }

    /**
     * Initialize bulk upload handler for a Video Assessment activity.
     *
     * Loads course module, assessment, course, and context records.
     *
     * @param int $cmid Course module id
     * @throws \moodle_exception If course module, assessment, or course not found
     */
    public function __construct($cmid) {
        global $DB, $PAGE;

        $this->cm = get_coursemodule_from_id('videoassessment', $cmid);
        if (!$this->cm) {
            throw new moodle_exception('invalidcoursemodule');
        }
        $this->assessment = $DB->get_record('videoassessment', array('id' => $this->cm->instance));
        if (!$this->assessment) {
            throw new moodle_exception('invalidid', 'videoassessment');
        }
        $this->course = $DB->get_record('course', array('id' => $this->assessment->course));
        if (!$this->course) {
            throw new moodle_exception('coursemisconf', 'videoassessment');
        }

        $this->context = context_module::instance($this->cm->id);
        $PAGE->set_context($this->context);
    }

    /**
     * Require user login and bulk upload capability.
     *
     * @return void
     */
    public function require_capability() {
        require_login($this->course, true, $this->cm);
        require_capability('mod/videoassessment:bulkupload', $this->context);
    }

    /**
     * Get the temporary directory path for this activity's uploads.
     *
     * @return string Path to temporary directory
     */
    public function get_tempdir() {
        $temppath = 'videoassessment/' . $this->cm->id;
        $tempdir = make_temp_directory($temppath);
        return $tempdir;
    }

    /**
     * Start asynchronous video processing for an uploaded file.
     *
     * Moves uploaded file to temp directory, creates database record,
     * and triggers background conversion process.
     *
     * @param array $file Uploaded file array from $_FILES
     * @return string Encoded filename for progress tracking
     * @throws \moodle_exception If file upload fails or processing cannot start
     */
    public function start_async(array $file) {
        if (!empty($file['error'])) {
            throw new moodle_exception('nofile');
        }

        $this->create_temp_dirs();

        $name = $this->get_temp_name(clean_param($file['name'], PARAM_FILE));
        $path = $this->get_tempdir() . '/upload/' . $name;
        $path = self::encode_to_native_pathname($path);

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new moodle_exception('nofile');
        }

        $this->video_data_add($name, $file['name']);

        @unlink("$path.err");
        @unlink("$path.log");

        $url = new moodle_url('/mod/videoassessment/bulkupload/async.php');
        $url->param('cmid', $this->cm->id);
        $url->param('file', $name);
        $token = md5($name . get_site_identifier());
        $url->param('token', $token);
        self::async_http_get($url->out(false));

        return self::encode_filename($name);
    }

    /**
     * Generate a unique temporary filename for upload processing.
     *
     * Creates a timestamp-based name with original extension, ensuring
     * no conflicts with existing files in upload or convert directories.
     *
     * @param string $name Original filename
     * @return string Unique temporary filename
     */
    public function get_temp_name($name) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!$ext) {
            debugging("File has no extension: $name");
        }

        $tmpdir = $this->get_tempdir();
        $base = time();
        $i = 0;
        while (true) {
            $tmpname = $base.$i.'.'.$ext;
            if (!file_exists($tmpdir.'/upload/'.$tmpname)
                && !file_exists($tmpdir.'/convert/'.$tmpname)) {
                break;
            }
            $i++;
        }

        return $tmpname;
    }

    /**
     * Perform video conversion and thumbnail generation.
     *
     * Converts uploaded video to configured format, generates thumbnail,
     * stores files in Moodle file storage, and cleans up temporary files.
     *
     * @param string $name Temporary filename to process
     * @throws \Exception If conversion or thumbnail generation fails
     */
    public function convert($name) {
        global $CFG;

        try {
            $srcpath = $this->get_tempdir().'/upload/'.$name;
            $base = pathinfo($name, PATHINFO_FILENAME);
            $path = $this->get_tempdir().'/convert/'.$base;

            $videoformat = $CFG->videoassessment_videoformat;
            $thumbformat = self::THUMBNAIL_FORMAT;

            // Convert video.
            $command = $CFG->videoassessment_ffmpegcommand;
            $command = self::fix_ffmpeg_options($command, $videoformat);
            $cmdline = strtr($command, array(
                '{INPUT}'  => escapeshellarg($srcpath),
                '{OUTPUT}' => escapeshellarg($path.$videoformat),
            )) . ' > ' . escapeshellarg("$path.log") . ' 2>&1';
            $retval = self::exec_nolimit($cmdline);
            if ($retval != 0) {
                throw new Exception("failed to execute: $cmdline");
            }
            // Generate thumbnail.
            $command = $CFG->videoassessment_ffmpegthumbnailcommand;
            $command = self::fix_ffmpeg_options($command, $thumbformat);
            $cmdline = strtr($command, array(
                '{INPUT}'  => escapeshellarg($path.$videoformat),
                '{OUTPUT}' => escapeshellarg($path . $thumbformat),
                )) . ' > ' . escapeshellarg($path . $thumbformat . '.log') . ' 2>&1';
            $retval = self::exec_nolimit($cmdline);
            if ($retval != 0) {
                throw new Exception("failed to execute: $cmdline");
            }
            // Generate a hint track for progressive playback (MP4 only).
            if ($videoformat == '.mp4' &&
                !empty($CFG->videoassessment_mp4boxcommand)) {
                // Network-mounted directories may fail to rename,
                // so save as a different name and copy & delete.
                $cmdline = sprintf('%s -hint %s -out %s >%s 2>&1',
                    $CFG->videoassessment_mp4boxcommand,
                    escapeshellarg($path . $videoformat),
                    escapeshellarg($path . '.hint.mp4'),
                    escapeshellarg($path . '.box.log'));
                $retval = self::exec_nolimit($cmdline);
                if ($retval == 0 && file_exists($path . '.hint.mp4')) {
                    @copy($path . '.hint.mp4', $path . $videoformat);
                    @unlink($path . '.hint.mp4');
                }
            }

            // Move to Moodle Storage (Overwrite).
            $file = $this->store_file($path . $videoformat, $base.$videoformat, true);
            $thumbnail = $this->store_file($path . $thumbformat, $base.$thumbformat, true);
            $this->video_data_update($name, $file, $thumbnail);
            @unlink($path . $videoformat);
            @unlink($path . $thumbformat);

            // Delete origin file.
            @unlink($srcpath);

        } catch (Exception $ex) {
            @file_put_contents("$path.err", $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * Create necessary temporary directories for upload and conversion.
     *
     * @return void
     */
    public function create_temp_dirs() {
        make_temp_directory('videoassessment/' . $this->cm->id . '/upload');
        make_temp_directory('videoassessment/' . $this->cm->id . '/convert');
    }

    /**
     * Add a new video record to the database.
     *
     * Creates initial database entry for uploaded video with temporary name.
     *
     * @param string $tmpname Temporary filename
     * @param string $originalname Original uploaded filename
     * @return int Database record id
     */
    public function video_data_add($tmpname, $originalname) {
        global $DB;

        $video = new stdClass();
        $video->videoassessment = $this->assessment->id;
        $video->tmpname = $tmpname;
        $video->originalname = $originalname;
        $video->timecreated = $video->timemodified = time();

        return $DB->insert_record('videoassessment_videos', $video);
    }

    /**
     * Add a YouTube video record to the database.
     *
     * Creates database entry for YouTube video with metadata.
     *
     * @param string $filepath File path (typically "/" for YouTube)
     * @param string $filename Video filename
     * @param string $thumbnailname Thumbnail filename
     * @param string $tmpname Temporary identifier (typically "Youtube")
     * @param string $originalname Original video title/name
     * @return int Database record id
     */
    public function youtube_video_data_add($filepath, $filename, $thumbnailname, $tmpname, $originalname) {
        global $DB;

        $video = new stdClass();
        $video->videoassessment = $this->assessment->id;
        $video->filename = $filename;
        $video->thumbnailname = $thumbnailname;
        $video->filepath = $filepath;
        $video->tmpname = $tmpname;
        $video->originalname = $originalname;
        $video->timecreated = $video->timemodified = time();

        return $DB->insert_record('videoassessment_videos', $video);
    }
    /**
     * Update video record with processed file information.
     *
     * Updates database record with final file paths and names after conversion.
     *
     * @param string $tmpname Temporary filename to match
     * @param \stored_file $file Processed video file
     * @param \stored_file $thumbnail Generated thumbnail file
     * @throws \coding_exception If video record not found
     */
    private function video_data_update($tmpname, stored_file $file, stored_file $thumbnail) {
        global $DB;

        $video = $DB->get_record('videoassessment_videos',
                ['videoassessment' => $this->assessment->id, 'tmpname' => $tmpname]);
        if (!$video) {
            throw new coding_exception(get_string('errornovideorecord', 'videoassessment'));
        }
        $video->filepath = $file->get_filepath();
        $video->filename = $file->get_filename();
        $video->thumbnailname = $thumbnail->get_filename();
        $video->timemodified = time();

        $DB->update_record('videoassessment_videos', $video);
    }

    /**
     * Get conversion progress percentage for a file.
     *
     * Reads conversion log files to determine processing progress.
     * Returns error content if conversion failed.
     *
     * @param string $code Encoded filename for progress tracking
     * @return int Progress percentage (0-100) or outputs error and dies
     * @throws \moodle_exception If encoded filename is invalid
     */
    public function get_progress($code) {
        $name = @self::decode_filename($code);
        if (!$name) {
            throw new moodle_exception('nofile');
        }
        $uploadpath = $this->get_tempdir().'/upload/'.$name;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $convertpath = $this->get_tempdir().'/convert/'.$base;

        if (file_exists("$convertpath.err")) {
            debugging("$convertpath.err exists");
            send_headers('text/plain', false);
            @readfile("$convertpath.err");
            die;
        }

        if (file_exists("$convertpath.log")) {
            $progress = self::parse_ffmpeg_progress(file_get_contents("$convertpath.log"));

            if ($progress > 0.95 && !file_exists($uploadpath)) {
                return 100;
            }

            return floor(100 * $progress);
        }

        return 0;
    }

    /**
     * Get all video files stored for this activity.
     *
     * Retrieves video files from file storage, filtered by video extensions
     * and sorted by filename in descending order.
     *
     * @return array List of stored_file objects
     */
    public function get_files() {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_videoassessment', 'video');
        $files = array_filter($files, function (stored_file $file) {
            return !$file->is_directory() && preg_match('/\.(mp4|mov|avi|webm|ogv|flv|mkv)$/i', $file->get_filename());
        });
        uasort($files, function ($lhs, $rhs)
        {
            return -strnatcasecmp($lhs->get_filename(), $rhs->get_filename());
        });
        return $files;
    }

    /**
     * Store a file into Moodle file storage.
     *
     * Creates a stored_file from a local file path, optionally overwriting
     * existing files with the same name.
     *
     * @param string $path Absolute path to the file
     * @param string $name Stored file name to save as
     * @param boolean $overwrite Overwrites existing file if true
     * @return \stored_file Created file object
     */
    private function store_file($path, $name, $overwrite = false) {
        $fs = get_file_storage();
        if ($overwrite) {
            if ($file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, '/', $name)) {
                $file->delete();
            }
        }
        $record = array(
            'contextid' => $this->context->id,
            'component' => 'mod_videoassessment',
            'filearea'  => 'video',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $name);
        return $fs->create_file_from_pathname($record, $path);
    }

    /**
     * Move a stored file to a new path and optionally rename it.
     *
     * Creates a new file at the target path and deletes the original.
     * Also handles associated thumbnail files automatically.
     *
     * @param \stored_file $file File to move
     * @param string $newpath New file path
     * @param boolean $rename Rename if file exists at destination
     * @return void
     */
    public function move_file(stored_file $file, $newpath, $rename = false) {
        $fs = get_file_storage();
        $record = new stdClass();
        $record->filepath = $newpath;
        if ($rename && $fs->file_exists($file->get_contextid(),
            'mod_videoassessment', 'video', 0, '/', $file->get_filename())) {
            $ext = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
            $name = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            for ($i = 1; $i == 0; $i++) {
                $record->filename = sprintf('%s-%d.%s', $name, $i, $ext);
                if (!$fs->file_exists($file->get_contextid(),
                    'mod_videoassessment', 'video', 0, '/', $record->filename)) {
                    break;
                }
            }
        }
        if ($thumbfile = $fs->get_file($file->get_contextid(),
            'mod_videoassessment', 'video', 0, $file->get_filepath(),
            pathinfo($file->get_filename(), PATHINFO_FILENAME) . self::THUMBNAIL_FORMAT)) {
            $thumbrecord = clone $record;
            if (!empty($record->filename)) {
                $thumbrecord->filename
                    = pathinfo($record->filename, PATHINFO_FILENAME) . self::THUMBNAIL_FORMAT;
            }
            $fs->create_file_from_storedfile($thumbrecord, $thumbfile);
            $thumbfile->delete();
        }
        $fs->create_file_from_storedfile($record, $file);
        $file->delete();
    }

    /**
     * Encode filename for safe URL transmission.
     *
     * @param string $name Original filename
     * @return string URL-safe encoded filename
     */
    private static function encode_filename($name) {
        return strtr(base64_encode($name), array('+' => '-', '/' => '.', '=' => '_'));
    }
    /**
     * Decode URL-safe filename back to original.
     *
     * @param string $code Encoded filename
     * @return string Original filename
     */
    private static function decode_filename($code) {
        return base64_decode(strtr($code, array('-' => '+', '.' => '/', '_' => '=')));
    }

    /**
     * Fix and validate ffmpeg command options for specific formats.
     *
     * Adds missing input/output options and format-specific codec settings.
     *
     * @param string $command Original ffmpeg command template
     * @param string $format Target video format (e.g., '.mp4', '.ogv')
     * @return string Fixed command string
     * @throws \Exception If command template is invalid
     */
    private static function fix_ffmpeg_options($command, $format) {
        if (strpos($command, '{INPUT}') <= stripos($command, 'ffmpeg') ||
            strpos($command, '{OUTPUT}') <= stripos($command, 'ffmpeg')) {
            throw new Exception('invalid command setting');
        }
        if (strpos($command, '-i') === false) {
            $command = str_replace('{INPUT}', '-i {INPUT}', $command);
        }
        if (strpos($command, '-y') === false) {
            $command = str_replace('{OUTPUT}', '-y {OUTPUT}', $command);
        }

        switch ($format) {
            case '.ogv':
            case '.ogg':
                // use Vorbis instead of FLAC
                if (strpos($command, '-acodec') === false) {
                    $command = str_replace('{OUTPUT}', '-acodec libvorbis {OUTPUT}', $command);
                }
                break;
            case '.flv':
                // flv does not support 48k samples/sec
                if (strpos($command, '-ar') === false) {
                    $command = str_replace('{OUTPUT}', '-ar 44100 {OUTPUT}', $command);
                }
                break;
        }
        return $command;
    }

    /**
     * Parse ffmpeg conversion progress from log output.
     *
     * Extracts duration and current time from ffmpeg log to calculate
     * conversion progress percentage.
     *
     * @param string $log ffmpeg log content
     * @return float|null Progress ratio (0.0-1.0) or null if cannot parse
     */
    private static function parse_ffmpeg_progress($log) {
        if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+),/', $log, $match)) {
            list (, $h, $m, $s) = $match;
            $duration = $h * 60 * 60 + $m * 60 + $s;
            // ffmpeg 0.8.2
            if (preg_match_all('/ time=(\d+):(\d+):(\d+\.\d+) /', $log, $matches, PREG_SET_ORDER)) {
                list (, $h, $m, $s) = end($matches);
                $time = $h * 60 * 60 + $m * 60 + $s;
                return $time / $duration;
            }
            // ffmpeg latest
            if (preg_match_all('/ time=(\d+\.\d+) /', $log, $matches, PREG_SET_ORDER)) {
                list (, $time) = end($matches);
                return $time / $duration;
            }
            return 0.0;
        }
        return null;
    }

    /**
     * Send asynchronous HTTP GET request without waiting for response.
     *
     * Opens socket connection, sends request, and closes immediately.
     * Used to trigger background processing without blocking.
     *
     * @param string $url Target URL to request
     * @throws \Exception If socket connection fails
     */
    private static function async_http_get($url) {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'http';
        $host   = $parts['host'] ?? '';
        $port   = $parts['port'] ?? null;
        $path   = $parts['path'] ?? '/';
        $query  = $parts['query'] ?? null;

        $ssl = $scheme === 'https';
        if (empty($port)) {
            $port = $ssl ? 443 : 80;
        }
        $req = $path . (isset($query) ? "?$query" : '');
        $sock = fsockopen($ssl ? "ssl://$host" : $host, $port, $errno, $errstr);
        if (!$sock) {
            throw new \Exception($errstr);
        }
        stream_set_blocking($sock, false);
        fwrite($sock, "GET $req HTTP/1.1\r\n");
        fwrite($sock, "Host: $host\r\n");
        fwrite($sock, "Connection: close\r\n");
        fwrite($sock, "\r\n");
        fclose($sock);
    }

    /**
     * Execute shell command with unlimited time and memory.
     *
     * Removes time limits and environment restrictions for long-running
     * video conversion processes.
     *
     * @param string $cmdline Command line to execute
     * @return int Command exit code
     */
    private static function exec_nolimit($cmdline) {
        ignore_user_abort(true);
        set_time_limit(0);
        $output = array();
        $retval = 0;
        putenv('PATH=');
        putenv('LD_LIBRARY_PATH=');
        putenv('DYLD_LIBRARY_PATH=');
        exec($cmdline, $output, $retval);
        return $retval;
    }

    /**
     * Convert file path to OS-dependent character encoding.
     *
     * Handles Windows-specific encoding conversion for proper file system access.
     *
     * @param string $path UTF-8 encoded file path
     * @return string OS-native encoded path
     */
    private static function encode_to_native_pathname($path) {
        global $CFG;

        if ($CFG->ostype == 'WINDOWS') {
            $currentlocale = setlocale(LC_CTYPE, '0');
            if (@list(, $codepage) = explode('.', $currentlocale)) {
                // Even if an error occurs, conversion proceeds partially,
                // so do not determine based on the presence or absence of a return value.
                $success = true;
                set_error_handler(function ($errno, $errstr) use (&$success) {
                    $success = false;
                    debugging('Iconv error in encode_to_native_pathname');
                }, E_NOTICE);
                $shellpath = iconv('UTF-8', 'CP'.$codepage, $path);
                restore_error_handler();

                if ($success) {
                    return $shellpath;
                }
            }
        }
        return $path;
    }
}
