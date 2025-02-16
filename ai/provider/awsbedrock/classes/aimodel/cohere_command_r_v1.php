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
        return 'Cohere Command R';
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        // Temperature – Use a lower value to decrease randomness in responses.
        $mform->addElement(
                'text',
                'temperature',
                get_string('settings_temperature', 'aiprovider_awsbedrock'),
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton('temperature', 'settings_temperature', 'aiprovider_awsbedrock');

        // Top_p – Use a lower value to ignore less probable options and decrease the diversity of responses.
        $mform->addElement(
                'text',
                'p',
                get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('p', PARAM_FLOAT);
        $mform->addHelpButton('p', 'settings_top_p', 'aiprovider_awsbedrock');

        // Top_k – Only sample from the top K options for each subsequent token.
        // Use top_k to remove long tail low probability responses.
        $mform->addElement(
                'text',
                'k',
                get_string('settings_top_k', 'aiprovider_awsbedrock'),
        );
        $mform->setType('k', PARAM_FLOAT);
        $mform->addHelpButton('k', 'settings_top_k', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        $mform->addElement(
                'text',
                'max_tokens',
                get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('max_tokens', 4096);
        $mform->setType('max_tokens', PARAM_INT);
        $mform->addHelpButton('max_tokens', 'settings_max_tokens', 'aiprovider_awsbedrock');

        // Frequency Penalty.
        $mform->addElement(
            'text',
            'frequency_penalty',
            get_string('settings_frequency_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('frequency_penalty', PARAM_FLOAT);
        $mform->addHelpButton('frequency_penalty', 'settings_frequency_penalty', 'aiprovider_awsbedrock');

        // Presence Penalty.
        $mform->addElement(
            'text',
            'presence_penalty',
            get_string('settings_presence_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('presence_penalty', PARAM_FLOAT);
        $mform->addHelpButton('presence_penalty', 'settings_presence_penalty', 'aiprovider_awsbedrock');

        // Seed.
        $mform->addElement(
            'text',
            'seed',
            get_string('settings_seed', 'aiprovider_awsbedrock'),
        );
        $mform->setType('seed', PARAM_INT);
        $mform->addHelpButton('seed', 'settings_seed', 'aiprovider_awsbedrock');


        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
                'text',
                'stop_sequences',
                get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        $mform->setType('stop_sequences', PARAM_TEXT);
        $mform->addHelpButton('stop_sequences', 'settings_stop_sequences', 'aiprovider_awsbedrock');

        // Prompt Truncation.
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
