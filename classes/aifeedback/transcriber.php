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

use core\http_client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Turns a learner's recording into text.
 *
 * Moodle 4.5's AI subsystem has no speech-to-text action, so the service
 * is a plugin setting: either an OpenAI-compatible /audio/transcriptions
 * endpoint (OpenAI itself, a gateway, or a Whisper server the institution
 * runs on its own hardware) or Amazon Transcribe ({@see aws_transcriber}).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transcriber {
    /** @var string Endpoint used when the admin has not set one. */
    public const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    /** @var string Model used when the admin has not set one. */
    public const DEFAULT_MODEL = 'whisper-1';

    /** @var int Largest file the OpenAI endpoint accepts. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /** @var string Service setting: OpenAI-compatible endpoint. */
    public const SERVICE_OPENAI = 'openai';

    /** @var string Service setting: Amazon Transcribe. */
    public const SERVICE_AWS = 'awstranscribe';

    /** @var aws_client|null AWS client to use instead of the configured one. */
    protected ?aws_client $awsclient;

    /**
     * Constructor.
     *
     * @param aws_client|null $awsclient AWS client to use instead of the configured one.
     */
    public function __construct(?aws_client $awsclient = null) {
        $this->awsclient = $awsclient;
    }

    /**
     * The transcription service chosen by the admin.
     *
     * @return string One of the SERVICE_* constants.
     */
    public static function service(): string {
        return get_config('videoassessment', 'aitranscribeservice') === self::SERVICE_AWS
            ? self::SERVICE_AWS
            : self::SERVICE_OPENAI;
    }

    /**
     * Whether transcription can run at all on this site.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        if (self::service() === self::SERVICE_AWS) {
            return aws_transcriber::is_configured();
        }
        return self::get_endpoint() !== '' && self::get_api_key() !== '';
    }

    /**
     * The configured endpoint.
     *
     * @return string
     */
    public static function get_endpoint(): string {
        $endpoint = get_config('videoassessment', 'aitranscribeendpoint');
        return $endpoint === false ? self::DEFAULT_ENDPOINT : trim($endpoint);
    }

    /**
     * The key sent with each request.
     *
     * The plugin's own key wins. When it is empty and the endpoint is
     * OpenAI's, the key of Moodle's OpenAI AI provider is reused so a site
     * only has to enter it once. That key is never sent to any other host.
     *
     * @return string
     */
    public static function get_api_key(): string {
        $key = trim((string)get_config('videoassessment', 'aitranscribeapikey'));
        if ($key !== '') {
            return $key;
        }
        $host = parse_url(self::get_endpoint(), PHP_URL_HOST);
        if ($host === 'api.openai.com') {
            return trim((string)get_config('aiprovider_openai', 'apikey'));
        }
        return '';
    }

    /**
     * Transcribe one stored recording.
     *
     * @param \stored_file $file The learner's recording.
     * @param string $language ISO-639-1 code of the spoken language, or '' to let the service detect it.
     * @return string The transcript.
     * @throws \moodle_exception When the service is not configured, refuses the request or returns no text.
     */
    public function transcribe(\stored_file $file, string $language = ''): string {
        if ($file->get_filesize() > self::MAX_BYTES) {
            throw new \moodle_exception('aifb_error_toolarge', 'videoassessment');
        }
        if (self::service() === self::SERVICE_AWS) {
            // Resolved through the container so tests can shorten its waits.
            $aws = $this->awsclient ? new aws_transcriber($this->awsclient) : \core\di::get(aws_transcriber::class);
            return $aws->transcribe($file, $language);
        }
        if (!self::is_configured()) {
            throw new \moodle_exception('aifb_error_notranscriber', 'videoassessment');
        }

        $model = trim((string)get_config('videoassessment', 'aitranscribemodel'));
        $multipart = [
            [
                'name' => 'file',
                'contents' => $file->get_content_file_handle(),
                'filename' => $file->get_filename(),
            ],
            ['name' => 'model', 'contents' => $model !== '' ? $model : self::DEFAULT_MODEL],
            ['name' => 'response_format', 'contents' => 'json'],
        ];
        if ($language !== '') {
            $multipart[] = ['name' => 'language', 'contents' => $language];
        }

        $client = \core\di::get(http_client::class);
        try {
            $response = $client->request('POST', self::get_endpoint(), [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . self::get_api_key()],
                RequestOptions::MULTIPART => $multipart,
                RequestOptions::TIMEOUT => 180,
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (GuzzleException $e) {
            throw new \moodle_exception('aifb_error_transcribe', 'videoassessment', '', null, $e->getMessage());
        }

        $body = (string)$response->getBody();
        $decoded = json_decode($body);
        if ($response->getStatusCode() !== 200) {
            $detail = $decoded->error->message ?? $response->getReasonPhrase();
            throw new \moodle_exception(
                'aifb_error_transcribe',
                'videoassessment',
                '',
                null,
                $response->getStatusCode() . ' ' . $detail
            );
        }
        $text = trim((string)($decoded->text ?? ''));
        if ($text === '') {
            throw new \moodle_exception('aifb_error_emptytranscript', 'videoassessment');
        }
        return $text;
    }
}
