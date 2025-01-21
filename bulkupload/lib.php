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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

class videoassessment_bulkupload
{
    const THUMBNAIL_FORMAT = '.jpg';

    private $cm, $course, $assessment, $context;

    public function __get($name)
    {
        return $this->assessment->$name;
    }

    public function __construct($cmid)
    {
        global $DB, $PAGE;

        $this->cm = get_coursemodule_from_id('videoassessment', $cmid);
        if (!$this->cm)
            throw new moodle_exception('invalidcoursemodule');
        $this->assessment = $DB->get_record('videoassessment', array('id' => $this->cm->instance));
        if (!$this->assessment)
            throw new moodle_exception('invalidid', 'videoassessment');
        $this->course = $DB->get_record('course', array('id' => $this->assessment->course));
        if (!$this->course)
            throw new moodle_exception('coursemisconf', 'videoassessment');

        $this->context = context_module::instance($this->cm->id);
        $PAGE->set_context($this->context);
    }

    public function require_capability()
    {
        require_login($this->course, true, $this->cm);
        require_capability('mod/videoassessment:bulkupload', $this->context);
    }

    public function get_tempdir()
    {
        global $CFG;

        $tempdir = $CFG->dataroot . '/temp/videoassessment/' . $this->cm->id;
        check_dir_exists($tempdir);
        return $tempdir;
    }

    /**
     *
     * @param array $file
     * @return string
     * @throws moodle_exception
     */
    public function start_async(array $file)
    {
        if (!empty($file['error']))
            throw new moodle_exception('nofile');

        $this->create_temp_dirs();

        $name = $this->get_temp_name(clean_param($file['name'], PARAM_FILE));
        $path = $this->get_tempdir() . '/upload/' . $name;
        $path = self::encode_to_native_pathname($path);

        if (!move_uploaded_file($file['tmp_name'], $path))
            throw new moodle_exception('nofile');

        $this->video_data_add($name, $file['name']);

        @unlink("$path.err");
        @unlink("$path.log");

        $url = new moodle_url('/mod/videoassessment/bulkupload/async.php');
        $url->param('cmid', $this->cm->id);
        $url->param('file', $name);
        self::async_http_get($url->out(false));

        return self::encode_filename($name);
    }

