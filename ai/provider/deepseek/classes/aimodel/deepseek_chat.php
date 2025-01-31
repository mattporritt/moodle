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

namespace aiprovider_deepseek\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * DeepSeek chat AI model.
 *
 * @package    aiprovider_deepseek
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deepseek_chat extends base implements deepseek_base {

    #[\Override]
    public function get_model_name(): string {
        return 'deepseek-chat';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'deepseek-chat';
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
            get_string('settings_frequency_penalty', 'aiprovider_deepseek'),
        );
        $mform->setType('frequency_penalty', PARAM_FLOAT);
        $mform->addHelpButton('frequency_penalty', 'settings_frequency_penalty', 'aiprovider_deepseek');

        $mform->addElement(
            'text',
            'temperature',
            get_string('settings_temperature', 'aiprovider_deepseek'),
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->addHelpButton('temperature', 'settings_temperature', 'aiprovider_deepseek');

        $mform->addElement(
            'checkbox',
            'logprobs',
            get_string('settings_logprobs', 'aiprovider_deepseek'),
            get_string('settings_logprobs_label', 'aiprovider_deepseek'),
        );
        $mform->setType('logprobs', PARAM_BOOL);
        $mform->addHelpButton('logprobs', 'settings_logprobs', 'aiprovider_deepseek');

        $mform->addElement(
            'text',
            'top_logprobs',
            get_string('settings_top_logprobs', 'aiprovider_deepseek'),
        );
        $mform->setType('top_logprobs', PARAM_FLOAT);
        $mform->addHelpButton('top_logprobs', 'settings_top_logprobs', 'aiprovider_deepseek');

        $mform->addElement(
            'text',
            'top_p',
            get_string('settings_top_p', 'aiprovider_deepseek'),
        );
        $mform->setType('top_p', PARAM_FLOAT);
        $mform->addHelpButton('top_p', 'settings_top_p', 'aiprovider_deepseek');
        $mform->addElement(
                'text',
                'max_tokens',
                get_string('settings_max_tokens', 'aiprovider_openai'),
        );
        $mform->setType('max_tokens', PARAM_INT);
        $mform->addHelpButton('max_tokens', 'settings_max_tokens', 'aiprovider_openai');

        $mform->addElement(
                'text',
                'frequency_penalty',
                get_string('settings_frequency_penalty', 'aiprovider_openai'),
        );
        // This is a raw value because it can be a float from -2.0 to 2.0.
        $mform->setType('frequency_penalty', PARAM_RAW);
        $mform->addHelpButton('frequency_penalty', 'settings_frequency_penalty', 'aiprovider_openai');

        $mform->addElement(
                'text',
                'presence_penalty',
                get_string('settings_presence_penalty', 'aiprovider_openai'),
        );
        // This is a raw value because it can be a float from -2.0 to 2.0.
        $mform->setType('presence_penalty', PARAM_RAW);
        $mform->addHelpButton('presence_penalty', 'settings_presence_penalty', 'aiprovider_openai');
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
