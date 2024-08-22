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

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\BedrockRuntime\Exception\BedrockRuntimeException;
use Aws\Result;
use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;
use core_ai\aiactions\responses\response_generate_text;
use core_ai\process_base;
use core_ai\provider;
use GuzzleHttp\Exception\RequestException;

/**
 * Class process text generation.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends process_base {
    /** @var string The AWS region that the model is hosted in. */
    protected string $region;

    /** @var string The API model to use. */
    protected string $model;

    /** @var string The system instructions. */
    protected string $systeminstructions;

    /**
     * Class constructor.
     *
     * @param provider $provider The provider that will process the action.
     * @param base $action The action to process.
     */
    public function __construct(provider $provider, base $action) {
        parent::__construct($provider, $action);
        $this->region = get_config('aiprovider_awsbedrock', 'action_generate_text_region');
        $this->model = get_config('aiprovider_awsbedrock', 'action_generate_text_model');
        $this->systeminstructions = get_config('aiprovider_awsbedrock', 'action_generate_text_systeminstruction');
    }

    /**
     * Process the AI request.
     *
     * @return response_base The result of the action.
     */
    public function process(): response_base {
        // Check the rate limiter.
        $ratelimitcheck = $this->provider->is_request_allowed($this->action);
        if ($ratelimitcheck !== true) {
            return new response_generate_text(
                    success: false,
                    actionname: 'generate_text',
                    errorcode: $ratelimitcheck['errorcode'],
                    errormessage: $ratelimitcheck['errormessage']
            );
        }

        $client = $this->provider->create_bedrock_client($this->region);

        // Create the request object.
        $request = $this->create_request($this->action);

        // Make the request to the OpenAI API.
        $response = $this->query_ai_api($client, $request);

        // Format the action response object.
        return $this->prepare_response($response);
    }

    /**
     * Query the AI service.
     *
     * @param BedrockRuntimeClient $client The client used to make requests.
     * @param array $request The request to process.
     * @return array The response from the AI service.
     */
    protected function query_ai_api(BedrockRuntimeClient $client, array $request): array {

        try {
            // Call the external AI service.
            $response = $client->invokeModel($request);
            // Double-check the response codes, in case of a non 200 that didn't throw an error.
            $status = $response['@metadata']['statusCode'];
            if ($status == 200) {
                return $this->handle_api_success($response);
            } else {
                return $this->handle_api_error($status);
            }
        } catch (BedrockRuntimeException $e) {
            // Handle any exceptions.
            return [
                    'success' => false,
                    'errorcode' => $e->getStatusCode(),
                    'errormessage' => $e->getAwsErrorMessage(),
            ];
        }

    }

    /**
     * Create the request object to send to AWS Bedrock.
     * This object contains all the required parameters for the request.
     *
     * @param \core_ai\aiactions\base $action The action to process.
     * @return array The request array to send to AWS Bedrock.
     * @throws \coding_exception
     */
    private function create_request(\core_ai\aiactions\base $action): array {
        $body = new \stdClass();
        $body->inputText = $action->get_configuration('prompttext');
        return [
            'ContentType' => 'application/json',
            'Accept' => 'application/json',
            'modelId' => $this->model,
            'body' => json_encode($body),
        ];
    }

    /**
     * Handle an error from the external AI api,
     * where we have an explicit response code but an exception was not thrown.
     *
     * @param int $status The status code.
     * @return array The error response.
     */
    protected function handle_api_error(int $status): array {
        $responsearr = [
                'success' => false,
                'errorcode' => $status,
        ];

        if ($status == 503) {
            $responsearr['errormessage'] = 'Service unavailable.';
        } else {
            $responsearr['errormessage'] = 'Internal server error.';
        }

        return $responsearr;
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param Result $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(Result $response): array {
        $responsebody = $response['body'];
        $bodyobj = json_decode($responsebody->getContents());

        return [
                'success' => true,
                'generatedcontent' => $bodyobj->results[0]->outputText,
                'finishreason' => $bodyobj->results[0]->completionReason,
                'prompttokens' => $bodyobj->inputTextTokenCount,
                'completiontokens' => $bodyobj->results[0]->tokenCount,
        ];
    }

    /**
     * Prepare the response object.
     *
     * @param array $response The response object.
     * @return response_generate_text The action response object.
     * @throws \coding_exception
     */
    private function prepare_response(array $response): response_generate_text {
        if ($response['success']) {
            $generatedtext = new response_generate_text(
                    success: true,
                    actionname: 'generate_text',
            );
            $generatedtext->set_response($response);
            return $generatedtext;
        } else {
            return new response_generate_text(
                    success: false,
                    actionname: 'generate_text',
                    errorcode: $response['errorcode'],
                    errormessage: $response['errormessage']
            );
        }
    }
}
