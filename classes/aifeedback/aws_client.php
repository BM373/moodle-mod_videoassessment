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
use Psr\Http\Message\ResponseInterface;

/**
 * Signed requests to the AWS APIs the AI feedback uses: Bedrock Runtime
 * (written feedback), Amazon Transcribe and S3 (transcription).
 *
 * Moodle does not ship the AWS SDK, and bundling it would add tens of
 * megabytes to a plugin meant to be installed from a zip, so requests are
 * signed here with Signature Version 4. Only what the three calls need is
 * implemented: no query strings, the whole body hashed up front.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aws_client {
    /** @var string Region used when the admin has not set one (Tokyo). */
    public const DEFAULT_REGION = 'ap-northeast-1';

    /** @var int[] HTTP statuses worth one more try (throttling, transient service errors). */
    const RETRY_STATUSES = [429, 500, 502, 503, 504];

    /** @var int Tries per request, the first included. */
    const MAX_TRIES = 3;

    /** @var int Seconds to wait before the first retry; doubled each time. Tests set it to 0. */
    public int $retrydelay = 2;

    /** @var string Region, e.g. ap-northeast-1. */
    protected string $region;

    /** @var string IAM access key id. */
    protected string $accesskeyid;

    /** @var string IAM secret access key. */
    protected string $secretaccesskey;

    /** @var string Session token for temporary credentials, '' for an IAM user's keys. */
    protected string $sessiontoken;

    /**
     * Constructor.
     *
     * @param string $region AWS region.
     * @param string $accesskeyid Access key id.
     * @param string $secretaccesskey Secret access key.
     * @param string $sessiontoken Session token, only for temporary credentials.
     */
    public function __construct(string $region, string $accesskeyid, string $secretaccesskey, string $sessiontoken = '') {
        $this->region = $region;
        $this->accesskeyid = $accesskeyid;
        $this->secretaccesskey = $secretaccesskey;
        $this->sessiontoken = $sessiontoken;
    }

    /**
     * Whether the plugin settings hold usable AWS credentials.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return self::get_region_setting() !== ''
            && trim((string)get_config('videoassessment', 'awsaccesskeyid')) !== ''
            && trim((string)get_config('videoassessment', 'awssecretaccesskey')) !== '';
    }

    /**
     * The configured region, or '' when it does not look like one.
     *
     * @return string
     */
    public static function get_region_setting(): string {
        $region = get_config('videoassessment', 'awsregion');
        $region = $region === false ? self::DEFAULT_REGION : trim($region);
        return preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $region) ? $region : '';
    }

    /**
     * A client with the credentials from the plugin settings.
     *
     * @return self
     * @throws \moodle_exception When the settings are incomplete.
     */
    public static function from_config(): self {
        if (!self::is_configured()) {
            throw new \moodle_exception('aifb_error_noaws', 'videoassessment');
        }
        return new self(
            self::get_region_setting(),
            trim((string)get_config('videoassessment', 'awsaccesskeyid')),
            trim((string)get_config('videoassessment', 'awssecretaccesskey'))
        );
    }

    /**
     * The client's region.
     *
     * @return string
     */
    public function get_region(): string {
        return $this->region;
    }

    /**
     * Send a signed request, retrying throttled or transient failures.
     *
     * HTTP errors are returned, not thrown, so the caller can read the
     * service's own message; only network failures throw.
     *
     * @param string $method HTTP method.
     * @param string $service Signing name: bedrock, transcribe or s3.
     * @param string $host Endpoint host.
     * @param string $path Path, not yet URL-encoded (Bedrock model ids contain ':').
     * @param string $body Request body.
     * @param array $headers Extra headers, e.g. content-type and x-amz-target.
     * @param int $timeout Seconds before giving up.
     * @param string $errorcode videoassessment string thrown on a network failure.
     * @return ResponseInterface
     * @throws \moodle_exception On a network failure.
     */
    public function send(
        string $method,
        string $service,
        string $host,
        string $path,
        string $body,
        array $headers,
        int $timeout,
        string $errorcode
    ): ResponseInterface {
        $encodedpath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = 'https://' . $host . $encodedpath;
        $client = \core\di::get(http_client::class);

        for ($try = 1;; $try++) {
            $signed = $this->sign($method, $service, $host, $encodedpath, $body, $headers, time());
            try {
                $response = $client->request($method, $url, [
                    RequestOptions::HEADERS => $signed,
                    RequestOptions::BODY => $body,
                    RequestOptions::TIMEOUT => $timeout,
                    RequestOptions::HTTP_ERRORS => false,
                ]);
            } catch (GuzzleException $e) {
                throw new \moodle_exception($errorcode, 'videoassessment', '', null, $e->getMessage());
            }
            if ($try >= self::MAX_TRIES || !in_array($response->getStatusCode(), self::RETRY_STATUSES, true)) {
                return $response;
            }
            if ($this->retrydelay > 0) {
                sleep($this->retrydelay * (2 ** ($try - 1)));
            }
        }
    }

    /**
     * Headers for one request, Authorization included (Signature Version 4).
     *
     * @param string $method HTTP method.
     * @param string $service Signing name.
     * @param string $host Endpoint host.
     * @param string $encodedpath Path as sent on the wire (each segment URL-encoded once).
     * @param string $body Request body.
     * @param array $headers Extra headers to send and sign.
     * @param int $time Unix time of the request.
     * @return array Header name => value.
     */
    public function sign(
        string $method,
        string $service,
        string $host,
        string $encodedpath,
        string $body,
        array $headers,
        int $time
    ): array {
        $amzdate = gmdate('Ymd\THis\Z', $time);
        $date = substr($amzdate, 0, 8);
        $payloadhash = hash('sha256', $body);

        $headers = array_change_key_case($headers, CASE_LOWER);
        $headers['host'] = $host;
        $headers['x-amz-date'] = $amzdate;
        $headers['x-amz-content-sha256'] = $payloadhash;
        if ($this->sessiontoken !== '') {
            $headers['x-amz-security-token'] = $this->sessiontoken;
        }
        ksort($headers);

        $canonicalheaders = '';
        foreach ($headers as $name => $value) {
            $canonicalheaders .= $name . ':' . preg_replace('/\s+/', ' ', trim((string)$value)) . "\n";
        }
        $signedheaders = implode(';', array_keys($headers));

        // S3 signs the path as sent; every other service signs it encoded a second time.
        $canonicaluri = $service === 's3'
            ? $encodedpath
            : implode('/', array_map('rawurlencode', explode('/', $encodedpath)));

        $canonicalrequest = implode("\n", [
            $method,
            $canonicaluri,
            '',
            $canonicalheaders,
            $signedheaders,
            $payloadhash,
        ]);
        $scope = "{$date}/{$this->region}/{$service}/aws4_request";
        $stringtosign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzdate,
            $scope,
            hash('sha256', $canonicalrequest),
        ]);

        $key = hash_hmac('sha256', $date, 'AWS4' . $this->secretaccesskey, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', $service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        $signature = hash_hmac('sha256', $stringtosign, $key);

        unset($headers['host']);
        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->accesskeyid}/{$scope}, "
            . "SignedHeaders={$signedheaders}, Signature={$signature}";
        return $headers;
    }

    /**
     * The service's error text from a failed response, for the technical detail.
     *
     * Bedrock and Transcribe answer in JSON ("message" or "Message", with
     * "__type" on Transcribe), S3 in XML.
     *
     * @param ResponseInterface $response The failed response.
     * @return string Status code, error type and message.
     */
    public static function error_detail(ResponseInterface $response): string {
        $body = (string)$response->getBody();
        $type = '';
        $message = '';
        $json = json_decode($body, true);
        if (is_array($json)) {
            $type = (string)($json['__type'] ?? '');
            $message = (string)($json['message'] ?? $json['Message'] ?? '');
        } else if (preg_match('~<Code>(.*?)</Code>.*?<Message>(.*?)</Message>~s', $body, $m)) {
            [, $type, $message] = $m;
        }
        $type = preg_replace('/^.*#/', '', $type);
        $parts = array_filter([(string)$response->getStatusCode(), $type, $message ?: $response->getReasonPhrase()]);
        return implode(' ', $parts);
    }

    /**
     * The error type of a failed JSON response (e.g. ConflictException).
     *
     * @param ResponseInterface $response The failed response.
     * @return string
     */
    public static function error_type(ResponseInterface $response): string {
        $json = json_decode((string)$response->getBody(), true);
        return is_array($json) ? preg_replace('/^.*#/', '', (string)($json['__type'] ?? '')) : '';
    }
}
