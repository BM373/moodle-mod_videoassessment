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
 * Tests for the Amazon Web Services back ends of the AI speech feedback.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use GuzzleHttp\Psr7\Response;
use mod_videoassessment\aifeedback\analyser;
use mod_videoassessment\aifeedback\attempt_manager;
use mod_videoassessment\aifeedback\aws_client;
use mod_videoassessment\aifeedback\aws_transcriber;
use mod_videoassessment\aifeedback\transcriber;

/**
 * Signing, Bedrock feedback and the Amazon Transcribe job life cycle.
 *
 * AWS is replaced by Moodle's mocked HTTP client; the requests it records
 * are checked against what the services expect.
 *
 * @covers \mod_videoassessment\aifeedback\aws_client
 * @covers \mod_videoassessment\aifeedback\aws_transcriber
 * @covers \mod_videoassessment\aifeedback\analyser
 * @covers \mod_videoassessment\aifeedback\transcriber
 * @covers \mod_videoassessment\aifeedback\attempt_manager
 */
final class aifeedback_aws_test extends \advanced_testcase {
    /** @var string Example secret from the AWS documentation. */
    const SECRET = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';

    /** @var string Feedback JSON returned by the mocked model. */
    const AI_ANSWER = '{"summary": "Clear topic.", '
        . '"strengths": [{"quote": "our team has built", "comment": "Good."}], '
        . '"improvements": [{"quote": "many data", "comment": "Data is uncountable.", "suggestion": "a lot of data"}], '
        . '"nextstep": "Add a conclusion."}';

    /** @var string Transcript returned by the mocked Transcribe job. */
    const TRANSCRIPT = 'Today I talk about the satellite our team has built. It can send many data.';

