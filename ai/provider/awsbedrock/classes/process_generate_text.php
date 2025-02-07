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
        return $this->provider->actionconfig[$this->action::class]['settings']['systeminstruction'] ?? '';
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
                    // Skip if the setting is the aws region.
                    if ($setting === 'awsregion') {
                        continue;
                    }
                    $modelobj->$setting = $value;
                }
                // Only add the model settings if we have any.
                if(!empty((array)$modelobj)) {
                    $requestobj->textGenerationConfig = $modelobj;
                }
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

    #[\Override]
    protected function handle_api_success(Result $result): array {
        $bodyobj = json_decode($result['body']->getContents());

        // Bedrock contains token counts in the headers.
        $responseheaders = $result['@metadata']['headers'];
        $response = [
            'success' => true,
            'fingerprint' => $responseheaders['x-amzn-requestid'],
            'prompttokens' => $responseheaders['x-amzn-bedrock-input-token-count'],
            'completiontokens' => $responseheaders['x-amzn-bedrock-output-token-count'],
            'model' => $this->get_model(),
        ];

        // Bedrock contains different response structures for different models.
        if (str_contains($this->get_model(), 'amazon')) {
            $response['generatedcontent'] = $bodyobj->results[0]->outputText;
            $response['finishreason'] = $bodyobj->results[0]->completionReason;
        } else if (str_contains($this->get_model(), 'anthropic')) {
            $response['generatedcontent'] = $bodyobj->content[0]->text;
            $response['finishreason'] = $bodyobj->stop_reason;
        } else if (str_contains($this->get_model(), 'mistral')) {
            $response['generatedcontent'] = $bodyobj->outputs[0]->text;
            $response['finishreason'] = $bodyobj->outputs[0]->stop_reason;
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return $response;
    }
}
