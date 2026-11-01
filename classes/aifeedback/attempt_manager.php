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

namespace mod_videoassessment\aifeedback;

/**
 * Life cycle of a spoken attempt sent for AI feedback.
 *
 * Stage 1 of the AI work (October 2026) is audio only: the learner
 * records or uploads speech, it is transcribed, the site's AI provider
 * comments on the transcript, and the learner reads the marked-up
 * transcript and tries again. Each recording is one attempt; attempts are
 * kept so teachers (and the research team) can see how often learners
 * retried.
 *
 * Processing is started by the learner's browser right after the upload
 * so feedback arrives within a minute, and an ad-hoc task picks up any
 * attempt the browser did not finish (closed tab, timeout).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /** @var string Attempts table. */
    const TABLE = 'videoassessment_aifb_attempts';

    /** @var string File area holding the recordings (itemid = attempt id). */
    const FILEAREA = 'aifbaudio';

    /** @var int Waiting to be processed. */
    const STATUS_QUEUED = 0;
    /** @var int Being transcribed or analysed. */
    const STATUS_PROCESSING = 1;
    /** @var int Feedback ready. */
    const STATUS_DONE = 2;
    /** @var int Transcription or analysis failed; the learner may retry. */
    const STATUS_FAILED = 3;

    /** @var string[] Audio formats the transcription API accepts. */
    const EXTENSIONS = ['webm', 'ogg', 'oga', 'mp3', 'mpga', 'mpeg', 'm4a', 'mp4', 'wav', 'flac'];

    /** @var string Separates the learner-facing error from the technical detail. */
    const DETAIL_SEPARATOR = "\n--\n";

    /** @var \stdClass Activity record. */
    protected \stdClass $va;

    /** @var \context_module Activity context. */
    protected \context_module $context;

    /**
     * Constructor.
     *
     * @param \stdClass $va The videoassessment record.
     * @param \context_module $context Its module context.
     */
    public function __construct(\stdClass $va, \context_module $context) {
        $this->va = $va;
        $this->context = $context;
    }

    /**
     * Whether the teacher turned AI feedback on for this activity.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return !empty($this->va->aifeedback);
    }

    /**
     * Whether the site has both a transcription service and an AI provider.
     *
     * @return bool
     */
    public static function site_ready(): bool {
        return transcriber::is_configured() && analyser::is_available();
    }

    /**
     * Store a new recording and queue it.
     *
     * @param int $userid The learner.
     * @param string $path Temporary path of the uploaded file.
     * @param string $originalname File name as sent by the browser (gives the format).
     * @param int $duration Length in seconds reported by the browser, 0 if unknown.
     * @return \stdClass The attempt record.
     * @throws \moodle_exception When the file is empty, too large or not audio we can send.
     */
    public function create_attempt(int $userid, string $path, string $originalname, int $duration = 0): \stdClass {
        global $DB;

        $size = is_readable($path) ? filesize($path) : 0;
        if (!$size) {
            throw new \moodle_exception('aifb_error_nofile', 'videoassessment');
        }
        if ($size > transcriber::MAX_BYTES) {
            throw new \moodle_exception('aifb_error_toolarge', 'videoassessment');
        }
        $extension = \core_text::strtolower(pathinfo($originalname, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \moodle_exception('aifb_error_format', 'videoassessment', '', implode(', ', self::EXTENSIONS));
        }

        $now = time();
        $last = (int)$DB->get_field(self::TABLE, 'MAX(attempt)', ['videoassessment' => $this->va->id, 'userid' => $userid]);
        $attempt = (object)[
            'videoassessment' => $this->va->id,
            'userid' => $userid,
            'attempt' => $last + 1,
            'status' => self::STATUS_QUEUED,
            'duration' => max(0, $duration),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $attempt->id = $DB->insert_record(self::TABLE, $attempt);

        get_file_storage()->create_file_from_pathname([
            'contextid' => $this->context->id,
            'component' => 'mod_videoassessment',
            'filearea' => self::FILEAREA,
            'itemid' => $attempt->id,
            'filepath' => '/',
            'filename' => 'attempt' . $attempt->attempt . '.' . $extension,
            'userid' => $userid,
        ], $path);

        self::queue_task($attempt);

        return $attempt;
    }

    /**
     * Transcribe and analyse one attempt.
     *
     * Safe to call from the browser request and the ad-hoc task at the
     * same time: a lock makes sure only one of them talks to the services.
     * An attempt already transcribed is not transcribed again on retry.
     *
     * @param int $attemptid The attempt.
     * @return \stdClass|null The attempt after processing, or null if it no longer exists.
     */
    public static function process_attempt(int $attemptid): ?\stdClass {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('mod_videoassessment_aifeedback');
        $lock = $factory->get_lock('attempt' . $attemptid, 0);
        if (!$lock) {
            // Someone else is processing it right now.
            return $DB->get_record(self::TABLE, ['id' => $attemptid]) ?: null;
        }

        try {
            $attempt = $DB->get_record(self::TABLE, ['id' => $attemptid]);
            if (!$attempt) {
                return null;
            }
            // Holding the lock, a "processing" row means an earlier worker died.
            if (!in_array((int)$attempt->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)) {
                return $attempt;
            }

            self::set_status($attempt, self::STATUS_PROCESSING);
            try {
                $va = $DB->get_record('videoassessment', ['id' => $attempt->videoassessment], '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);

                if (trim((string)$attempt->transcript) === '') {
                    $file = (new self($va, $context))->get_audio_file($attempt);
                    if (!$file) {
                        throw new \moodle_exception('aifb_error_nofile', 'videoassessment');
                    }
                    $attempt->transcript = (new transcriber())->transcribe($file, trim((string)$va->aitranscribelang));
                    $DB->set_field(self::TABLE, 'transcript', $attempt->transcript, ['id' => $attempt->id]);
                }

                $feedback = (new analyser())->analyse($va, $attempt->transcript, $context->id, (int)$attempt->userid);
                $attempt->feedback = json_encode($feedback);
                $attempt->errormessage = null;
                $attempt->timeprocessed = time();
                self::set_status($attempt, self::STATUS_DONE);
            } catch (\Throwable $e) {
                if ($e instanceof \moodle_exception && $e->errorcode === 'aifb_stillrunning') {
                    // A slow transcription job: not a failure. Queue it again; the
                    // browser keeps polling and the next try resumes the same job.
                    self::set_status($attempt, self::STATUS_QUEUED);
                    self::queue_task($attempt, time() + 30);
                    return $attempt;
                }
                // With developer debugging on, getMessage() also carries the debug info,
                // so the learner-facing text is rebuilt from the string itself.
                if ($e instanceof \moodle_exception && get_string_manager()->string_exists($e->errorcode, $e->module)) {
                    $message = get_string($e->errorcode, $e->module, $e->a);
                    $detail = (string)$e->debuginfo;
                } else {
                    $message = get_string('aifb_error_analyse', 'videoassessment');
                    $detail = $e->getMessage();
                }
                $attempt->errormessage = $message . ($detail !== '' ? self::DETAIL_SEPARATOR . $detail : '');
                self::set_status($attempt, self::STATUS_FAILED);
            }
            return $attempt;
        } finally {
            $lock->release();
        }
    }

    /**
     * Put a failed attempt back in the queue.
     *
     * @param \stdClass $attempt The attempt.
     * @return void
     */
    public static function retry(\stdClass $attempt): void {
        if ((int)$attempt->status !== self::STATUS_FAILED) {
            return;
        }
        $attempt->errormessage = null;
        self::set_status($attempt, self::STATUS_QUEUED);
        self::queue_task($attempt);
    }

    /**
     * Queue the ad-hoc task that processes an attempt if the browser does not.
     *
     * @param \stdClass $attempt The attempt.
     * @param int $runafter Unix time before which the task must not run, 0 for the next cron run.
     * @return void
     */
    protected static function queue_task(\stdClass $attempt, int $runafter = 0): void {
        $task = new \mod_videoassessment\task\process_ai_feedback();
        $task->set_custom_data(['attemptid' => $attempt->id]);
        $task->set_userid((int)$attempt->userid);
        if ($runafter) {
            $task->set_next_run_time($runafter);
        }
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Save a status change.
     *
     * @param \stdClass $attempt The attempt; its fields are written back.
     * @param int $status New status.
     * @return void
     */
    protected static function set_status(\stdClass $attempt, int $status): void {
        global $DB;
        $attempt->status = $status;
        $attempt->timemodified = time();
        $DB->update_record(self::TABLE, $attempt);
    }

    /**
     * One attempt of this activity.
     *
     * @param int $attemptid The attempt.
     * @return \stdClass|null
     */
    public function get_attempt(int $attemptid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $attemptid, 'videoassessment' => $this->va->id]) ?: null;
    }

    /**
     * A learner's attempts, newest first.
     *
     * @param int $userid The learner.
     * @return \stdClass[]
     */
    public function get_user_attempts(int $userid): array {
        global $DB;
        return array_values($DB->get_records(
            self::TABLE,
            ['videoassessment' => $this->va->id, 'userid' => $userid],
            'attempt DESC'
        ));
    }

    /**
     * Per-learner counts for the teacher's overview.
     *
     * @return \stdClass[] Keyed by user id: attempts, done, firstattempt, lastattempt, viewed.
     */
    public function get_summary(): array {
        global $DB;
        $sql = "SELECT userid,
                       COUNT(1) AS attempts,
                       SUM(CASE WHEN status = :done THEN 1 ELSE 0 END) AS done,
                       SUM(CASE WHEN timeviewed > 0 THEN 1 ELSE 0 END) AS viewed,
                       MIN(timecreated) AS firstattempt,
                       MAX(timecreated) AS lastattempt
                  FROM {" . self::TABLE . "}
                 WHERE videoassessment = :va
              GROUP BY userid";
        return $DB->get_records_sql($sql, ['done' => self::STATUS_DONE, 'va' => $this->va->id]);
    }

    /**
     * Record that the learner has seen the finished feedback.
     *
     * @param \stdClass $attempt The attempt.
     * @return void
     */
    public function mark_viewed(\stdClass $attempt): void {
        global $DB;
        if ((int)$attempt->status === self::STATUS_DONE && empty($attempt->timeviewed)) {
            $attempt->timeviewed = time();
            $DB->set_field(self::TABLE, 'timeviewed', $attempt->timeviewed, ['id' => $attempt->id]);
        }
    }

    /**
     * The recording of an attempt.
     *
     * @param \stdClass $attempt The attempt.
     * @return \stored_file|null
     */
    public function get_audio_file(\stdClass $attempt): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            'mod_videoassessment',
            self::FILEAREA,
            $attempt->id,
            'id',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Address the browser plays the recording from.
     *
     * @param \stdClass $attempt The attempt.
     * @return \moodle_url|null
     */
    public function get_audio_url(\stdClass $attempt): ?\moodle_url {
        $file = $this->get_audio_file($attempt);
        if (!$file) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Remove attempts and recordings.
     *
     * @param int $vaid The activity.
     * @param int $contextid Its module context (for the files).
     * @param int[]|null $userids Only these learners; null for everyone.
     * @return void
     */
    public static function delete_attempts(int $vaid, int $contextid, ?array $userids = null): void {
        global $DB;
        $params = ['va' => $vaid];
        $where = 'videoassessment = :va';
        if ($userids !== null) {
            if (!$userids) {
                return;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= " AND userid {$insql}";
            $params += $inparams;
        }
        $fs = get_file_storage();
        foreach ($DB->get_fieldset_select(self::TABLE, 'id', $where, $params) as $attemptid) {
            $fs->delete_area_files($contextid, 'mod_videoassessment', self::FILEAREA, $attemptid);
        }
        $DB->delete_records_select(self::TABLE, $where, $params);
    }

    /**
     * The message a learner sees for a failed attempt (without technical detail).
     *
     * @param \stdClass $attempt The attempt.
     * @param bool $withdetail Also return the provider's error (for teachers).
     * @return string
     */
    public static function error_text(\stdClass $attempt, bool $withdetail): string {
        $parts = explode(self::DETAIL_SEPARATOR, (string)$attempt->errormessage, 2);
        if ($withdetail && isset($parts[1])) {
            return $parts[0] . ' (' . $parts[1] . ')';
        }
        return $parts[0];
    }
}
