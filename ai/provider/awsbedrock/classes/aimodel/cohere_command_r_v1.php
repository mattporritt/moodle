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

namespace aiprovider_awsbedrock\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * Cohere Command R AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohere_command_r_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'cohere.command-r-v1:0';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return get_string("model_{$this->get_model_name()}", 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        // Temperature – Use a lower value to decrease randomness in responses.
        // Min: 0, Max: 1, Default: 0.3.
        $mform->addElement(
            'text',
            'temperature',
            get_string('settings_temperature', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('temperature', 0.3);
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'temperature',
            identifier: 'settings_temperature',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1, 'default' => 0.3]
        );

        // Top_p – Use a lower value to ignore less probable options and decrease the diversity of responses.
        // Min: 0.01, Max: 0.99, Default: 0.75.
        $mform->addElement(
            'text',
            'p',
            get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('p', 0.75);
        $mform->setType('p', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'p',
            identifier: 'settings_top_p',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0.01, 'max' => 0.99, 'default' => 0.75]
        );

        // Top_k – Only sample from the top K options for each subsequent token.
        // Use top_k to remove long tail low probability responses.
        // Min: 0, Max: 500, Default: 0.
        $mform->addElement(
            'text',
            'k',
            get_string('settings_top_k', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('k', 0);
        $mform->setType('k', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'k',
            identifier: 'settings_top_k',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 500, 'default' => 0]
        );

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        // Min: 0, Max: 4096, Default: 4096.
        $mform->addElement(
            'text',
            'max_tokens',
            get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('max_tokens', 4096);
        $mform->setType('max_tokens', PARAM_INT);
        $mform->addHelpButton(
            elementname: 'max_tokens',
            identifier: 'settings_max_tokens',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 4096, 'default' => 4096]
        );

        // Frequency Penalty - Used to reduce repetitiveness of generated tokens.
        // The higher the value, the stronger a penalty is applied to previously present tokens,
        // proportional to how many times they have already appeared in the prompt or prior generation.
        // Min: 0, Max: 1, Default: 0.
        $mform->addElement(
            'text',
            'frequency_penalty',
            get_string('settings_frequency_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('frequency_penalty', 0);
        $mform->setType('frequency_penalty', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'frequency_penalty',
            identifier: 'settings_frequency_penalty',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1, 'default' => 0]
        );

        // Presence Penalty - Used to reduce repetitiveness of generated tokens.
        // Min: 0, Max: 1, Default: 0.
        $mform->addElement(
            'text',
            'presence_penalty',
            get_string('settings_presence_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('presence_penalty', 0);
        $mform->setType('presence_penalty', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'presence_penalty',
            identifier: 'settings_presence_penalty',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1, 'default' => 0]
        );

        // Seed - If specified, the backend will make a best effort to sample tokens deterministically,
        // such that repeated requests with the same seed and parameters should return the same result.
        // However, determinism cannot be totally guaranteed.
        // Min: 0, Max: 1024, Default: null.
        $mform->addElement(
            'text',
            'seed',
            get_string('settings_seed', 'aiprovider_awsbedrock'),
        );
        $mform->setType('seed', PARAM_INT);
        $mform->addHelpButton(
            elementname: 'seed',
            identifier: 'settings_seed',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1024, 'default' => 'Null']
        );

        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
            'text',
            'stop_sequences',
            get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        $mform->setType('stop_sequences', PARAM_TEXT);
        $mform->addHelpButton('stop_sequences', 'settings_stop_sequences', 'aiprovider_awsbedrock');

        // Prompt Truncation - Dictates how the prompt is constructed.
        $mform->addElement(
            'select',
            'prompt_truncation',
            get_string('settings_prompt_truncation', 'aiprovider_awsbedrock'),
            [
                'OFF' => get_string('settings_prompt_truncation_off', 'aiprovider_awsbedrock'),
                'AUTO_PRESERVE_ORDER' => get_string('settings_prompt_truncation_auto', 'aiprovider_awsbedrock'),
            ],
        );
        $mform->setDefault('prompt_truncation', 'OFF');
        $mform->addHelpButton('prompt_truncation', 'settings_prompt_truncation', 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
