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
     * Create the request object for the AI21 Jamba models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_ai21_jamba_request(
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
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
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
     * Create the request object for the AI21 Jurassic models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_ai21_jurassic_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        if (!empty($systeminstruction)) {
            $requestobj->prompt = $this->action->get_configuration('prompttext') . ' ' . $systeminstruction;
        } else {
            $requestobj->prompt = $this->action->get_configuration('prompttext');
        }

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
                    continue;
                }
                // Correctly format the stopSequences setting.
                if ($setting === 'stopSequences') {
                    $requestobj->$setting = [$value];
                } else if (str_contains($setting, 'Penalty')) {
                    $scale = new \stdClass();
                    $scale->scale = $value + 0;
                    $requestobj->$setting = $scale;
                } else {
                    $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }
        }

        return $requestobj;
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
            $systemobj = new \stdClass();
            $systemobj->text = $systeminstruction;
            $requestobj->system = [$systemobj];
        }

        // Create message object.
        $messageobj = new \stdClass();
        $messageobj->text = $this->action->get_configuration('prompttext');

        // Create the user object.
        $userobj = new \stdClass();
        $userobj->role = 'user';
        $userobj->content = [$messageobj];

        $requestobj->messages = [$userobj];

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            $modelobj = new \stdClass();

            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
                    continue;
                }
                // Handle schema version.
                if ($setting === 'schemaVersion') {
                    $requestobj->schemaVersion = $value;
                } else {
                    $modelobj->$setting = is_numeric($value) ? ($value + 0) : $value;
                }
            }

            // Only add the model settings if we have any.
            if(!empty((array)$modelobj)) {
                $requestobj->inferenceConfig = $modelobj;
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
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
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
     * Create the request object for the Cohere models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_cohere_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        if (!empty($systeminstruction)) {
            $requestobj->preamble = $systeminstruction;
        }
        $requestobj->message = $this->action->get_configuration('prompttext');

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
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
     * Create the request object for the Meta models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param string $systeminstruction The system instruction to append to the request object.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_meta_request(
        \stdClass $requestobj,
        string $systeminstruction,
        array $modelsettings
    ): \stdClass {
        $prompt = '<|begin_of_text|>';
        if (!empty($systeminstruction)) {

            $prompt .= '<|start_header_id|>system<|end_header_id|>'
                . $systeminstruction
                . '<|eot_id|>';
        }

        $prompt .=  '<|start_header_id|>user<|end_header_id|>'
            . $this->action->get_configuration('prompttext')
            . '<|eot_id|><|start_header_id|>assistant<|end_header_id|>';

        $requestobj->prompt = $prompt;

        // Append the extra model settings.
        if (!empty($modelsettings)) {
            foreach ($modelsettings as $setting => $value) {
                // Skip if the setting is the aws region.
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
                    continue;
                }
                // Correctly format the stopSequences setting.
                $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
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
                if ($setting === 'awsregion' || $setting === 'cross_region_inference') {
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
        if (str_contains($this->get_model(), 'ai21.jamba')) {
            $requestobj = $this->create_ai21_jamba_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'ai21.j2')) {
            $requestobj = $this->create_ai21_jurassic_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'amazon')) {
            $requestobj = $this->create_amazon_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'anthropic')) {
            $requestobj = $this->create_anthropic_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'cohere')) {
            $requestobj = $this->create_cohere_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'meta')) {
            $requestobj = $this->create_meta_request($requestobj, $systeminstruction, $modelsettings);
        } else if (str_contains($this->get_model(), 'mistral')) {
            $requestobj = $this->create_mistral_request($requestobj, $systeminstruction, $modelsettings);
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return [
            'ContentType' => 'application/json',
            'Accept' => 'application/json',
            'modelId' => $modelsettings['cross_region_inference'] ?? $this->get_model(), // Handle cross region inference.
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
        if (str_contains($this->get_model(), 'ai21.jamba')) {
            $response['generatedcontent'] = $bodyobj->choices[0]->message->content;
            $response['finishreason'] = $bodyobj->choices[0]->finish_reason;
            $response['model'] = $bodyobj->model;
        } else if (str_contains($this->get_model(), 'ai21.j2')) {
            $response['generatedcontent'] = $bodyobj->completions[0]->data->text;
            $response['finishreason'] = $bodyobj->completions[0]->finishReason->reason;
            $response['model'] = $bodyobj->model;
        } else if (str_contains($this->get_model(), 'amazon')) {
            $response['generatedcontent'] = $bodyobj->output->message->content[0]->text;
            $response['finishreason'] = $bodyobj->stopReason;
            $response['model'] = $this->get_model();
        } else if (str_contains($this->get_model(), 'anthropic')) {
            $response['generatedcontent'] = $bodyobj->content[0]->text;
            $response['finishreason'] = $bodyobj->stop_reason;
            $response['model'] = $bodyobj->model;
        } else if (str_contains($this->get_model(), 'cohere')) {
            $response['generatedcontent'] = $bodyobj->text;
            $response['finishreason'] = $bodyobj->finish_reason;
            $response['model'] = $this->get_model();
        } else if (str_contains($this->get_model(), 'meta')) {
            $response['generatedcontent'] = $bodyobj->generation;
            $response['finishreason'] = $bodyobj->stop_reason;
            $response['model'] = $this->get_model();
        } else if (str_contains($this->get_model(), 'mistral')) {
            $response['generatedcontent'] = $bodyobj->outputs[0]->text;
            $response['finishreason'] = $bodyobj->outputs[0]->stop_reason;
            $response['model'] = $this->get_model();
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return $response;
    }
}
