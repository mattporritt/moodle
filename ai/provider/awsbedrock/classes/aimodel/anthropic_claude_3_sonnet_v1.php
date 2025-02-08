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
 * Claude 3 Sonnet  AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class anthropic_claude_3_sonnet_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'anthropic.claude-3-sonnet-20240229-v1:0';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Claude 3 Sonnet';
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
                'top_p',
                get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('top_p', PARAM_FLOAT);
        $mform->addHelpButton('top_p', 'settings_top_p', 'aiprovider_awsbedrock');

        // Top_k – Only sample from the top K options for each subsequent token.
        // Use top_k to remove long tail low probability responses.
        // Default: null, min: 0, max: 500.
        $mform->addElement(
                'text',
                'top_k',
                get_string('settings_top_k', 'aiprovider_awsbedrock'),
        );
        $mform->setType('top_k', PARAM_FLOAT);
        $mform->addHelpButton('top_k', 'settings_top_k', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        $mform->addElement(
                'text',
                'max_tokens',
                get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('max_tokens', 4096);
        $mform->setType('max_tokens', PARAM_INT);
        $mform->addHelpButton('max_tokens', 'settings_max_tokens', 'aiprovider_awsbedrock');

        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
                'text',
                'stop_sequences',
                get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        $mform->setType('stop_sequences', PARAM_TEXT);
        $mform->addHelpButton('stop_sequences', 'settings_stop_sequences', 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
