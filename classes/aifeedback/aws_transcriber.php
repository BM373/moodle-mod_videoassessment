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
 * Transcription with Amazon Transcribe.
 *
 * Transcribe only reads media from S3, so a recording is uploaded to the
 * site's bucket, a batch job writes the transcript back to the same bucket,
 * and the recording, the transcript and the job are deleted as soon as the
 * text has been read. Nothing stays in AWS after a successful run.
 *
 * The job name is derived from the stored file, so when the browser
 * request times out the ad-hoc task picks up the same job instead of
 * starting a second one.
 *
 * Bedrock's audio-capable models were tried first (October 2026): Voxtral
 * is listed with speech input but answers "Model does not support audio
 * modality" in every region, so Transcribe is the AWS option for now.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aws_transcriber {
    /** @var string Key prefix of the files written to the bucket. */
    const PREFIX = 'videoassessment-aifeedback/';

    /** @var int Seconds to wait for a job before leaving it to the next try. */
    const MAX_WAIT = 240;

    /** @var array<string, string> Moodle language code => Transcribe language code. */
    const LANGUAGES = [
        'ar' => 'ar-SA', 'de' => 'de-DE', 'en' => 'en-US', 'es' => 'es-ES', 'fr' => 'fr-FR',
        'hi' => 'hi-IN', 'id' => 'id-ID', 'it' => 'it-IT', 'ja' => 'ja-JP', 'ko' => 'ko-KR',
        'nl' => 'nl-NL', 'pl' => 'pl-PL', 'pt' => 'pt-BR', 'ru' => 'ru-RU', 'sv' => 'sv-SE',
        'th' => 'th-TH', 'tr' => 'tr-TR', 'vi' => 'vi-VN', 'zh' => 'zh-CN',
    ];

    /** @var array<string, string> File extension => Transcribe MediaFormat. */
    const FORMATS = [
        'webm' => 'webm', 'ogg' => 'ogg', 'oga' => 'ogg', 'mp3' => 'mp3', 'mpga' => 'mp3',
        'mpeg' => 'mp3', 'm4a' => 'm4a', 'mp4' => 'mp4', 'wav' => 'wav', 'flac' => 'flac',
    ];

    /** @var int Seconds between status checks. Tests set it to 0. */
    public int $pollinterval = 3;

    /** @var int Seconds to wait for the job in one try. Tests set it to 0. */
    public int $maxwait = self::MAX_WAIT;

    /** @var aws_client|null Client to use; null means one built from the settings. */
    protected ?aws_client $client;

    /**
     * Constructor.
     *
     * @param aws_client|null $client Client to use instead of the configured one.
     */
    public function __construct(?aws_client $client = null) {
        $this->client = $client;
    }

    /**
     * Whether credentials and a bucket are set.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return aws_client::is_configured() && self::get_bucket() !== '';
    }

    /**
     * The configured bucket, or '' when the name cannot be used.
     *
     * Names with dots are refused: they break the TLS certificate of the
     * bucket's virtual-hosted address.
     *
     * @return string
     */
    public static function get_bucket(): string {
        $bucket = trim((string)get_config('videoassessment', 'awss3bucket'));
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $bucket) ? $bucket : '';
    }

    /**
     * Transcribe one stored recording.
     *
     * @param \stored_file $file The learner's recording.
     * @param string $language ISO-639-1 code of the spoken language, or '' to let Transcribe identify it.
     * @return string The transcript.
     * @throws \moodle_exception When AWS is not configured, a call fails or the job fails; aifb_stillrunning
     *     when the job has not finished within {@see self::$maxwait} seconds.
     */
    public function transcribe(\stored_file $file, string $language = ''): string {
        if (self::get_bucket() === '' || ($this->client === null && !aws_client::is_configured())) {
            throw new \moodle_exception('aifb_error_notranscriber', 'videoassessment');
        }
        $client = $this->client ?? aws_client::from_config();
        $bucket = self::get_bucket();
        $extension = \core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $job = self::job_name($file);
        $mediakey = self::PREFIX . $job . '.' . $extension;
        $outputkey = self::PREFIX . $job . '.json';

        $this->s3($client, 'PUT', $bucket, $mediakey, $file->get_content(), [
            'content-type' => $file->get_mimetype() ?: 'application/octet-stream',
        ]);

        $request = [
            'TranscriptionJobName' => $job,
            'Media' => ['MediaFileUri' => "s3://{$bucket}/{$mediakey}"],
            'OutputBucketName' => $bucket,
            'OutputKey' => $outputkey,
        ];
        if (isset(self::FORMATS[$extension])) {
            $request['MediaFormat'] = self::FORMATS[$extension];
        }
        $code = self::LANGUAGES[explode('_', $language)[0]] ?? null;
        if ($code !== null) {
            $request['LanguageCode'] = $code;
        } else {
            $request['IdentifyLanguage'] = true;
        }

        $status = $this->start_job($client, $request);
        $deadline = time() + $this->maxwait;
        while (in_array($status['TranscriptionJobStatus'] ?? '', ['QUEUED', 'IN_PROGRESS'], true)) {
            if (time() >= $deadline) {
                // The job keeps running; the attempt is queued again and the next try picks it up by name.
                throw new \moodle_exception('aifb_stillrunning', 'videoassessment');
            }
            if ($this->pollinterval > 0) {
                sleep($this->pollinterval);
            }
            $status = $this->get_job($client, $job);
        }

        if (($status['TranscriptionJobStatus'] ?? '') !== 'COMPLETED') {
            $reason = (string)($status['FailureReason'] ?? $status['TranscriptionJobStatus'] ?? 'unknown');
            $this->clean_up($client, $bucket, [$mediakey, $outputkey], $job);
            throw new \moodle_exception('aifb_error_transcribe', 'videoassessment', '', null, 'Transcribe: ' . $reason);
        }

        $result = json_decode($this->s3($client, 'GET', $bucket, $outputkey), true);
        $this->clean_up($client, $bucket, [$mediakey, $outputkey], $job);
        $text = trim((string)($result['results']['transcripts'][0]['transcript'] ?? ''));
        if ($text === '') {
            throw new \moodle_exception('aifb_error_emptytranscript', 'videoassessment');
        }
        return $text;
    }

    /**
     * Job name for a recording: unique per site, attempt and file content.
     *
     * @param \stored_file $file The recording.
     * @return string
     */
    public static function job_name(\stored_file $file): string {
        global $CFG;
        return 'vam-' . substr(sha1($CFG->siteidentifier ?? $CFG->wwwroot), 0, 8)
            . '-' . $file->get_itemid() . '-' . substr($file->get_contenthash(), 0, 12);
    }

    /**
     * Start the job, or reuse the one an earlier try started.
     *
     * @param aws_client $client AWS client.
     * @param array $request StartTranscriptionJob request.
     * @return array The job (TranscriptionJob structure).
     */
    protected function start_job(aws_client $client, array $request): array {
        $response = $this->call($client, 'StartTranscriptionJob', $request, false);
        if ($response->getStatusCode() === 200) {
            return json_decode((string)$response->getBody(), true)['TranscriptionJob'] ?? [];
        }
        if (aws_client::error_type($response) !== 'ConflictException') {
            throw new \moodle_exception('aifb_error_transcribe', 'videoassessment', '', null, aws_client::error_detail($response));
        }
        $existing = $this->get_job($client, $request['TranscriptionJobName']);
        if (($existing['TranscriptionJobStatus'] ?? '') !== 'FAILED') {
            return $existing;
        }
        // An earlier try failed; start over under the same name.
        $this->call($client, 'DeleteTranscriptionJob', ['TranscriptionJobName' => $request['TranscriptionJobName']]);
        $response = $this->call($client, 'StartTranscriptionJob', $request);
        return json_decode((string)$response->getBody(), true)['TranscriptionJob'] ?? [];
    }

    /**
     * Current state of a job.
     *
     * @param aws_client $client AWS client.
     * @param string $job Job name.
     * @return array The job (TranscriptionJob structure).
     */
    protected function get_job(aws_client $client, string $job): array {
        $response = $this->call($client, 'GetTranscriptionJob', ['TranscriptionJobName' => $job]);
        return json_decode((string)$response->getBody(), true)['TranscriptionJob'] ?? [];
    }

    /**
     * Call the Transcribe JSON API.
     *
     * @param aws_client $client AWS client.
     * @param string $operation Operation name, e.g. StartTranscriptionJob.
     * @param array $request Request body.
     * @param bool $mustsucceed Throw when the call fails.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function call(aws_client $client, string $operation, array $request, bool $mustsucceed = true) {
        $response = $client->send(
            'POST',
            'transcribe',
            'transcribe.' . $client->get_region() . '.amazonaws.com',
            '/',
            json_encode($request),
            ['content-type' => 'application/x-amz-json-1.1', 'x-amz-target' => 'Transcribe.' . $operation],
            60,
            'aifb_error_transcribe'
        );
        if ($mustsucceed && $response->getStatusCode() !== 200) {
            throw new \moodle_exception(
                'aifb_error_transcribe',
                'videoassessment',
                '',
                null,
                $operation . ': ' . aws_client::error_detail($response)
            );
        }
        return $response;
    }

    /**
     * One S3 object request.
     *
     * @param aws_client $client AWS client.
     * @param string $method PUT, GET or DELETE.
     * @param string $bucket Bucket name.
     * @param string $key Object key.
     * @param string $body Object content for PUT.
     * @param array $headers Extra headers.
     * @return string Response body.
     */
    protected function s3(
        aws_client $client,
        string $method,
        string $bucket,
        string $key,
        string $body = '',
        array $headers = []
    ): string {
        $response = $client->send(
            $method,
            's3',
            $bucket . '.s3.' . $client->get_region() . '.amazonaws.com',
            '/' . $key,
            $body,
            $headers,
            120,
            'aifb_error_transcribe'
        );
        if ($response->getStatusCode() >= 300) {
            throw new \moodle_exception(
                'aifb_error_transcribe',
                'videoassessment',
                '',
                null,
                "S3 {$method}: " . aws_client::error_detail($response)
            );
        }
        return (string)$response->getBody();
    }

    /**
     * Delete the recording, the transcript file and the job.
     *
     * A failure here does not cost the learner their feedback, so it is
     * only reported to developers.
     *
     * @param aws_client $client AWS client.
     * @param string $bucket Bucket name.
     * @param string[] $keys Object keys.
     * @param string $job Job name.
     * @return void
     */
    protected function clean_up(aws_client $client, string $bucket, array $keys, string $job): void {
        try {
            foreach ($keys as $key) {
                $this->s3($client, 'DELETE', $bucket, $key);
            }
            $this->call($client, 'DeleteTranscriptionJob', ['TranscriptionJobName' => $job]);
        } catch (\moodle_exception $e) {
            debugging('Amazon Transcribe clean-up failed: ' . $e->debuginfo, DEBUG_DEVELOPER);
        }
    }
}
