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
 * AI21 Labs Jamba 1.5 Large AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai21_jamba_1_5_large_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'ai21.jamba-1-5-large-v1:0';
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

        // Reduce frequency of repeated words within a single response message by increasing this number.
        // This penalty gradually increases the more times a word appears during response generation.
        // Min: 0, Max: 2.0, Default: 0.
        $mform->addElement(
            'text',
            'frequency_penalty',
            get_string('settings_frequency_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('frequencyPenalty', 0);
        $mform->setType('frequency_penalty', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'frequency_penalty',
            identifier: 'settings_frequency_penalty_jamba',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 2.0, 'default' => 0]
        );

        // Reduce the frequency of repeated words within a single message by increasing this number.
        // Unlike frequency penalty, presence penalty is the same no matter how many times a word appears.
        // Min: 0, Max: 5.0, Default: 0.
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
            a: ['min' => 0, 'max' => 5.0, 'default' => 0]
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

        // Stop Sequences – Specify a character sequence to indicate where the model should stop.
        $mform->addElement(
            'text',
            'stop',
            get_string('settings_stop_sequences', 'aiprovider_awsbedrock'),
        );
        $mform->setType('stop', PARAM_TEXT);
        $mform->addHelpButton('stop', 'settings_stop_sequences', 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
