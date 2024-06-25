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

use GuzzleHttp\Psr7\Response;

/**
 * Test base OpenAI provider methods.
 *
 * @package    aiprovier_openai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\provider\openai
 */
class provider_test extends \advanced_testcase {
    /** @var string A successful response in JSON format. */
    protected string $responsebodyjson;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        // Load a response body from a file.
        $this->responsebodyjson = file_get_contents(__DIR__ . '/fixtures/request_success.json');
    }

    /**
     * Test generate_userid.
     */
    public function test_generate_userid(): void {
        $provider = new \aiprovider_openai\provider();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'generate_userid');

        $userid = $method->invoke($provider);

        //assert that the generated userid is a string of proper length.
        $this->assertIsString($userid);
        $this->assertEquals(64, strlen($userid));
    }

    /**
     * Test calculate_size.
     */
    public function test_calculate_size(): void {
        $provider = new \aiprovider_openai\provider();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'calculate_size');

        $ratio = 'square';
        $size = $method->invoke($provider, $ratio);
        $this->assertEquals('1024x1024', $size);

        $ratio = 'portrait';
        $size = $method->invoke($provider, $ratio);
        $this->assertEquals('1024x1792', $size);

        $ratio = 'landscape';
        $size = $method->invoke($provider, $ratio);
        $this->assertEquals('1792x1024', $size);
    }

    /**
     * Test create_http_client.
     */
    public function test_create_http_client(): void {
        $provider = new \aiprovider_openai\provider();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'create_http_client');

        $client = $method->invoke($provider);

        $this->assertInstanceOf(\core\http_client::class, $client);
    }

    /**
     * Test create_request_object
     */
    public function test_create_request_object(): void {
        $action = new \core_ai\actions\generate_image();
        $contextid = 1;
        $prompt = 'This is a test prompt';
        $aspectratio = 'square';
        $quality = 'hd';
        $style = 'vivid';
        $action->configure($contextid, $prompt, $aspectratio, $quality, $style);

        $provider = new \aiprovider_openai\provider();
        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'create_request_object');
        $request = $method->invoke($provider, $action);

        $this->assertEquals($prompt, $request->prompt);
        $this->assertEquals('dall-e-3', $request->model);
        $this->assertEquals('1', $request->n);
        $this->assertEquals($quality, $request->quality);
        $this->assertEquals('url', $request->response_format);
        $this->assertEquals('1024x1024', $request->size);
    }

    /**
     * Test the API error response handler method.
     *
     */
    public function test_handle_api_error() {
        $responses = [
                500 => new Response(500, ['Content-Type' => 'application/json']),
                503 => new Response(503, ['Content-Type' => 'application/json']),
                401 => new Response(401, ['Content-Type' => 'application/json'],
                        '{"error": {"message": "Invalid Authentication"}}'),
                404 => new Response(404, ['Content-Type' => 'application/json'],
                        '{"error": {"message": "You must be a member of an organization to use the API"}}'),
                429 => new Response(429, ['Content-Type' => 'application/json'],
                        '{"error": {"message": "Rate limit reached for requests"}}'),
        ];

        $provider = new \aiprovider_openai\provider();
        $method = new ReflectionMethod($provider, 'handle_api_error');

        foreach($responses as $status => $response) {
            $result = $method->invoke($provider, $status, $response);
            $this->assertEquals($status, $result['errorcode']);
            if ($status == 500) {
                $this->assertEquals('Internal server error.', $result['errormessage']);
            } else if ($status == 503) {
                $this->assertEquals('Service unavailable.', $result['errormessage']);
            } else {
                $this->assertStringContainsString($response->getBody()->getContents(), $result['errormessage']);
            }
        }
    }

    /**
     * Test the API success response handler method.
     *
     */
    public function test_handle_api_success() {
        $response = new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->responsebodyjson
        );

        // We're testing a private method, so we need to setup reflector magic.
        $provider = new \aiprovider_openai\provider();
        $method = new ReflectionMethod($provider, 'handle_api_success');

        $result = $method->invoke($provider, $response);

        $this->assertEquals('1719140500', $result['body']['created']);
        $this->stringContains('An image that represents the concept of a \'test\'.', $result['body']['revised_prompt']);
        $this->stringContains('oaidalleapiprodscus.blob.core.windows.net', $result['body']['url']);
    }

    /**
     * Test query_ai_api for a successful call.
     */
    public function test_query_ai_api_success(): void {
        // Mock the http client to return a successful response.
        $response = new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->responsebodyjson
        );
        $client = $this->createMock(\core\http_client::class);
        $client->method('request')->willReturn($response);

        // Create a request object.
        $requestobj = new \stdClass();
        $requestobj->prompt = 'generate a test image';
        $requestobj->model = 'awesome-ai-3';
        $requestobj->n = '3';
        $requestobj->quality = 'hd';
        $requestobj->response_format = 'url;';
        $requestobj->size = '1024x1024';
        $requestobj->style = 'vivid';
        $requestobj->user = 't3464h89dftjltestudfaser';

        $provider = new \aiprovider_openai\provider();
        $method = new ReflectionMethod($provider, 'query_ai_api');
        $result = $method->invoke($provider, $client, $requestobj);

        $this->assertEquals('1719140500', $result['body']['created']);
        $this->stringContains('An image that represents the concept of a \'test\'.', $result['body']['revised_prompt']);
        $this->stringContains('oaidalleapiprodscus.blob.core.windows.net', $result['body']['url']);
    }

    /**
     * Test prepare_response success.
     */
    public function test_prepare_response_success(): void {
        $provider = new \aiprovider_openai\provider();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'prepare_response');

        $response = [
                'success' => true,
                'body' => [
                        'created' => 1719140500,
                        'revised_prompt' => 'An image that represents the concept of a \'test\'.',
                        'url' => 'oaidalleapiprodscus.blob.core.windows.net',
                    ]
                ];

        $result = $method->invoke($provider, $response);

        $this->assertInstanceOf(\core_ai\actions\action_response::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['body'], $result->get_body());
    }

    /**
     * Test prepare_response error.
     */
    public function test_prepare_response_error(): void {
        $provider = new \aiprovider_openai\provider();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($provider, 'prepare_response');

        $response = [
                'success' => false,
                'errorcode' => 500,
                'errormessage' => 'Internal server error.'
        ];

        $result = $method->invoke($provider, $response);

        $this->assertInstanceOf(\core_ai\actions\action_response::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['errorcode'], $result->get_errorcode());
        $this->assertEquals($response['errormessage'], $result->get_errormessage());
    }

    /**
     * test process_action_generate_image.
     */
    public function test_process_action_generate_image():void {
        $this->resetAfterTest();
        $action = new \core_ai\actions\generate_image();
        $contextid = 1;
        $prompt = 'This is a test prompt';
        $aspectratio = 'square';
        $quality = 'hd';
        $style = 'vivid';
        $action->configure($contextid, $prompt, $aspectratio, $quality, $style);

        set_config('apikey', '', 'aiprovider_openai');
        set_config('orgid', '', 'aiprovider_openai');

        $provider = new \aiprovider_openai\provider();
        //$result = $provider->process_action_generate_image($action);

    }

}
