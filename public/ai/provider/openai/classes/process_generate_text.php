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
 * Class process text generation.
 *
 * @package    aiprovider_openai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends abstract_processor {
    /** The official OpenAI Responses endpoint. */
    private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';


    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        if ($this->uses_responses_api()) {
            return $this->create_responses_request_object($userid);
        }

        // Create the user object.
        $userobj = new \stdClass();
        $userobj->role = 'user';
        $userobj->content = $this->action->get_configuration('prompttext');

        // Create the request object.
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->user = $userid;

        // If there is a system string available, use it.
        $systeminstruction = $this->get_system_instruction();
        if (!empty($systeminstruction)) {
            $systemobj = new \stdClass();
            $systemobj->role = 'system';
            $systemobj->content = $systeminstruction;
            $requestobj->messages = [$systemobj, $userobj];
        } else {
            $requestobj->messages = [$userobj];
        }

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

        if ($this->uses_responses_api()) {
            return $this->handle_responses_api_success($bodyobj);
        }

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
     * Whether this action uses the official Responses API endpoint.
     *
     * Non-official endpoints retain the Chat Completions contract so that a
     * custom endpoint is never sent an incompatible request payload.
     *
     * @return bool
     */
    private function uses_responses_api(): bool {
        return (string) $this->get_endpoint() === self::RESPONSES_ENDPOINT;
    }

    /**
     * Create a request for the official OpenAI Responses API.
     *
     * @param string $userid The pseudonymous user identifier.
     * @return RequestInterface
     */
    private function create_responses_request_object(string $userid): RequestInterface {
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->input = $this->action->get_configuration('prompttext');
        $requestobj->safety_identifier = $userid;
        $requestobj->store = false;

        $systeminstruction = $this->get_system_instruction();
        if (!empty($systeminstruction)) {
            $requestobj->instructions = $systeminstruction;
        }

        foreach ($this->get_model_settings() as $setting => $value) {
            $requestobj->$setting = $value;
        }

        return new Request(
            method: 'POST',
            uri: '',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($requestobj),
        );
    }

    #[\Override]
    protected function get_model_settings(): array {
        $settings = parent::get_model_settings();

        if (!$this->uses_responses_api() && isset($settings['max_output_tokens'])) {
            $settings['max_completion_tokens'] = $settings['max_output_tokens'];
            unset($settings['max_output_tokens']);
        }

        return $settings;
    }

    /**
     * Handle a successful response from the official OpenAI Responses API.
     *
     * @param \stdClass|null $bodyobj The decoded response body.
     * @return array The response.
     */
    private function handle_responses_api_success(?\stdClass $bodyobj): array {
        if ($bodyobj === null) {
            return \core_ai\error\factory::create(
                500,
                get_string('invalidresponsesresponse', 'aiprovider_openai'),
            )->get_error_details();
        }

        if (($bodyobj->status ?? null) !== 'completed' || empty($bodyobj->id)) {
            $details = $bodyobj->incomplete_details ?? null;
            $reason = is_object($details) ? ($details->reason ?? null) : null;
            $reason ??= get_string('invalidresponsesresponse', 'aiprovider_openai');
            return \core_ai\error\factory::create(500, $reason)->get_error_details();
        }

        $usage = $bodyobj->usage ?? null;
        if (
            !is_object($usage) ||
            !isset($usage->input_tokens) ||
            !is_int($usage->input_tokens) ||
            $usage->input_tokens < 0 ||
            !isset($usage->output_tokens) ||
            !is_int($usage->output_tokens) ||
            $usage->output_tokens < 0
        ) {
            return \core_ai\error\factory::create(
                500,
                get_string('invalidresponsesresponse', 'aiprovider_openai'),
            )->get_error_details();
        }

        $generatedcontent = '';
        $output = $bodyobj->output ?? null;
        if (!is_array($output)) {
            return \core_ai\error\factory::create(
                500,
                get_string('invalidresponsesresponse', 'aiprovider_openai'),
            )->get_error_details();
        }

        foreach ($output as $outputitem) {
            if (!is_object($outputitem)) {
                continue;
            }
            if (($outputitem->type ?? null) !== 'message') {
                continue;
            }
            $content = $outputitem->content ?? null;
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $contentitem) {
                if (!is_object($contentitem)) {
                    continue;
                }
                if (($contentitem->type ?? null) === 'output_text') {
                    if (!isset($contentitem->text) || !is_string($contentitem->text)) {
                        return \core_ai\error\factory::create(
                            500,
                            get_string('invalidresponsesresponse', 'aiprovider_openai'),
                        )->get_error_details();
                    }
                    $generatedcontent .= $contentitem->text;
                }
            }
        }

        if ($generatedcontent === '') {
            return \core_ai\error\factory::create(500, get_string('invalidresponsesresponse', 'aiprovider_openai'))
                ->get_error_details();
        }

        return [
            'success' => true,
            'id' => $bodyobj->id,
            'fingerprint' => null,
            'generatedcontent' => $generatedcontent,
            'finishreason' => $bodyobj->status,
            'prompttokens' => $usage->input_tokens,
            'completiontokens' => $usage->output_tokens,
            'model' => $bodyobj->model ?? $this->get_model(),
        ];
    }

    #[\Override]
    protected function validate_request_configuration(): ?array {
        $settings = $this->provider->actionconfig[$this->action::class]['settings'];
        if ($this->uses_responses_api() && !empty($settings['modelextraparams'])) {
            return \core_ai\error\factory::create(
                400,
                get_string('responsesextraparamsunsupported', 'aiprovider_openai'),
            )->get_error_details();
        }

        return null;
    }

    #[\Override]
    protected function handle_api_error(ResponseInterface $response): array {
        $status = $response->getStatusCode();
        $errormessage = $response->getReasonPhrase();
        if ($status < 500) {
            $bodyobj = json_decode($response->getBody()->getContents());
            $errormessage = $bodyobj->error->message ?? $errormessage;
        }

        return \core_ai\error\factory::create($status, $errormessage)->get_error_details();
    }
}
