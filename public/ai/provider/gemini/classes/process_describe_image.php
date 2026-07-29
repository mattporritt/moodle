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

namespace aiprovider_gemini;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Process image description requests with Gemini.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_describe_image extends abstract_processor {
    /** @var int Maximum size of a Gemini inline generateContent request. */
    private const MAX_REQUEST_SIZE = 20_000_000;

    #[\Override]
    protected function query_ai_api(): array {
        $emptyrequestsize = strlen(json_encode($this->get_request_data(''), JSON_UNESCAPED_SLASHES));
        $availablebase64bytes = max(0, self::MAX_REQUEST_SIZE - $emptyrequestsize);
        $maxfilesize = intdiv($availablebase64bytes, 4) * 3;
        $validation = $this->action->validate_provider_limits(maxfilesize: $maxfilesize);
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
        $requestobj = $this->get_request_data(base64_encode($image->get_content()));

        return new Request(
            'POST',
            '',
            ['Content-Type' => 'application/json'],
            json_encode($requestobj, JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * Build the Gemini request data around the supplied base64 image data.
     *
     * @param string $imagedata Base64-encoded image data.
     * @return \stdClass
     */
    private function get_request_data(string $imagedata): \stdClass {
        $image = $this->action->get_configuration('image');
        $requestobj = (object) [
            'contents' => [(object) [
                'role' => 'user',
                'parts' => [
                    (object) ['text' => $this->action->get_prompt()],
                    (object) [
                        'inline_data' => (object) [
                            'mime_type' => $image->get_mimetype(),
                            'data' => $imagedata,
                        ],
                    ],
                ],
            ]],
        ];

        $systeminstruction = $this->get_system_instruction();
        if ($systeminstruction !== '') {
            $requestobj->system_instruction = (object) [
                'parts' => [(object) ['text' => $systeminstruction]],
            ];
        }

        $modelsettings = $this->get_model_settings();
        if ($modelsettings) {
            $generationconfig = new \stdClass();
            foreach ($modelsettings as $setting => $value) {
                $generationconfig->$setting = $setting === 'stop_sequences'
                    ? array_map('trim', explode(',', $value))
                    : $value;
            }
            $requestobj->generationConfig = $generationconfig;
        }

        return $requestobj;
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());
        $candidate = $bodyobj->candidates[0] ?? null;
        $usage = $bodyobj->usageMetadata ?? (object) [];
        $generatedcontent = $candidate->content->parts[0]->text ?? null;
        if (!is_string($generatedcontent) || trim($generatedcontent) === '') {
            return \core_ai\error\factory::create(
                500,
                get_string('error:invalidresponse', 'core_ai'),
            )->get_error_details();
        }

        return [
            'success' => true,
            'id' => $bodyobj->responseId ?? null,
            'generatedcontent' => $generatedcontent,
            'finishreason' => $candidate->finishReason ?? null,
            'prompttokens' => $usage->promptTokenCount ?? null,
            'completiontokens' => $usage->candidatesTokenCount ?? null,
            'model' => $bodyobj->modelVersion ?? $this->get_model(),
        ];
    }
}
