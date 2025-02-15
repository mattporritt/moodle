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
 * Amazon Nova Pro AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amazon_nova_pro_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'amazon.nova-pro-v1:0';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Amazon Nova Pro';
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        // Temperature – Use a lower value to decrease randomness in responses.
        // Default: 1, min: 0, max: 1.
        $mform->addElement(
                'text',
                'temperature',
                get_string('settings_temperature', 'aiprovider_awsbedrock'),
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton('temperature', 'settings_temperature', 'aiprovider_awsbedrock');

        // Top_p – Use a lower value to ignore less probable options and decrease the diversity of responses.
        // Default: 0.9999, min: 0, max: 1.
        $mform->addElement(
                'text',
                'topP',
                get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('topP', PARAM_FLOAT);
        $mform->addHelpButton('topP', 'settings_top_p', 'aiprovider_awsbedrock');

        // Top_k – Only sample from the top K options for each subsequent token.
        // Use top_k to remove long tail low probability responses.
        // Default: null, min: 0, max: 500.
        $mform->addElement(
                'text',
                'topK',
                get_string('settings_top_k', 'aiprovider_awsbedrock'),
        );
        $mform->setType('topK', PARAM_FLOAT);
        $mform->addHelpButton('topK', 'settings_top_k', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        $mform->addElement(
                'text',
                'maxTokens',
                get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('maxTokens', 4096);
        $mform->setType('maxTokens', PARAM_INT);
        $mform->addHelpButton('maxTokens', 'settings_max_tokens', 'aiprovider_awsbedrock');

        $mform->addElement(
                'text',
                'schemaVersion',
                get_string('settings_schema_version', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('schemaVersion', 'messages-v1');
        $mform->setType('schemaVersion', PARAM_TEXT);
        $mform->addHelpButton('schemaVersion', 'settings_schema_version', 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
