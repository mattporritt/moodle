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

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class process text generation.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends abstract_processor {

    #[\Override]
    protected function get_system_instruction(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'];
    }

    #[\Override]
    protected function create_request(): array {
        $requestobj = new \stdClass();
        $systeminstruction = $this->get_system_instruction();
        $modelsettings = $this->get_model_settings();

        // Handle model family specific configuration.
        if (str_contains($this->get_model(), 'amazon')) {
            if (!empty($systeminstruction)) {
                $requestobj->inputText = $this->action->get_configuration('prompttext') . '\n\n' . $systeminstruction;
            } else {
                $requestobj->inputText = $this->action->get_configuration('prompttext');
            }
            // Append the extra model settings.
            if (!empty($modelsettings)) {
                $modelobj = new \stdClass();
                foreach ($modelsettings as $setting => $value) {
                    $modelobj->$setting = $value;
                }
                $requestobj->textGenerationConfig = $modelobj;
            }
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return [
            'ContentType' => 'application/json',
            'Accept' => 'application/json',
            'modelId' => $this->get_model(),
            'body' => json_encode($requestobj),
        ];
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param array $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(array $response): array {
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
}
