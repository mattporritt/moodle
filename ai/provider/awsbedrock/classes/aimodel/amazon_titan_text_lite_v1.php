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
 * Titan Text G1 - Lite AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amazon_titan_text_lite_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'amazon.titan-text-lite-v1';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Titan Text G1 - Lite ';
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        // Temperature – Use a lower value to decrease randomness in responses.
        // Default: 0.7, min: 0, max: 1.
        $mform->addElement(
            'text',
            'temperature',
            get_string('settings_temperature', 'aiprovider_awsbedrock'),
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton('temperature', 'settings_temperature', 'aiprovider_awsbedrock');

        // TopP – Use a lower value to ignore less probable options and decrease the diversity of responses.
        // Default: 0.9, min: 0, max: 1.
        $mform->addElement(
            'text',
            'topP',
            get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('topP', PARAM_FLOAT);
        $mform->addHelpButton('topP', 'settings_top_p', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        // Default: 512, Min and Max vary by specific model type.
        $mform->addElement(
            'text',
            'maxTokenCount',
            get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setType('maxTokenCount', PARAM_INT);
        $mform->addHelpButton('maxTokenCount', 'settings_max_tokens', 'aiprovider_awsbedrock');

        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
            'text',
            'stopSequences',
            get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        // This is a raw value because it can be a float from -2.0 to 2.0.
        $mform->setType('stopSequences', PARAM_RAW);
        $mform->addHelpButton('stopSequences', 'settings_stop_sequences', 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
