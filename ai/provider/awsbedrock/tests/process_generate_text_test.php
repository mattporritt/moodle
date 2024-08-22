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

use aiprovider_awsbedrock\process_generate_text;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Stream;

/**
 * Test Generate text provider class for AWS Bedrock provider methods.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\provider\awsbedrock
 */
final class process_generate_text_test extends \advanced_testcase {
    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->provider = new \aiprovider_awsbedrock\provider();
        $contextid = 1;
        $userid = 1;
        $prompttext = 'This is a test prompt';
        $this->action = new \core_ai\aiactions\generate_text(
                contextid: $contextid,
                userid: $userid,
                prompttext: $prompttext
        );
    }

    /**
     * Test create_request_object
     */
    public function test_create_request(): void {
        $prompttext = 'This is a test prompt';
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request');
        $request = $method->invoke($processor, $this->action);

        $this->assertEquals($prompttext, json_decode($request['body'])->inputText);
    }

    /**
     * Test the API error response handler method.
     *
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => new Result(['@metadata' => ['statusCode' => 500]]),
            503 => new Result(['@metadata' => ['statusCode' => 503]]),
        ];

        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => $response) {
            $result = $method->invoke($processor, $status, $response);
            $this->assertEquals($status, $result['errorcode']);
            if ($status == 500) {
                $this->assertEquals('Internal server error.', $result['errormessage']);
            } else if ($status == 503) {
                $this->assertEquals('Service unavailable.', $result['errormessage']);
            }
        }
    }

    /**
     * Test the API success response handler method.
     *
     */
    public function test_handle_api_success(): void {
        // Create a mock of the GuzzleHttp\Psr7\Stream class
        $streammock = $this->createMock(Stream::class);
        $streammock->method('getContents')
            ->willReturn('{"inputTextTokenCount":5,"results":[{"tokenCount":7,"outputText":"This is a test prompt","completionReason":"FINISH"}]}');

        $response = new Result([
            '@metadata' => ['statusCode' => 200],
            'body' => $streammock
        ]);

        // We're testing a private method, so we need to setup reflector magic.
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('This is a test prompt', $result['generatedcontent']);
        $this->assertEquals('FINISH', $result['finishreason']);
        $this->assertEquals(5, $result['prompttokens']);
        $this->assertEquals(7, $result['completiontokens']);
    }

    /**
     * Test query_ai_api for a successful call.
     */
    public function test_query_ai_api_success(): void {
        // Create a mock of the GuzzleHttp\Psr7\Stream class
        $streammock = $this->createMock(Stream::class);
        $streammock->method('getContents')
                ->willReturn('{"inputTextTokenCount":5,"results":[{"tokenCount":7,"outputText":"This is a test prompt","completionReason":"FINISH"}]}');

        $response = new Result([
                '@metadata' => ['statusCode' => 200],
                'body' => $streammock
        ]);

        $client = $this->createMock(BedrockRuntimeClient::class);
        // Using a callback to simulate the dynamic invocation
        $client->method('__call')
                ->with('invokeModel', $this->anything())
                ->willReturn($response);

        $body = new \stdClass();
        $body->inputText = 'This is a test prompt';
        $request = [
                'ContentType' => 'application/json',
                'Accept' => 'application/json',
                'modelId' => 'amazon.titan-text-express-v1',
                'body' => json_encode($body),
        ];

        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor, $client, $request);

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('This is a test prompt', $result['generatedcontent']);
        $this->assertEquals('FINISH', $result['finishreason']);
        $this->assertEquals(5, $result['prompttokens']);
        $this->assertEquals(7, $result['completiontokens']);


    }

    /**
     * Test prepare_response success.
     */
    public function test_prepare_response_success(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => true,
            'generatedcontent' => 'This is a test prompt',
            'finishreason' => 'FINISH',
            'prompttokens' => 5,
            'completiontokens' => 7,
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertEquals($response['success'], $result->get_success());
        $this->assertEquals($response['generatedcontent'], $result->get_response()['generatedcontent']);
    }

    /**
     * Test prepare_response error.
     */
    public function test_prepare_response_error(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
                'success' => false,
                'errorcode' => 500,
                'errormessage' => 'Internal server error.',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertEquals($response['errorcode'], $result->get_errorcode());
        $this->assertEquals($response['errormessage'], $result->get_errormessage());
    }

}
