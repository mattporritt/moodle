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

namespace aiprovider_openai;

use core\http_client;
use \core_ai\actions;

/**
 * Class provider.
 *
 * @package    aiprovier_openai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string The openAI API key. */
    // API key.
    private string $apikey;

    /** @var string The organisation ID that goes with the key */
    private string $orgid;

    /** @var string A unique identifier representing your end-user, which can help OpenAI to monitor and detect abuse.  */
    private string $userid;

    /** @var string The API endpoint to make requests against */
    private string $aiendpoint = 'https://api.openai.com/v1/images/generations';

    /** @var string The API model to use */
    private string $model = 'dall-e-3';

    /** @var int The number of images to generate dall-e-3 only supports 1 */
    private int $numberimages = 1;

    /** @var string Response format: url or b64_json. */
    private string $responseformat = 'url';

    /**
     * Class constructor.
     */
    public function __construct() {
        // Get api key from config.
        $this->apikey = get_config('aiprovider_openai', 'apikey');
        // Get api org id from config.
        $this->orgid = get_config('aiprovider_openai', 'orgid');
        // Generate the user id.
        $this->userid = $this->generate_userid();
    }

    /**
     * Generate a user id.
     * This is a hash of the site id and user id,
     * this means we can determine who made the request
     * but don't pass any personal data to OpenAI.
     *
     * @return string The generated user id.
     */
    private function generate_userid(): string {
        global $USER, $CFG;
        return hash('sha256', $CFG->siteidentifier . $USER->id);
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public function get_action_list(): array {
        return [
            'generate_text',
            'generate_image',
            'summarise_text',
            'translate_text',
        ];
    }

    /**
     * Process the generate_text action.
     * Handles communication with the OpenAI API and returning the result.
     *
     * @param actions\base $action The action to process.
     * @return actions\action_response The result of the action.
     */
    public function process_action_generate_image(actions\base $action): actions\action_response {
        // Create the HTTP client.
        $client = $this->create_http_client();

        // Create the request object.
        $requestobj = $this->create_request_object($action);

        // Make the request to the OpenAI API.
        $response = $this->query_ai_api($client, $requestobj);

        // If the request was successful, save the URL to a file.
        if ($response['success']) {
            $this->url_to_file(
                $action->get_configuration('contextid'),
                $response['body']['url']
            );
        }

        // Format the action response object.
        return $this->prepare_response($response);
    }

    /**
     * Convert the given aspect ratio to an image size
     * that is compatible with the OpenAI API.
     *
     * @param string $ratio The aspect ratio of the image.
     * @return string The size of the image.
     * @throws \coding_exception
     */
    private function calculate_size(string $ratio): string {
        if ($ratio == 'square') {
            $size = '1024x1024';
        } else if ($ratio == 'landscape') {
            $size = '1792x1024';
        } else if ($ratio == 'portrait') {
            $size = '1024x1792';
        } else {
            throw new \coding_exception('Invalid aspect ratio: ' . $ratio);
        }
        return $size;
    }

    /**
     * Create the HTTP client.
     *
     * @return http_client The HTTP client used to make requests.
     */
    private function create_http_client(): http_client {
        return new http_client([
                'base_uri' => $this->aiendpoint,
                'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey,
                        'OpenAI-Organization' => $this->orgid,
                ]
        ]);
    }

    /**
     * Create the request object to send to the OpenAI API.
     * This object contains all the required parameters for the request.
     *
     * @param \core_ai\actions\base $action The action to process.
     * @return \stdClass The request object to send to the OpenAI API.
     * @throws \coding_exception
     */
    private function create_request_object(\core_ai\actions\base $action): \stdClass {
        $requestobj = new \stdClass();
        $requestobj->prompt = $action->get_configuration('prompttext');
        $requestobj->model = $this->model;
        $requestobj->n = $this->numberimages;
        $requestobj->quality = $action->get_configuration('quality');
        $requestobj->response_format = $this->responseformat;
        $requestobj->size = $this->calculate_size($action->get_configuration('aspectratio'));
        $requestobj->style = $action->get_configuration('style');
        $requestobj->user = $this->userid;

        return $requestobj;
    }

    /**
     * Query the AI service.
     *
     * @param http_client $client The http client.
     * @param \stdClass $requestobj The request object.
     * @return array The response from the AI service.
     */
    public function query_ai_api(http_client $client, \stdClass $requestobj): array {
        $requestjson = json_encode($requestobj);

        // Call the external AI service.
        $response = $client->request('POST', '', [
                'body' => $requestjson,
        ]);

        // Handle the various response codes.
        $status = $response->getStatusCode();
        if ($status == 200) {
            return $this->handle_api_success($response);
        } else {
            return $this->handle_api_error($status, $response);
        }
    }

    /**
     * Handle an error from the external AI api.
     *
     * @param int $status The status code.
     * @param \GuzzleHttp\Psr7\Response $response The response object.
     * @return array The error response.
     */
    protected function handle_api_error(int $status, \GuzzleHttp\Psr7\Response $response): array {

        if ($status == 500) {
            $responsearr = [
                    'errorcode' => $status,
                    'errormessage' => 'Internal server error.',
            ];
        } else if ($status == 503) {
            $responsearr = [
                    'errorcode' => $status,
                    'errormessage' => 'Service unavailable.',
            ];
        } else {
            $responsebody = $response->getBody();
            $bodyobj = json_decode($responsebody->getContents());
            $responsearr =[
                    'errorcode' => $status,
                    'errormessage' => $bodyobj->error->message,
            ];
        }

        return $responsearr;
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param \GuzzleHttp\Psr7\Response $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(\GuzzleHttp\Psr7\Response $response): array {
        $responsebody = $response->getBody();
        $bodyobj = json_decode($responsebody->getContents());

        return [
            'success' => true,
            'body' => [
                'created' => $bodyobj->created,
                'revised_prompt' => $bodyobj->data[0]->revised_prompt,
                'url' => $bodyobj->data[0]->url
            ],
        ];
    }

    /**
     * Prepare the response object.
     *
     * @param array $response The response object.
     * @throws \coding_exception
     * @return actions\action_response The action response object.
     */
    private function prepare_response(array $response): actions\action_response {
        if ($response['success']) {
            return new actions\action_response(
                success: true,
                actionname: 'generate_image',
                body: $response['body']
            );
        } else {
            return new actions\action_response(
                success: false,
                actionname: 'generate_image',
                errorcode: $response['errorcode'],
                errormessage: $response['errormessage']
            );
        }
    }

    /**
     * Convert the url for the image  to a file.
     *
     * Placements can't interact with the provider AI directly,
     * therefore we need to provide the image file in a format that can
     * be used by placements. So we use the file API.
     *
     * @param int $contextid The context id.
     * @param string $url The URL to the image.
     * @return \stored_file The file object.
     */
    private function url_to_file(int $contextid, string $url): \stored_file {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apikey,
                'OpenAI-Organization' => $this->orgid,
            ]
        ];

        $parsedurl = parse_url($url, PHP_URL_PATH); // Parse the URL to get the path.
        $filename = basename($parsedurl); // Get the basename of the path.

        $fileinfo = new \stdClass();
        $fileinfo->contextid = $contextid;
        $fileinfo->filearea  = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid    = file_get_unused_draft_itemid();
        $fileinfo->filepath  = '/';
        $fileinfo->filename  = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_url($fileinfo, $url, $options);
    }
}
