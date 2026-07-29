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
 * Process image description requests with OpenAI-compatible chat completions.
 *
 * @package    aiprovider_openai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_describe_image extends abstract_processor {
    /** @var int Moodle-side safety limit for images loaded into base64 request bodies. */
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;

    #[\Override]
    protected function query_ai_api(): array {
        $validation = $this->action->validate_provider_limits(maxfilesize: self::MAX_FILE_SIZE);
        if ($validation !== true) {
            return $validation;
        }

        return parent::query_ai_api();
    }

    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $image = $this->action->get_configuration('image');
        $requestobj = (object) [
            'model' => $this->get_model(),
            'user' => $userid,
            'messages' => [],
        ];

        $systeminstruction = $this->get_system_instruction();
        if ($systeminstruction !== '') {
            $requestobj->messages[] = (object) [
                'role' => 'system',
                'content' => $systeminstruction,
            ];
        }
        $requestobj->messages[] = (object) [
            'role' => 'user',
            'content' => [
                (object) [
                    'type' => 'text',
                    'text' => $this->action->get_prompt(),
                ],
                (object) [
                    'type' => 'image_url',
                    'image_url' => (object) [
                        'url' => 'data:' . $image->get_mimetype() . ';base64,' . base64_encode($image->get_content()),
                    ],
                ],
            ],
        ];

        foreach ($this->get_model_settings() as $setting => $value) {
            $requestobj->$setting = $value;
        }

        return new Request('POST', '', ['Content-Type' => 'application/json'], json_encode($requestobj));
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());
        $message = $bodyobj->choices[0]->message ?? null;
        $generatedcontent = $message->content ?? null;
        if (!empty($message->refusal) || !is_string($generatedcontent) || trim($generatedcontent) === '') {
            return \core_ai\error\factory::create(
                500,
                get_string('error:invalidresponse', 'core_ai'),
            )->get_error_details();
        }

        return [
            'success' => true,
            'id' => $bodyobj->id ?? null,
            'fingerprint' => $bodyobj->system_fingerprint ?? null,
            'generatedcontent' => $generatedcontent,
            'finishreason' => $bodyobj->choices[0]->finish_reason ?? null,
            'prompttokens' => $bodyobj->usage->prompt_tokens ?? null,
            'completiontokens' => $bodyobj->usage->completion_tokens ?? null,
            'model' => $bodyobj->model ?? $this->get_model(),
        ];
    }
}
