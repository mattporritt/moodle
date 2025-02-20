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

        $mform->addElement(
            'text',
            'frequency_penalty',
            get_string('settings_frequency_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('frequency_penalty', PARAM_FLOAT);
        $mform->addHelpButton('frequency_penalty', 'settings_frequency_penalty', 'aiprovider_awsbedrock');

        $mform->addElement(
            'text',
            'presence_penalty',
            get_string('settings_presence_penalty', 'aiprovider_awsbedrock'),
        );
        $mform->setType('presence_penalty', PARAM_FLOAT);
        $mform->addHelpButton('presence_penalty', 'settings_presence_penalty', 'aiprovider_awsbedrock');

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
