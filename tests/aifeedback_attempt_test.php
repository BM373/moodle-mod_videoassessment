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
 * Tests for the AI speech feedback attempt life cycle.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

use core_ai\aiactions\generate_text;
use core_ai\aiactions\responses\response_generate_text;
use GuzzleHttp\Psr7\Response;
use mod_videoassessment\aifeedback\attempt_manager;
use mod_videoassessment\aifeedback\transcriber;

/**
 * Upload, transcription, analysis, failure, retry and clean-up of attempts.
 *
 * The transcription service is replaced by Moodle's mocked HTTP client and
 * the AI subsystem by a mock manager, so no request leaves the test.
 *
 * @covers \mod_videoassessment\aifeedback\attempt_manager
 * @covers \mod_videoassessment\aifeedback\transcriber
 * @covers \mod_videoassessment\aifeedback\analyser
 * @covers \mod_videoassessment\task\process_ai_feedback
 */
final class aifeedback_attempt_test extends \advanced_testcase {
    /** @var string AI answer used by the tests. */
    const AI_ANSWER = '{"summary": "Clear topic.", '
        . '"strengths": [{"quote": "today I talk about space debris", "comment": "You state the topic at once."}], '
        . '"improvements": [{"quote": "very very big", "comment": "Repetition.", "suggestion": "enormous"}], '
        . '"nextstep": "Add a conclusion."}';

    /**
     * Activity with AI feedback on, and one learner.
     *
     * @return array{manager: attempt_manager, va: \stdClass, context: \context_module, student: \stdClass}
     */
    private function setup_activity(): array {
        $this->resetAfterTest();
        set_config('aitranscribeapikey', 'test-key', 'videoassessment');
        $course = $this->getDataGenerator()->create_course();
        $va = $this->getDataGenerator()->create_module('videoassessment', [
            'course' => $course->id,
            'aifeedback' => 1,
            'aifeedbackinstructions' => 'Talk about a space problem.',
            'aifeedbackkeywords' => "firstly\nin conclusion",
            'aifeedbacklang' => 'en',
            'aitranscribelang' => 'en',
        ]);
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $va = $GLOBALS['DB']->get_record('videoassessment', ['id' => $va->id]);
        return [
            'manager' => new attempt_manager($va, $context),
            'va' => $va,
            'context' => $context,
            'student' => $student,
        ];
    }

    /**
     * A small fake recording on disk.
     *
     * @return string Path.
     */
    private function audio_file(): string {
        $path = make_request_directory() . '/recording.webm';
        file_put_contents($path, str_repeat("\x1a\x45\xdf\xa3", 64));
        return $path;
    }

    /**
     * Replace the AI subsystem with a mock that answers once per call.
     *
     * @param array $answers Generated texts, or null for a provider error.
     * @return \ArrayObject Prompts the mock received, filled in as calls happen.
     */
    private function mock_ai(array $answers): \ArrayObject {
        $prompts = new \ArrayObject();
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->expects($this->exactly(count($answers)))
            ->method('process_action')
            ->willReturnCallback(function ($action) use (&$answers, $prompts) {
                $this->assertInstanceOf(generate_text::class, $action);
                $prompts[] = $action->get_configuration('prompttext');
                $answer = array_shift($answers);
                if ($answer === null) {
                    return new response_generate_text(false, 429, 'Rate limit reached');
                }
                $response = new response_generate_text(true);
                $response->set_response_data(['generatedcontent' => $answer]);
                return $response;
            });
        \core\di::set(\core_ai\manager::class, $manager);
        return $prompts;
    }

