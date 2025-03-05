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
 * Mistral 7B Instruct AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mistral_7b_instruct_v0 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'mistral.mistral-7b-instruct-v0:2';
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
        // Min: 0, Max: 1, Default: 0.5.
        $mform->addElement(
            'text',
            'temperature',
            get_string('settings_temperature', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('temperature', 0.5);
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'temperature',
            identifier: 'settings_temperature',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1, 'default' => 0.5]
        );

        // Top_p – Use a lower value to ignore less probable options and decrease the diversity of responses.
        // Min: 0, Max: 1, Default: 0.9.
        $mform->addElement(
            'text',
            'top_p',
            get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('top_p', 0.9);
        $mform->setType('top_p', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'top_p',
            identifier: 'settings_top_p',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 1, 'default' => 0.9]
        );

        // Top_k – Only sample from the top K options for each subsequent token.
        // Use top_k to remove long tail low probability responses.
        // Min: 1, Max: 200, Default: 50.
        $mform->addElement(
            'text',
            'top_k',
            get_string('settings_top_k', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('top_k', 50);
        $mform->setType('top_k', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'top_k',
            identifier: 'settings_top_k',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 1, 'max' => 200, 'default' => 50]
        );

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        // Min: 1, Max: 8192, Default: 4096.
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
            a: ['min' => 1, 'max' => 8192, 'default' => 4096]
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
