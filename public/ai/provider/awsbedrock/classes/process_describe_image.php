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

use Aws\Result;

/**
 * Process image description requests with AWS Bedrock.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_describe_image extends abstract_processor {
    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function query_ai_api(): array {
        $model = $this->get_model();
        if (!str_contains($model, 'amazon.nova') && !str_contains($model, 'anthropic')) {
            return [
                'success' => false,
                'errorcode' => 400,
                'error' => 'unsupportedmodel',
                'errormessage' => get_string('error:visionnotsupported', 'aiprovider_awsbedrock', $model),
            ];
        }

        $validation = $this->action->validate_provider_limits(
            maxfilesize: str_contains($model, 'anthropic') ? 3_932_160 : 25_000_000,
            maxdimension: 8_000,
        );
        if ($validation !== true) {
            return $validation;
        }

        return parent::query_ai_api();
    }

    #[\Override]
    protected function create_request(): array {
        $model = $this->get_model();
        $image = $this->action->get_configuration('image');
        $format = match ($image->get_mimetype()) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $requestobj = new \stdClass();

        if (str_contains($model, 'anthropic')) {
            $requestobj->anthropic_version = 'bedrock-2023-05-31';
            $requestobj->system = $this->get_system_instruction();
            $requestobj->messages = [(object) [
                'role' => 'user',
                'content' => [
                    (object) [
                        'type' => 'image',
                        'source' => (object) [
                            'type' => 'base64',
                            'media_type' => $image->get_mimetype(),
                            'data' => base64_encode($image->get_content()),
                        ],
                    ],
                    (object) ['type' => 'text', 'text' => $this->action->get_prompt()],
                ],
            ]];
            foreach ($this->get_model_settings() as $setting => $value) {
                $requestobj->$setting = $setting === 'stop_sequences' ? [$value] : $value;
            }
        } else {
            $systeminstruction = $this->get_system_instruction();
            if ($systeminstruction !== '') {
                $requestobj->system = [(object) ['text' => $systeminstruction]];
            }
            $requestobj->messages = [(object) [
                'role' => 'user',
                'content' => [
                    (object) [
                        'image' => (object) [
                            'format' => $format,
                            'source' => (object) ['bytes' => base64_encode($image->get_content())],
                        ],
                    ],
                    (object) ['text' => $this->action->get_prompt()],
                ],
            ]];
            $requestobj->inferenceConfig = (object) $this->get_model_settings();
        }

        return [
            'ContentType' => 'application/json',
            'Accept' => 'application/json',
            'modelId' => $this->get_cross_region_inference() ?? $model,
            'body' => json_encode($requestobj),
        ];
    }

    #[\Override]
    protected function handle_api_success(Result $result): array {
        $bodyobj = json_decode($result['body']->getContents());
        $headers = $result['@metadata']['headers'] ?? [];
        $model = $this->get_model();
        $isanthropic = str_contains($model, 'anthropic');

        return [
            'success' => true,
            'fingerprint' => $headers['x-amzn-requestid'] ?? null,
            'generatedcontent' => $isanthropic
                ? $bodyobj->content[0]->text
                : $bodyobj->output->message->content[0]->text,
            'finishreason' => $isanthropic ? $bodyobj->stop_reason : $bodyobj->stopReason,
            'prompttokens' => $isanthropic
                ? ($bodyobj->usage->input_tokens ?? $headers['x-amzn-bedrock-input-token-count'] ?? null)
                : ($bodyobj->usage->inputTokens ?? $headers['x-amzn-bedrock-input-token-count'] ?? null),
            'completiontokens' => $isanthropic
                ? ($bodyobj->usage->output_tokens ?? $headers['x-amzn-bedrock-output-token-count'] ?? null)
                : ($bodyobj->usage->outputTokens ?? $headers['x-amzn-bedrock-output-token-count'] ?? null),
            'model' => $bodyobj->model ?? $model,
        ];
    }
}