    /**
     * A recording is stored, numbered and queued for the safety-net task.
     */
    public function test_create_attempt(): void {
        ['manager' => $manager, 'student' => $student, 'context' => $context] = $this->setup_activity();

        $first = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm', 42);
        $second = $manager->create_attempt($student->id, $this->audio_file(), 'take2.M4A');

        $this->assertSame(1, (int)$first->attempt);
        $this->assertSame(2, (int)$second->attempt);
        $this->assertSame(42, (int)$first->duration);
        $this->assertSame(attempt_manager::STATUS_QUEUED, (int)$first->status);
        $this->assertSame('attempt2.m4a', $manager->get_audio_file($second)->get_filename());
        $this->assertSame((int)$context->id, (int)$manager->get_audio_file($first)->get_contextid());
        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(task\process_ai_feedback::class));
        $this->assertSame([(int)$second->id, (int)$first->id], array_map(
            fn($a) => (int)$a->id,
            $manager->get_user_attempts($student->id)
        ));
    }

    /**
     * Files that are not audio the API accepts are refused before anything is stored.
     */
    public function test_create_attempt_rejects_format(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        try {
            $manager->create_attempt($student->id, $this->audio_file(), 'notes.pdf');
            $this->fail('A PDF must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('aifb_error_format', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE));
    }

    /**
     * Happy path: transcribed, analysed, keywords checked, request well formed.
     */
    public function test_process_attempt(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm');

        $history = [];
        ['mock' => $http] = $this->get_mocked_http_client($history);
        $http->append(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'text' => 'Firstly, today I talk about space debris. It is a very very big problem.',
        ])));
        $prompts = $this->mock_ai([self::AI_ANSWER]);

        $result = attempt_manager::process_attempt($attempt->id);

        $this->assertSame(attempt_manager::STATUS_DONE, (int)$result->status);
        $stored = $DB->get_record(attempt_manager::TABLE, ['id' => $attempt->id]);
        $this->assertSame(attempt_manager::STATUS_DONE, (int)$stored->status);
        $this->assertStringStartsWith('Firstly, today I talk', $stored->transcript);
        $this->assertGreaterThan(0, (int)$stored->timeprocessed);
        $feedback = json_decode($stored->feedback, true);
        $this->assertSame('Clear topic.', $feedback['summary']);
        $this->assertSame([
            ['keyword' => 'firstly', 'used' => true],
            ['keyword' => 'in conclusion', 'used' => false],
        ], $feedback['keywords']);

        // One multipart request to the default endpoint with the plugin's key and language.
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame(transcriber::DEFAULT_ENDPOINT, (string)$request->getUri());
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $body = (string)$request->getBody();
        $this->assertStringContainsString('name="model"', $body);
        $this->assertStringContainsString('whisper-1', $body);
        $this->assertStringContainsString('filename="attempt1.webm"', $body);
        $this->assertMatchesRegularExpression('/name="language"\r\n.*?\r\n\r\nen\r\n/s', $body);

        // The transcript reached the AI inside the prompt, with the instructions.
        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Talk about a space problem.', $prompts[0]);
        $this->assertStringContainsString('It is a very very big problem.', $prompts[0]);

        // Processing again does nothing (no more HTTP or AI calls are queued).
        $this->assertSame(attempt_manager::STATUS_DONE, (int)attempt_manager::process_attempt($attempt->id)->status);
    }

    /**
     * A transcription error fails the attempt; learners see no technical detail.
     */
    public function test_process_attempt_transcription_error(): void {
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm');

        ['mock' => $http] = $this->get_mocked_http_client();
        $http->append(new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => 'Incorrect API key provided'],
        ])));
        $this->mock_ai([]);

        $result = attempt_manager::process_attempt($attempt->id);

        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$result->status);
        $learner = attempt_manager::error_text($result, false);
        $teacher = attempt_manager::error_text($result, true);
        $this->assertSame(get_string('aifb_error_transcribe', 'videoassessment'), $learner);
        $this->assertStringContainsString('401 Incorrect API key provided', $teacher);
    }

    /**
     * After an AI error, a retry reuses the transcript and only asks the AI again.
     */
    public function test_retry_after_ai_error(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm');

        $history = [];
        ['mock' => $http] = $this->get_mocked_http_client($history);
        $http->append(new Response(200, [], json_encode(['text' => 'Today I talk about space debris.'])));
        $this->mock_ai([null, self::AI_ANSWER]);

        $failed = attempt_manager::process_attempt($attempt->id);
        $this->assertSame(attempt_manager::STATUS_FAILED, (int)$failed->status);
        $this->assertStringContainsString('Rate limit reached', attempt_manager::error_text($failed, true));

        attempt_manager::retry($failed);
        $status = (int)$DB->get_field(attempt_manager::TABLE, 'status', ['id' => $attempt->id]);
        $this->assertSame(attempt_manager::STATUS_QUEUED, $status);

        $done = attempt_manager::process_attempt($attempt->id);
        $this->assertSame(attempt_manager::STATUS_DONE, (int)$done->status);
        $this->assertCount(1, $history, 'The recording must not be transcribed twice');
    }

    /**
     * The ad-hoc task finishes an attempt the browser left behind.
     */
    public function test_task_processes_queued_attempt(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student] = $this->setup_activity();
        $attempt = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm');

        ['mock' => $http] = $this->get_mocked_http_client();
        $http->append(new Response(200, [], json_encode(['text' => 'Today I talk about space debris.'])));
        $this->mock_ai([self::AI_ANSWER]);

        $this->runAdhocTasks(task\process_ai_feedback::class);
        $status = (int)$DB->get_field(attempt_manager::TABLE, 'status', ['id' => $attempt->id]);
        $this->assertSame(attempt_manager::STATUS_DONE, $status);
    }

    /**
     * The OpenAI provider's key is reused only for OpenAI's own endpoint.
     */
    public function test_api_key_fallback(): void {
        $this->resetAfterTest();
        set_config('aitranscribeapikey', '', 'videoassessment');
        set_config('apikey', 'provider-key', 'aiprovider_openai');

        $this->assertSame('provider-key', transcriber::get_api_key());
        $this->assertTrue(transcriber::is_configured());

        set_config('aitranscribeendpoint', 'https://whisper.example.edu/v1/audio/transcriptions', 'videoassessment');
        $this->assertSame('', transcriber::get_api_key());
        $this->assertFalse(transcriber::is_configured());

        set_config('aitranscribeapikey', 'own-key', 'videoassessment');
        $this->assertSame('own-key', transcriber::get_api_key());
    }

    /**
     * Viewing marks the feedback read once; deleting removes rows and recordings.
     */
    public function test_viewed_and_delete(): void {
        global $DB;
        ['manager' => $manager, 'student' => $student, 'va' => $va, 'context' => $context] = $this->setup_activity();
        $other = $this->getDataGenerator()->create_user();
        $attempt = $manager->create_attempt($student->id, $this->audio_file(), 'recording.webm');
        $manager->create_attempt($other->id, $this->audio_file(), 'recording.webm');

        $DB->set_field(attempt_manager::TABLE, 'status', attempt_manager::STATUS_DONE, ['id' => $attempt->id]);
        $attempt = $manager->get_attempt($attempt->id);
        $manager->mark_viewed($attempt);
        $first = (int)$DB->get_field(attempt_manager::TABLE, 'timeviewed', ['id' => $attempt->id]);
        $this->assertGreaterThan(0, $first);
        $summary = $manager->get_summary();
        $this->assertSame(1, (int)$summary[$student->id]->viewed);

        attempt_manager::delete_attempts($va->id, $context->id, [$student->id]);
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE, ['userid' => $student->id]));
        $this->assertSame(1, $DB->count_records(attempt_manager::TABLE, ['userid' => $other->id]));
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_videoassessment',
            attempt_manager::FILEAREA,
            false,
            'id',
            false
        );
        $this->assertCount(1, $files);

        require_once(__DIR__ . '/../lib.php');
        videoassessment_delete_instance($va->id);
        $this->assertSame(0, $DB->count_records(attempt_manager::TABLE));
    }
}
