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

namespace aiprovider_awsbedrock;

use aiprovider_awsbedrock\process_generate_text;
use Aws\Result;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;

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
     * Test query_ai_api for a successful call.
     */
    public function test_process(): void {
        $this->resetAfterTest();
        set_config('apikey', '123', 'aiprovider_awsbedrock');
        set_config('apisecret', '456', 'aiprovider_awsbedrock');
        set_config('action_generate_text_region', 'ap-southeast-2', 'aiprovider_awsbedrock');
        set_config('action_generate_text_model', 'amazon.titan-text-express-v1', 'aiprovider_awsbedrock');

        $provider = new \aiprovider_awsbedrock\provider();
        $processor = new process_generate_text($provider, $this->action);
        //$result = $processor->process();

    }

}