    /**
     *
     * @param string $name
     * @return string
     */
    public function get_temp_name($name) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!$ext) {
            error_log("File has no extension: $name");
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
     *  動画変換/サムネール生成を行い、完了したら元ファイルを削除する
     *
     *  @param string $name
     */
    public function convert($name)
    {
        global $CFG;

        try {
            $srcpath = $this->get_tempdir().'/upload/'.$name;
            $base = pathinfo($name, PATHINFO_FILENAME);
            $path = $this->get_tempdir().'/convert/'.$base;

            $videoformat = $CFG->videoassessment_videoformat;
            $thumbformat = self::THUMBNAIL_FORMAT;

            // 動画変換
            $command = $CFG->videoassessment_ffmpegcommand;
            $command = self::fix_ffmpeg_options($command, $videoformat);
            $cmdline = strtr($command, array(
                '{INPUT}'  => escapeshellarg($srcpath),
                '{OUTPUT}' => escapeshellarg($path.$videoformat),
            )) . ' > ' . escapeshellarg("$path.log") . ' 2>&1';
//             error_log("cmdline = $cmdline");
            $retval = self::exec_nolimit($cmdline);
            if ($retval != 0)
                throw new Exception("failed to execute: $cmdline");

            // サムネール生成
            $command = $CFG->videoassessment_ffmpegthumbnailcommand;
            $command = self::fix_ffmpeg_options($command, $thumbformat);
            $cmdline = strtr($command, array(
                '{INPUT}'  => escapeshellarg($path.$videoformat),
                '{OUTPUT}' => escapeshellarg($path . $thumbformat)
                )) . ' > ' . escapeshellarg($path . $thumbformat . '.log') . ' 2>&1';
            $retval = self::exec_nolimit($cmdline);
            if ($retval != 0)
                throw new Exception("failed to execute: $cmdline");

            // プログレッシブ再生用のヒントトラック生成 (MP4のみ)
            if ($videoformat == '.mp4' &&
                !empty($CFG->videoassessment_mp4boxcommand))
            {
                // ネットワークマウントしているディレクトリは
                // リネームに失敗することがあるので別名保存してコピー＆削除する
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
                //if ($retval != 0)
                //    throw new Exception("failed to execute: $cmdline");
            }

            // Moodle ストレージに移動 (上書き)
            $file = $this->store_file($path . $videoformat, $base.$videoformat, true);
            $thumbnail = $this->store_file($path . $thumbformat, $base.$thumbformat, true);
            $this->video_data_update($name, $file, $thumbnail);
            @unlink($path . $videoformat);
            @unlink($path . $thumbformat);

            // 元ファイル削除
            @unlink($srcpath);

        } catch (Exception $ex) {
            @file_put_contents("$path.err", $ex->getMessage());
            throw $ex;
        }
    }

    public function create_temp_dirs() {
        check_dir_exists($this->get_tempdir().'/upload');
        check_dir_exists($this->get_tempdir().'/convert');
    }

    /**
     *
     * @param string $tmpname
     * @param string $originalname
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

    public function youtube_video_data_add($filepath,$filename,$thumbnailname,$tmpname, $originalname) {
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
     *
     * @param string $tmpname
     * @param stored_file $file
     */
    private function video_data_update($tmpname, stored_file $file, stored_file $thumbnail) {
        global $DB;

        $video = $DB->get_record('videoassessment_videos',
                array('videoassessment' => $this->assessment->id, 'tmpname' => $tmpname));
        if (!$video) {
            throw new coding_exception('先にビデオレコードを追加する');
        }
        $video->filepath = $file->get_filepath();
        $video->filename = $file->get_filename();
        $video->thumbnailname = $thumbnail->get_filename();
        $video->timemodified = time();

        $DB->update_record('videoassessment_videos', $video);
    }

    public function get_progress($code)
    {
        $name = @self::decode_filename($code);
        if (!$name)
            throw new moodle_exception('nofile');

        $uploadpath = $this->get_tempdir().'/upload/'.$name;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $convertpath = $this->get_tempdir().'/convert/'.$base;

        if (file_exists("$convertpath.err")) {
            error_log("$convertpath.err exists");
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
     * @return array
     */
    public function get_files()
    {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_videoassessment', 'video');
        $files = array_filter($files, function (stored_file $file) {
            return !$file->is_directory() && preg_match(videoassessment_base::RE_VIDEOEXT, $file->get_filename());
        });
        uasort($files, function ($lhs, $rhs)
        {
            return -strnatcasecmp($lhs->get_filename(), $rhs->get_filename());
        });
        return $files;
    }

    /**
     * Store a file into file storage
     *
     * @param string  $path       Absolute path to the file
     * @param string  $name       Stored file name to save as
     * @param boolean $overwrite  Overwrites existing file if true, throws exception otherwise
     * @return stored_file
     */
    private function store_file($path, $name, $overwrite = false)
    {
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
     * Move a path name of a stored_file
     *
     * @param stored_file $file
     * @param string      $newpath
     * @param boolean     $rename   Renames if new-uploaded file exists in same name
     */
    public function move_file(stored_file $file, $newpath, $rename = false)
    {
        $fs = get_file_storage();
        $record = new stdClass();
        $record->filepath = $newpath;
        if ($rename and $fs->file_exists($file->get_contextid(),
            'mod_videoassessment', 'video', 0, '/', $file->get_filename()))
        {
            $ext = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
            $name = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            for ($i = 1; ; $i++) {
                $record->filename = sprintf('%s-%d.%s', $name, $i, $ext);
                if (!$fs->file_exists($file->get_contextid(),
                    'mod_videoassessment', 'video', 0, '/', $record->filename))
                {
                    break;
                }
            }
        }
        if ($thumbfile = $fs->get_file($file->get_contextid(),
            'mod_videoassessment', 'video', 0, $file->get_filepath(),
            pathinfo($file->get_filename(), PATHINFO_FILENAME) . self::THUMBNAIL_FORMAT))
        {
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


    private static function encode_filename($name)
    {
        return strtr(base64_encode($name), array('+' => '-', '/' => '.', '=' => '_'));
    }
    private static function decode_filename($code)
    {
        return base64_decode(strtr($code, array('-' => '+', '.' => '/', '_' => '=')));
    }

    private static function fix_ffmpeg_options($command, $format)
    {
        if (strpos($command, '{INPUT}') <= stripos($command, 'ffmpeg') ||
            strpos($command, '{OUTPUT}') <= stripos($command, 'ffmpeg'))
        {
            throw new Exception('invalid command setting');
        }
        if (strpos($command, '-i') === false)
            $command = str_replace('{INPUT}', '-i {INPUT}', $command);
        if (strpos($command, '-y') === false)
            $command = str_replace('{OUTPUT}', '-y {OUTPUT}', $command);

        switch ($format) {
        case '.ogv':
        case '.ogg':
            // use Vorbis instead of FLAC
            if (strpos($command, '-acodec') === false)
                $command = str_replace('{OUTPUT}', '-acodec libvorbis {OUTPUT}', $command);
            break;
        case '.flv':
            // flv does not support 48k samples/sec
            if (strpos($command, '-ar') === false)
                $command = str_replace('{OUTPUT}', '-ar 44100 {OUTPUT}', $command);
            break;
        }
        return $command;
    }

    private static function parse_ffmpeg_progress($log)
    {
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

    private static function async_http_get($url)
    {
        extract(parse_url($url));
        $ssl = $scheme === 'https';
        if (empty($port))
            $port = $ssl ? 443 : 80;
        $req = $path . (isset($query) ? "?$query" : '');
        $sock = fsockopen($ssl ? "ssl://$host" : $host, $port, $errno, $errstr);
        if (!$sock)
            throw new \Exception($errstr);
        stream_set_blocking($sock, false);
        fwrite($sock, "GET $req HTTP/1.1\r\n");
        fwrite($sock, "Host: $host\r\n");
        fwrite($sock, "Connection: close\r\n");
        fwrite($sock, "\r\n");
        fclose($sock);
    }

    private static function exec_nolimit($cmdline)
    {
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
     * ファイルパスの文字列をOS依存の文字コードに変換
     *
     * @param string $path
     * @return string
     */
    private static function encode_to_native_pathname($path) {
        global $CFG;

        if ($CFG->ostype == 'WINDOWS') {
            $currentlocale = setlocale(LC_CTYPE, '0');
            if (@list(, $codepage) = explode('.', $currentlocale)) {
                // エラーが出ても途中まで変換されるので戻り値の有無で判定しない
                $success = true;
                set_error_handler(function ($errno, $errstr) use (&$success) {
                    $success = false;
                    error_log('Iconv error in encode_to_native_pathname');
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
