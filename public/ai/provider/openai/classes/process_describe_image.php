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

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class process image description.
 *
 * @package    aiprovider_openai
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_describe_image extends abstract_processor {

    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        // Get the base64 encoded image.
        $base64image = $this->stored_file_to_data_uri($this->action->get_configuration('image'));

        // Get the system instruction.
        $systeminstruction = $this->get_system_instruction();

        // Throw an error if no system instruction is set. We need it to tell the model to describe the image.
        if (empty($systeminstruction)) {
            throw new \moodle_exception('nosysteminstruction', 'aiprovider_openai');
        }

        // Create the request object.
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->user = $userid;
        $requestobj->messages = [];

        $message = new \stdClass();
        $message->role = 'user';
        $message->content = [];

        $textContent = new \stdClass();
        $textContent->type = 'text';
        $textContent->text = $systeminstruction;

        $imageContent = new \stdClass();
        $imageContent->type = 'image_url';
        $imageContent->image_url = new \stdClass();
        $imageContent->image_url->url = $base64image;

        $message->content[] = $textContent;
        $message->content[] = $imageContent;

        $requestobj->messages[] = $message;

        // Append the extra model settings.
        $modelsettings = $this->get_model_settings();
        foreach ($modelsettings as $setting => $value) {
            $requestobj->$setting = $value;
        }

        return new Request(
            method: 'POST',
            uri: '',
            headers: [
                'Content-Type' => 'application/json',
            ],
            body: json_encode($requestobj),
        );
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        $responsebody = $response->getBody();
        $bodyobj = json_decode($responsebody->getContents());

        return [
            'success' => true,
            'id' => $bodyobj->id,
            'fingerprint' => $bodyobj->system_fingerprint,
            'generatedcontent' => $bodyobj->choices[0]->message->content,
            'finishreason' => $bodyobj->choices[0]->finish_reason,
            'prompttokens' => $bodyobj->usage->prompt_tokens,
            'completiontokens' => $bodyobj->usage->completion_tokens,
            'model' => $bodyobj->model ?? $this->get_model(), // Fallback to config model.
        ];
    }

    /**
     * Convert an image in Moodle stored_file object format to a base64-encoded data URI string.
     *
     * @param \stored_file $image The Moodle stored_file object.
     * @return string The data URI string.
     * @throws \moodle_exception If the file type is not supported.
     */
    private function stored_file_to_data_uri(\stored_file $image): string {
        $supportedtypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpeg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $mimetype = $image->get_mimetype();
        if (!isset($supportedtypes[$mimetype])) {
            throw new \moodle_exception('unsupportedfiletype', 'error', '', $mimetype);
        }
        // For GIF, check if animated (only allow non-animated).
        if ($mimetype === 'image/gif') {
            $content = $image->get_content();
            // Check for animated GIF: more than one frame (look for multiple graphic control extensions).
            if (preg_match_all('/\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', $content, $matches) > 1) {
                throw new \moodle_exception('animatedgifnotsupported', 'error');
            }
        } else {
            $content = $image->get_content();
        }
        $base64 = base64_encode($content);
        $ext = $supportedtypes[$mimetype];
        return "data:image/{$ext};base64,{$base64}";
    }
}
