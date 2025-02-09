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

    /**
     * Create the request object for the Amazon models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_amazon_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        if (!empty($systeminstruction)) {
            $requestobj->inputText = $systeminstruction. '\n\n' . $this->action->get_configuration('prompttext');
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
                // Correctly format the stopSequences setting.
                if ($setting === 'stopSequences') {
                    $modelobj->$setting = [$value];
                } else {
                    $modelobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }
            // Only add the model settings if we have any.
            if(!empty((array)$modelobj)) {
                $requestobj->textGenerationConfig = $modelobj;
            }
        }

        return $requestobj;
    }

    /**
     * Create the request object for the Anthropic models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_anthropic_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        $requestobj->anthropic_version = "bedrock-2023-05-31";
        if (!empty($systeminstruction)) {
            $requestobj->system = $systeminstruction;
        }

        // Create message object.
        $messageobj = new \stdClass();
        $messageobj->type = 'text';
        $messageobj->text = $this->action->get_configuration('prompttext');

        // Create the user object.
        $userobj = new \stdClass();
        $userobj->role = 'user';
        $userobj->content = [$messageobj];

        $requestobj->messages = [$userobj];

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion') {
                    continue;
                }
                // Correctly format the stopSequences setting.
                if ($setting === 'stop_sequences') {
                    $requestobj->$setting = [$value];
                } else {
                    $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }
        }
        return $requestobj;
    }

    /**
     * Create the request object for the Mistral models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_mistral_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        if (!empty($systeminstruction)) {
            $requestobj->prompt = '<s>[INST] '
                . 'System: ' . $systeminstruction
                . ' User: ' . $this->action->get_configuration('prompttext')
                . ' [/INST]';
        } else {
            $requestobj->prompt = '<s>[INST] ' . $this->action->get_configuration('prompttext') . ' [/INST]';
        }

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion') {
                    continue;
                }
                // Correctly format the stopSequences setting.
                if ($setting === 'stop') {
                    $requestobj->$setting = [$value];
                } else {
                    $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }
        }

        return $requestobj;
    }

    /**
     * Create the request object for the AI21 models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * * @param string $systeminstruction The system instruction to append to the request object.
     * * @param array $modelsettings The model settings to append to the request object.
     * * @return \stdClass $requestobj The extended request object.
     */
    private function create_ai21_request(
            \stdClass $requestobj,
            string $systeminstruction,
            array $modelsettings
    ): \stdClass {
        $requestobj->n = 1;

        // Create user message object.
        $messageobj = new \stdClass();
        $messageobj->role = 'user';
        $messageobj->content = $this->action->get_configuration('prompttext');

        if (!empty($systeminstruction)) {
            // Create system message object.
            $systemobj = new \stdClass();
            $systemobj->role = 'system';
            $systemobj->content = $systeminstruction;

            $requestobj->messages = [$systemobj, $messageobj];
        } else {
            $requestobj->messages = [$messageobj];
        }

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion') {
                    continue;
                }
                // Correctly format the stopSequences setting.
                if ($setting === 'stop') {
                    $requestobj->$setting = [$value];
                } else {
                    $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }
        }

        return $requestobj;
    }

    #[\Override]
    protected function create_request(): array {
        $requestobj = new \stdClass();
        $systeminstruction = $this->get_system_instruction();
        $modelsettings = $this->get_model_settings();

        // Handle model family specific configuration.
        if (str_contains($this->get_model(), 'amazon')) {
            $requestobj = $this->create_amazon_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'anthropic')) {
            $requestobj = $this->create_anthropic_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'mistral')) {
            $requestobj = $this->create_mistral_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'ai21')) {
            $requestobj = $this->create_ai21_request($requestobj, $systeminstruction, $modelsettings);
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        //$requestobj = $requestobj = [
        //        "messages" => [
        //                [
        //                        "role" => "user",
        //                        "content" => "Hello, world!"
        //                ]
        //        ]
        //];

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
        ];

        // Bedrock contains different response structures for different models.
        if (str_contains($this->get_model(), 'amazon')) {
            $response['generatedcontent'] = $bodyobj->results[0]->outputText;
            $response['finishreason'] = $bodyobj->results[0]->completionReason;
            $response['model'] = $this->get_model();
        } else if (str_contains($this->get_model(), 'anthropic')) {
            $response['generatedcontent'] = $bodyobj->content[0]->text;
            $response['finishreason'] = $bodyobj->stop_reason;
            $response['model'] = $bodyobj->model;

        } else if (str_contains($this->get_model(), 'mistral')) {
            $response['generatedcontent'] = $bodyobj->outputs[0]->text;
            $response['finishreason'] = $bodyobj->outputs[0]->stop_reason;
            $response['model'] = $this->get_model();
        } else if (str_contains($this->get_model(), 'ai21')) {
            $response['generatedcontent'] = $bodyobj->choices[0]->message->content;
            $response['finishreason'] = $bodyobj->choices[0]->finish_reason;
            $response['model'] = $bodyobj->model;
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return $response;
    }
}
