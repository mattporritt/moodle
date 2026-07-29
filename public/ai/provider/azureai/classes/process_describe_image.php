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

namespace aiprovider_azureai;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Process image description requests with Azure AI.
 *
 * @package    aiprovider_azureai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_describe_image extends abstract_processor {
    #[\Override]
    protected function query_ai_api(): array {
        $validation = $this->action->validate_provider_limits(maxfilesize: 20 * 1024 * 1024);
        if ($validation !== true) {
            return $validation;
        }

        return parent::query_ai_api();
    }

    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri(rtrim($this->provider->config['endpoint'], '/')
            . '/openai/deployments/' . $this->get_deployment_name()
            . '/chat/completions?api-version=' . $this->get_api_version());
    }

    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $image = $this->action->get_configuration('image');
        $requestobj = (object) ['user' => $userid, 'messages' => []];

        $systeminstruction = $this->get_system_instruction();
        if ($systeminstruction !== '') {
            $requestobj->messages[] = (object) ['role' => 'system', 'content' => $systeminstruction];
        }
        $requestobj->messages[] = (object) [
            'role' => 'user',
            'content' => [
                (object) ['type' => 'text', 'text' => $this->action->get_prompt()],
                (object) [
                    'type' => 'image_url',
                    'image_url' => (object) [
                        'url' => 'data:' . $image->get_mimetype() . ';base64,' . base64_encode($image->get_content()),
                    ],
                ],
            ],
        ];

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
            'model' => $bodyobj->model ?? $this->get_deployment_name(),
        ];
    }
}
