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

use aiprovider_awsbedrock\test\testcase_helper_trait;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test Generate text provider class for AWS Bedrock provider methods.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_awsbedrock\provider
 * @covers     \aiprovider_awsbedrock\process_generate_text
 * @covers     \aiprovider_awsbedrock\abstract_processor
 */
final class process_generate_text_test extends \advanced_testcase {

    use testcase_helper_trait;

    /** @var \core_ai\manager */
    private $manager;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\generate_text::class,
            actionconfig: [
                'systeminstruction' => get_string('action_generate_text_instruction', 'core_ai'),
            ],
        );
        $this->create_action();
    }

    /**
     * Create the action object.
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: $userid,
            prompttext: 'This is a test prompt',
        );
    }

    /**
     * Create a mocked Aws\Result.
     */
    private function get_mocked_aws_result(): Result {
        // Mock JSON response body.
        $mockresponsebody = json_encode([
            'results' => [
                [
                    'outputText' => 'The capital of Australia is Canberra.',
                    'completionReason' => 'FINISHED'
                ]
            ]
        ]);

        // Create a PSR-7 Stream for the response body.
        $stream = Utils::streamFor($mockresponsebody);

        // Create a mocked Aws\Result.
        return new Result([
            'body' => $stream,  // Simulate AWS SDK response body.
            'contentType' => 'application/json',
            '@metadata' => [
                'statusCode' => 200,
                'headers' => [
                    'x-amzn-requestid' => 'mock-request-id',
                    'x-amzn-bedrock-input-token-count' => '11',
                    'x-amzn-bedrock-output-token-count' => '568',
                ]
            ]
        ]);
    }

    /**
     * Mock the result from query_ai_api().
     * Returns a MockBuilder object for a processor.
     *
     * @param provider $provider The provider to use.
     * @param bool $success Whether the query was successful.
     * @return MockObject The mocked processor object.
     */
    private function get_mocked_query_ai_api_result(
        provider $provider,
        bool $success = true): MockObject {
        // Define the mock successful response from query_ai_api().
        $responsesuccess = [
            'success' => true,
            'fingerprint' => 'mock-request-id',
            'prompttokens' => '11',
            'completiontokens' => '568',
            'model' => 'amazon.titan-text-lite-v1',
            'generatedcontent' => 'The capital of Australia is Canberra.',
            'finishreason' => 'FINISHED',
        ];

        $responseerror = [
            'success' => false,
            'errorcode' => 401,
            'errormessage' => 'Invalid Authentication',
        ];

        // Create a partial mock of `process_generate_text`, only mocking `query_ai_api()`.
        $processor = $this->getMockBuilder(process_generate_text::class)
            ->setConstructorArgs([$provider, $this->action])
            ->onlyMethods(['query_ai_api'])
            ->getMock();

        // Mock `query_ai_api()` to return the predefined response.
        if ($success) {
            $processor->method('query_ai_api')
                ->willReturn($responsesuccess);
        } else {
            $processor->method('query_ai_api')
                ->willReturn($responseerror);
        }

        return $processor;
    }

    /**
     * Test create_request_object
     */
    public function test_create_request(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request');
        $request = $method->invoke($processor);

        $body = (object) json_decode($request['body']);

        $this->assertStringContainsString('This is a test prompt', $body->inputText);
        $this->assertStringContainsString('You will receive a text input from the user.', $body->inputText);
    }

    /**
     * Test create_request with extra model settings.
     */
    public function test_create_request_object_with_model_settings(): void {
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\generate_text::class,
            actionconfig: [
                'systeminstruction' => get_string('action_generate_text_instruction', 'core_ai'),
                'temperature' => '0.5',
                'max_tokens' => '100',
            ],
        );
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a protected method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request');
        $request = $method->invoke($processor);

        $body = (object) json_decode($request['body']);

        $this->assertEquals('amazon.titan-text-lite-v1', $request['modelId']);
        $this->assertEquals('0.5', $body->textGenerationConfig->temperature);
        $this->assertEquals('100', $body->textGenerationConfig->max_tokens);

        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\generate_text::class,
            actionconfig: [
                'model' => 'amazon.titan-text-lite-v2',
                'systeminstruction' => get_string('action_generate_text_instruction', 'core_ai'),
                'modelextraparams' => '{"temperature": 0.5,"max_tokens": 100}',
            ],
        );
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a protected method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request');
        $request = $method->invoke($processor);

        $body = (object) json_decode($request['body']);

        $this->assertEquals('amazon.titan-text-lite-v2', $request['modelId']);
        $this->assertEquals('0.5', $body->textGenerationConfig->temperature);
        $this->assertEquals('100', $body->textGenerationConfig->max_tokens);
    }

    /**
     * Test the API error response handler method.
     */
    public function test_handle_api_error(): void {
        // Mock an AWS Command
        $command = new Command('InvokeModel');

        // Define various error responses
        $responses = [
            400 => new AwsException(
                'ValidationException: Invalid modelId',
                $command,
                [
                    'code' => 'ValidationException',
                    'response' => new Response(400, [], json_encode([
                        'message' => 'Invalid modelId: invalid-model-id',
                        'code' => 'ValidationException'
                    ]))
                ]
            ),
            403 => new AwsException(
                'AccessDeniedException: You do not have permission to access this resource',
                $command,
                [
                    'code' => 'AccessDeniedException',
                    'response' => new Response(403, [], json_encode([
                        'message' => 'You do not have permission to invoke this model',
                        'code' => 'AccessDeniedException'
                    ]))
                ]
            ),
            429 => new AwsException(
                'ThrottlingException: Too many requests',
                $command,
                [
                    'code' => 'ThrottlingException',
                    'response' => new Response(429, [], json_encode([
                        'message' => 'Rate limit exceeded, please try again later',
                        'code' => 'ThrottlingException'
                    ]))
                ]
            ),
            500 => new AwsException(
                'InternalServerException: AWS Bedrock encountered an error',
                $command,
                [
                    'code' => 'InternalServerException',
                    'response' => new Response(500, [], json_encode([
                        'message' => 'An internal server error occurred',
                        'code' => 'InternalServerException'
                    ]))
                ]
            )
        ];

        // Create an instance of the class that processes API errors
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => $exception) {
            $result = $method->invoke($processor, $exception);

            // Assert that the returned error code matches the expected HTTP status
            $this->assertEquals($status, $result['errorcode'], "Failed asserting for status $status");
        }
    }


    /**
     * Test the API success response handler method.
     */
    public function test_handle_api_success(): void {
        // We're testing a protected method, so we need to setup reflector magic.
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->get_mocked_aws_result());

        $this->assertTrue($result['success']);
        $this->assertEquals('mock-request-id', $result['fingerprint']);
        $this->assertEquals('The capital of Australia is Canberra.', $result['generatedcontent']);
        $this->assertEquals('FINISHED', $result['finishreason']);
        $this->assertEquals('11', $result['prompttokens']);
        $this->assertEquals('568', $result['completiontokens']);
        $this->assertEquals('amazon.titan-text-lite-v1', $result['model']);
    }

    /**
     * Test query_ai_api for a successful call.
     */
    public function test_query_ai_api_success(): void {
        // Create a mock of the Bedrock client.
        $mockclient = $this->createMock(BedrockRuntimeClient::class);

        // Properly mock the invokeModel call using __call.
        $mockclient->expects($this->any())
            ->method('__call')
            ->with('invokeModel', $this->anything()) // AWS SDK calls are dynamic via __call
            ->willReturn($this->get_mocked_aws_result());

        // Now properly mock the provider while calling its constructor.
        $mockprovider = $this->getMockBuilder(get_class($this->provider))
            ->setConstructorArgs([
                true, // Enable the provider.
                'mockprovider', // Provider name.
                '{}', // Empty config is ok here.
            ])
            ->onlyMethods(['create_bedrock_client']) // Only mock this method.
            ->getMock();

        // Ensure the mock returns our fake client.
        $mockprovider->method('create_bedrock_client')
            ->willReturn($mockclient);

        // Ensure the mock returns our fake client.
        $mockprovider->method('create_bedrock_client')
            ->willReturn($mockclient);

        // Create an instance of the processor with the mocked provider.
        $processor = new process_generate_text($mockprovider, $this->action);

        // We're testing a protected method, so we need to setup reflector magic.
        $method = new \ReflectionMethod($processor, 'query_ai_api');

        // Invoke the query_ai_api method.
        $result = $method->invoke($processor);

        // Assertions.
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('mock-request-id', $result['fingerprint']);
        $this->assertEquals('The capital of Australia is Canberra.', $result['generatedcontent']);
        $this->assertEquals('FINISHED', $result['finishreason']);
        $this->assertEquals('11', $result['prompttokens']);
        $this->assertEquals('568', $result['completiontokens']);
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
            'id' => 'chatcmpl-9lkwPWOIiQEvI3nfcGofJcmS5lPYo',
            'fingerprint' => 'fp_c4e5b6fa31',
            'generatedcontent' => 'Sure, here is some sample text',
            'finishreason' => 'stop',
            'prompttokens' => '11',
            'completiontokens' => '568',
            'model' => 'gpt-4o',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertEquals($response['success'], $result->get_success());
        $this->assertEquals($response['generatedcontent'], $result->get_response_data()['generatedcontent']);
        $this->assertEquals($response['model'], $result->get_response_data()['model']);
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

    /**
     * Test process method.
     */
    public function test_process(): void {
        // Log in user.
        $this->setUser($this->getDataGenerator()->create_user());

        $processor = $this->get_mocked_query_ai_api_result($this->provider);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
    }

    /**
     * Test process method with error.
     */
    public function test_process_error(): void {
        // Log in user.
        $this->setUser($this->getDataGenerator()->create_user());

        $processor = $this->get_mocked_query_ai_api_result($this->provider, false);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Invalid Authentication', $result->get_errormessage());
    }

    /**
     * Test process method with user rate limiter.
     */
    public function test_process_with_user_rate_limiter(): void {
        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Log in user1.
        $this->setUser($user1);
        // Mock clock.
        $clock = $this->mock_clock_with_frozen();

        // Set the user rate limiter.
        $config = [
            'apikey' => '123',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
        ];
        $provider = $this->manager->create_provider_instance(
            classname: '\aiprovider_awsbedrock\provider',
            name: 'dummy',
            config: $config,
            actionconfig: [
                \core_ai\aiactions\generate_text::class => [
                    'settings' => [
                        'model' => 'gpt-4o',
                        'endpoint' => "https://api.awsbedrock.com/v1/chat/completions",
                        'systeminstruction' => get_string('action_generate_text_instruction', 'core_ai'),
                    ],
                ],
            ],
        );

        // Case 1: User rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: User rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);

        $result = $processor->process();
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('User rate limit exceeded', $result->get_errormessage());
        $this->assertFalse($result->get_success());

        // Case 3: User rate limit has not been reached for a different user.
        // Log in user2.
        $this->setUser($user2);
        $this->create_action($user2->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Time window has passed, user rate limit should be reset.
        $clock->bump(11);
        // Log in user1.
        $this->setUser($user1);
        $this->provider = $this->create_provider(\core_ai\aiactions\generate_text::class);
        $this->create_action($user1->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }

    /**
     * Test process method with global rate limiter.
     */
    public function test_process_with_global_rate_limiter(): void {
        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Log in user1.
        $this->setUser($user1);
        // Mock clock.
        $clock = $this->mock_clock_with_frozen();

        // Set the global rate limiter.
        $config = [
            'apikey' => '123',
            'enableglobalratelimit' => true,
            'globalratelimit' => 1,
        ];
        $provider = $this->manager->create_provider_instance(
            classname: '\aiprovider_awsbedrock\provider',
            name: 'dummy',
            config: $config,
            actionconfig: [
                \core_ai\aiactions\generate_text::class => [
                    'settings' => [
                        'model' => 'gpt-4o',
                        'endpoint' => "https://api.awsbedrock.com/v1/chat/completions",
                        'systeminstruction' => get_string('action_generate_text_instruction', 'core_ai'),
                    ],
                ],
            ],
        );

        // Case 1: Global rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('Global rate limit exceeded', $result->get_errormessage());
        $this->assertFalse($result->get_success());

        // Case 3: Global rate limit has been reached for a different user too.
        // Log in user2.
        $this->setUser($user2);
        $this->create_action($user2->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertFalse($result->get_success());

        // Case 4: Time window has passed, global rate limit should be reset.
        $clock->bump(11);
        // Log in user1.
        $this->setUser($user1);
        $this->create_action($user1->id);
        $processor = $this->get_mocked_query_ai_api_result($provider);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }

    /**
     * Test create_amazon_request method.
     */
    public function test_create_amazon_titan_request(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_amazon_request');

        $requestobj = new \stdClass();
        $systeminstruction = 'This is a test system instruction';
        $modelsettings = [
            'temperature' => 0.5,
            'max_tokens' => 100,
            'stopSequences' => 'alpha beta gamma',
            'awsregion' => 'us-east-1',
        ];

        $result = $method->invoke($processor, $requestobj, $systeminstruction, $modelsettings);

        $this->assertEquals('This is a test system instruction\n\nThis is a test prompt', $result->inputText);
        $this->assertEquals(0.5, $result->textGenerationConfig->temperature);
        $this->assertEquals(100, $result->textGenerationConfig->max_tokens);
        $this->assertEquals(['alpha beta gamma'], $result->textGenerationConfig->stopSequences);
    }

    /**
     * Test create_anthropic_request method.
     */
    public function test_create_anthropic_request(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_anthropic_request');

        $requestobj = new \stdClass();
        $systeminstruction = 'This is a test system instruction';
        $modelsettings = [
            'temperature' => 0.5,
            'top_p' => 0.9,
            'top_k' => 100,
            'max_tokens' => 100,
            'stop_sequences' => 'alpha beta gamma',
            'awsregion' => 'us-east-1',
        ];

        $result = $method->invoke($processor, $requestobj, $systeminstruction, $modelsettings);

        $this->assertEquals('bedrock-2023-05-31', $result->anthropic_version);
        $this->assertEquals('This is a test system instruction', $result->system);
        $this->assertEquals(0.5, $result->temperature);
        $this->assertEquals(0.9, $result->top_p);
        $this->assertEquals(100, $result->top_k);
        $this->assertEquals(100, $result->max_tokens);
        $this->assertEquals(['alpha beta gamma'], $result->stop_sequences);
        $this->assertEquals('This is a test prompt', $result->messages[0]->content[0]->text);
    }

    /**
     * Test create_mistral_request method.
     */
    public function test_create_mistral_request(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_mistral_request');

        $requestobj = new \stdClass();
        $systeminstruction = 'This is a test system instruction';
        $modelsettings = [
            'temperature' => 0.5,
            'top_p' => 0.9,
            'top_k' => 100,
            'max_tokens' => 100,
            'stop' => 'alpha beta gamma',
            'awsregion' => 'us-east-1',
        ];

        $result = $method->invoke($processor, $requestobj, $systeminstruction, $modelsettings);

        $this->assertEquals(
            '<s>[INST] System: This is a test system instruction User: This is a test prompt [/INST]',
            $result->prompt);
        $this->assertEquals(0.5, $result->temperature);
        $this->assertEquals(0.9, $result->top_p);
        $this->assertEquals(100, $result->top_k);
        $this->assertEquals(100, $result->max_tokens);
        $this->assertEquals(['alpha beta gamma'], $result->stop);
    }

    /**
     * Test create_a21_request method.
     */
    public function test_create_a21_request(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_ai21_request');

        $requestobj = new \stdClass();
        $systeminstruction = 'This is a test system instruction';
        $modelsettings = [
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.5,
            'max_tokens' => 100,
            'stop' => 'alpha beta gamma',
            'awsregion' => 'us-east-1',
        ];

        $result = $method->invoke($processor, $requestobj, $systeminstruction, $modelsettings);

        $this->assertEquals(0.5, $result->frequency_penalty);
        $this->assertEquals(0.5, $result->presence_penalty);
        $this->assertEquals(100, $result->max_tokens);
        $this->assertEquals(['alpha beta gamma'], $result->stop);
        $this->assertEquals('user', $result->messages[1]->role);
        $this->assertEquals('This is a test prompt', $result->messages[1]->content);
        $this->assertEquals('system', $result->messages[0]->role);
        $this->assertEquals('This is a test system instruction', $result->messages[0]->content);
    }
}
