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
 * Meta Llama 3 8B Instruct  AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meta_llama3_8b_instruct_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'meta.llama3-8b-instruct-v1:0';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Meta Llama 3 8B Instruct ';
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
                'top_p',
                get_string('settings_top_p', 'aiprovider_awsbedrock'),
        );
        $mform->setType('top_p', PARAM_FLOAT);
        $mform->addHelpButton('top_p', 'settings_top_p', 'aiprovider_awsbedrock');

        // Max token count – The maximum number of tokens to generate in the response. Maximum token limits are strictly enforced.
        $mform->addElement(
                'text',
                'max_gen_len',
                get_string('settings_max_tokens', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('max_gen_len', 2048);
        $mform->setType('max_gen_len', PARAM_INT);
        $mform->addHelpButton('max_gen_len', 'settings_max_tokens', 'aiprovider_awsbedrock');
      }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