    /**
     * Activity with AI feedback on, AWS configured for both steps, and one learner.
     *
     * @return array{manager: attempt_manager, va: \stdClass, student: \stdClass}
     */
    private function setup_activity(): array {
        $this->resetAfterTest();
        set_config('aifeedbackengine', analyser::ENGINE_BEDROCK, 'videoassessment');
        set_config('aitranscribeservice', transcriber::SERVICE_AWS, 'videoassessment');
        set_config('awsregion', 'ap-northeast-1', 'videoassessment');
        set_config('awsaccesskeyid', 'AKIDEXAMPLE', 'videoassessment');
        set_config('awssecretaccesskey', self::SECRET, 'videoassessment');
        set_config('awss3bucket', 'my-bucket', 'videoassessment');

        $course = $this->getDataGenerator()->create_course();
        $va = $this->getDataGenerator()->create_module('videoassessment', [
            'course' => $course->id,
            'aifeedback' => 1,
            'aifeedbackinstructions' => 'Introduce your satellite project.',
            'aifeedbacklang' => 'en',
            'aitranscribelang' => 'en',
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $va = $GLOBALS['DB']->get_record('videoassessment', ['id' => $va->id]);
        return [
            'manager' => new attempt_manager($va, \context_module::instance($cm->id)),
            'va' => $va,
            'student' => $student,
        ];
    }

    /**
     * Store a small fake recording as a new attempt.
     *
     * @param attempt_manager $manager The activity's manager.
     * @param int $userid The learner.
     * @return \stdClass The attempt.
     */
    private function create_attempt(attempt_manager $manager, int $userid): \stdClass {
        $path = make_request_directory() . '/recording.webm';
        file_put_contents($path, str_repeat("\x1a\x45\xdf\xa3", 64));
        return $manager->create_attempt($userid, $path, 'recording.webm', 15);
    }

    /**
     * Install a Transcribe client that does not sleep.
     *
     * @param int $maxwait Seconds a try may wait for the job.
     * @return void
     */
    private function fast_transcriber(int $maxwait = 60): void {
        $transcriber = new aws_transcriber();
        $transcriber->pollinterval = 0;
        $transcriber->maxwait = $maxwait;
        \core\di::set(aws_transcriber::class, $transcriber);
    }

    /**
     * A Transcribe API answer.
     *
     * @param string $status Job status.
     * @param array $extra Extra job fields.
     * @return Response
     */
    private static function job(string $status, array $extra = []): Response {
        return new Response(200, [], json_encode(['TranscriptionJob' => ['TranscriptionJobStatus' => $status] + $extra]));
    }

    /**
     * A Bedrock Converse answer, with the reasoning block newer Claude models send first.
     *
     * @param string $text Generated text.
     * @return Response
     */
    private static function converse(string $text): Response {
        return new Response(200, [], json_encode([
            'output' => ['message' => ['role' => 'assistant', 'content' => [
                ['reasoningContent' => ['reasoningText' => ['text' => 'Let me think.', 'signature' => 'x']]],
                ['text' => $text],
            ]]],
            'stopReason' => 'end_turn',
        ]));
    }

    /**
     * Method, host, path and Transcribe operation of each recorded request.
     *
     * @param array $history Guzzle history.
     * @return string[]
     */
    private static function calls(array $history): array {
        return array_map(function ($entry) {
            $request = $entry['request'];
            $target = $request->getHeaderLine('x-amz-target');
            return $request->getMethod() . ' ' . $request->getUri()->getHost() . $request->getUri()->getPath()
                . ($target !== '' ? ' ' . $target : '');
        }, $history);
    }

    /**
     * Signatures match the ones botocore computes for the same requests.
     *
     * The expected values come from botocore's SigV4Auth and S3SigV4Auth
     * with the clock fixed at 2026-10-26 04:00:00 UTC. The Bedrock case has a
     * ':' in the model id, which only non-S3 services encode a second time.
     */
    public function test_signature_matches_botocore(): void {
        $client = new aws_client('ap-northeast-1', 'AKIDEXAMPLE', self::SECRET);
        $time = gmmktime(4, 0, 0, 10, 26, 2026);

        $bedrock = $client->sign(
            'POST',
            'bedrock',
            'bedrock-runtime.ap-northeast-1.amazonaws.com',
            '/model/jp.anthropic.claude-sonnet-4-5-20250929-v1%3A0/converse',
            '{"messages":[]}',
            ['Content-Type' => 'application/json'],
            $time
        );
        $this->assertSame('20261026T040000Z', $bedrock['x-amz-date']);
        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20261026/ap-northeast-1/bedrock/aws4_request, '
                . 'SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, '
                . 'Signature=809599fd6137b5c598e138fc6bc05d694a9ca62351d86a7f2c2f739175930753',
            $bedrock['Authorization']
        );

        $s3 = $client->sign(
            'PUT',
            's3',
            'my-bucket.s3.ap-northeast-1.amazonaws.com',
            '/videoassessment-aifeedback/vam-1234abcd-7-0123456789ab.webm',
            'audio-bytes',
            ['content-type' => 'audio/webm'],
            $time
        );
        $this->assertStringEndsWith(
            'Signature=f4454aaf2b8e62fc9325cdbde522fdf83430876cf16c942887555972864c6b49',
            $s3['Authorization']
        );

        $temporary = new aws_client('ap-northeast-1', 'ASIAEXAMPLE', self::SECRET, 'session-token');
        $headers = $temporary->sign('GET', 's3', 'my-bucket.s3.ap-northeast-1.amazonaws.com', '/x', '', [], $time);
        $this->assertSame('session-token', $headers['x-amz-security-token']);
        $this->assertStringContainsString('x-amz-security-token', $headers['Authorization']);
    }

    /**
     * Bedrock feedback: a transient error is retried, the reasoning block is
     * skipped and no temperature is sent.
     */
    public function test_bedrock_feedback(): void {
        ['va' => $va, 'student' => $student] = $this->setup_activity();
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(503, [], '{"message": "Bedrock is unable to process your request."}'));
        $mock->append(self::converse(self::AI_ANSWER));

        $client = new aws_client('ap-northeast-1', 'AKIDEXAMPLE', self::SECRET);
        $client->retrydelay = 0;
        $context = \context_module::instance(get_coursemodule_from_instance('videoassessment', $va->id)->id);
        $feedback = (new analyser($client))->analyse($va, self::TRANSCRIPT, $context->id, $student->id);

        $this->assertSame('Clear topic.', $feedback['summary']);
        $this->assertSame('many data', $feedback['improvements'][0]['quote']);
        $this->assertCount(2, $history);
        $request = $history[1]['request'];
        $this->assertSame(
            'https://bedrock-runtime.ap-northeast-1.amazonaws.com/model/jp.anthropic.claude-haiku-5-5/converse',
            (string)$request->getUri()
        );
        $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $request->getHeaderLine('Authorization'));
        $body = json_decode((string)$request->getBody(), true);
        $this->assertSame(['maxTokens' => analyser::MAX_TOKENS], $body['inferenceConfig']);
        $this->assertStringContainsString(self::TRANSCRIPT, $body['messages'][0]['content'][0]['text']);
        $this->assertTrue(analyser::is_available());
        $this->assertTrue(attempt_manager::site_ready());
    }

    /**
     * A refusal from Bedrock fails the attempt; only teachers see AWS's message.
     */
    public function test_bedrock_refusal(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $this->create_attempt($manager, $student->id);
        $DB->set_field(attempt_manager::TABLE, 'transcript', self::TRANSCRIPT, ['id' => $attempt->id]);
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(403, [], json_encode([
            'message' => 'You don\'t have access to the model with the specified model ID.',
        ])));

        $result = attempt_manager::process_attempt($attempt->id);

        $this->assertCount(1, $history, 'A 403 is not retried and nothing is transcribed again.');
        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$result->status);
        $this->assertSame(get_string('aifb_error_analyse', 'videoassessment'), attempt_manager::error_text($result, false));
        $this->assertStringContainsString('403 You don\'t have access', attempt_manager::error_text($result, true));
    }

    /**
     * Upload, job, transcript, clean-up and feedback in one run.
     */
    public function test_transcribe_life_cycle(): void {
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $this->create_attempt($manager, $student->id);
        $file = $manager->get_audio_file($attempt);
        $job = aws_transcriber::job_name($file);
        $this->fast_transcriber();
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200));
        $mock->append(self::job('IN_PROGRESS'));
        $mock->append(self::job('COMPLETED'));
        $mock->append(new Response(200, [], json_encode(['results' => ['transcripts' => [['transcript' => self::TRANSCRIPT]]]])));
        $mock->append(new Response(204));
        $mock->append(new Response(204));
        $mock->append(new Response(200, [], '{}'));
        $mock->append(self::converse(self::AI_ANSWER));

        $result = attempt_manager::process_attempt($attempt->id);

        $this->assertSame(attempt_manager::STATUS_DONE, (int)$result->status, (string)$result->errormessage);
        $this->assertSame(self::TRANSCRIPT, $result->transcript);
        $s3 = 'my-bucket.s3.ap-northeast-1.amazonaws.com/videoassessment-aifeedback/' . $job;
        $transcribe = 'transcribe.ap-northeast-1.amazonaws.com/';
        $this->assertSame([
            "PUT {$s3}.webm",
            "POST {$transcribe} Transcribe.StartTranscriptionJob",
            "POST {$transcribe} Transcribe.GetTranscriptionJob",
            "GET {$s3}.json",
            "DELETE {$s3}.webm",
            "DELETE {$s3}.json",
            "POST {$transcribe} Transcribe.DeleteTranscriptionJob",
            'POST bedrock-runtime.ap-northeast-1.amazonaws.com/model/jp.anthropic.claude-haiku-5-5/converse',
        ], self::calls($history));

        $this->assertSame($file->get_content(), (string)$history[0]['request']->getBody());
        $start = json_decode((string)$history[1]['request']->getBody(), true);
        $this->assertSame([
            'TranscriptionJobName' => $job,
            'Media' => ['MediaFileUri' => "s3://my-bucket/videoassessment-aifeedback/{$job}.webm"],
            'OutputBucketName' => 'my-bucket',
            'OutputKey' => "videoassessment-aifeedback/{$job}.json",
            'MediaFormat' => 'webm',
            'LanguageCode' => 'en-US',
        ], $start);
    }

    /**
     * A job an earlier try started is picked up instead of failing.
     */
    public function test_transcribe_resumes_existing_job(): void {
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $file = $manager->get_audio_file($this->create_attempt($manager, $student->id));
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200));
        $mock->append(new Response(400, [], json_encode([
            '__type' => 'ConflictException',
            'Message' => 'The requested job name already exists. Use a different job name.',
        ])));
        $mock->append(self::job('COMPLETED'));
        $mock->append(new Response(200, [], json_encode(['results' => ['transcripts' => [['transcript' => self::TRANSCRIPT]]]])));
        $mock->append(new Response(204));
        $mock->append(new Response(204));
        $mock->append(new Response(200, [], '{}'));

        $client = new aws_client('ap-northeast-1', 'AKIDEXAMPLE', self::SECRET);
        $transcriber = new aws_transcriber($client);
        $transcriber->pollinterval = 0;

        $this->assertSame(self::TRANSCRIPT, $transcriber->transcribe($file, ''));
        $start = json_decode((string)$history[1]['request']->getBody(), true);
        $this->assertTrue($start['IdentifyLanguage'], 'No language set: Transcribe identifies it.');
        $this->assertArrayNotHasKey('LanguageCode', $start);
        $this->assertSame('Transcribe.GetTranscriptionJob', $history[2]['request']->getHeaderLine('x-amz-target'));
    }

    /**
     * A failed job is cleaned up and reported with Transcribe's reason.
     */
    public function test_transcribe_failed_job(): void {
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $file = $manager->get_audio_file($this->create_attempt($manager, $student->id));
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200));
        $mock->append(self::job('FAILED', [
            'FailureReason' => 'The media format provided does not match the detected media format.',
        ]));
        $mock->append(new Response(204));
        $mock->append(new Response(204));
        $mock->append(new Response(200, [], '{}'));

        try {
            (new aws_transcriber(new aws_client('ap-northeast-1', 'AKIDEXAMPLE', self::SECRET)))->transcribe($file, 'en');
            $this->fail('A failed job must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('aifb_error_transcribe', $e->errorcode);
            $this->assertStringContainsString('does not match the detected media format', $e->debuginfo);
        }
        $this->assertCount(5, $history);
        $this->assertSame('Transcribe.DeleteTranscriptionJob', $history[4]['request']->getHeaderLine('x-amz-target'));
    }

    /**
     * A job still running after the wait is not a failure: the attempt is queued again.
     */
    public function test_slow_job_is_queued_again(): void {
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $this->create_attempt($manager, $student->id);
        $this->fast_transcriber(0);
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200));
        $mock->append(self::job('IN_PROGRESS'));

        $result = attempt_manager::process_attempt($attempt->id);

        $this->assertSame(attempt_manager::STATUS_QUEUED, (int)$result->status);
        $this->assertEmpty($result->errormessage);
        $tasks = \core\task\manager::get_adhoc_tasks(task\process_ai_feedback::class);
        $this->assertCount(2, $tasks, 'The task queued at upload plus the one queued for the slow job.');
        $this->assertGreaterThan(time(), max(array_map(fn($t) => $t->get_next_run_time(), $tasks)));
    }

    /**
     * Readiness depends on the chosen services and their settings.
     */
    public function test_readiness(): void {
        $this->setup_activity();
        $this->assertTrue(attempt_manager::site_ready());

        set_config('awss3bucket', 'my.bucket', 'videoassessment');
        $this->assertFalse(transcriber::is_configured(), 'Bucket names with dots are refused.');

        set_config('awss3bucket', 'my-bucket', 'videoassessment');
        set_config('awsregion', 'tokyo', 'videoassessment');
        $this->assertFalse(analyser::is_available());
        $this->assertFalse(transcriber::is_configured());

        set_config('awsregion', 'ap-northeast-1', 'videoassessment');
        set_config('awssecretaccesskey', '', 'videoassessment');
        $this->assertFalse(attempt_manager::site_ready());

        // Back to the defaults: Moodle's AI subsystem and the OpenAI-compatible endpoint.
        unset_config('aifeedbackengine', 'videoassessment');
        unset_config('aitranscribeservice', 'videoassessment');
        set_config('aitranscribeapikey', 'key', 'videoassessment');
        $this->assertSame(analyser::ENGINE_COREAI, analyser::engine());
        $this->assertTrue(transcriber::is_configured());
    }
}
