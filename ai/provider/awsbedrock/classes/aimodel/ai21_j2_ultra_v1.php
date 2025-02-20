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
 * AI21 Labs Jurassic 2 Ultra AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai21_j2_ultra_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'ai21.j2-ultra-v1';
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
            'topP',
            get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('topP', PARAM_FLOAT);
        $mform->addHelpButton('topP', 'settings_top_p', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        $mform->addElement(
            'text',
            'maxTokens',
            get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('maxTokens', 4096);
        $mform->setType('maxTokens', PARAM_INT);
        $mform->addHelpButton('maxTokens', 'settings_max_tokens', 'aiprovider_awsbedrock');

        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
            'text',
            'stopSequences',
            get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        $mform->setType('stopSequences', PARAM_TEXT);
        $mform->addHelpButton('stopSequences', 'settings_stop_sequences', 'aiprovider_awsbedrock');

        // Presence penalty – Reduce the frequency of repeated words within a single message by increasing this number.
        $mform->addElement(
            'text',
            'presencePenalty',
            get_string('settings_presence_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('presencePenalty', PARAM_FLOAT);
        $mform->addHelpButton('presencePenalty', 'settings_presence_penalty', 'aiprovider_awsbedrock');

        // Count Penalty –  Use a higher value to lower the probability of generating new tokens that already appear at least once.
        $mform->addElement(
            'text',
            'countPenalty',
            get_string('settings_count_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('countPenalty', PARAM_FLOAT);
        $mform->addHelpButton('countPenalty', 'settings_count_penalty', 'aiprovider_awsbedrock');

        // Frequency penalty – Penalizes new tokens based on their frequency in the text so far. Resulting in fewer repeated words.
        $mform->addElement(
            'text',
            'frequencyPenalty',
            get_string('settings_frequency_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('frequencyPenalty', PARAM_FLOAT);
        $mform->addHelpButton('frequencyPenalty', 'settings_frequency_penalty', 'aiprovider_awsbedrock');
       }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
