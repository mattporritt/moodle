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

namespace aiprovider_openai\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * GPT-4o AI model.
 *
 * @package    aiprovider_openai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gpt4o extends base implements openai_base {
    #[\Override]
    public function get_model_name(): string {
        return 'gpt-4o';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'GPT-4o';
    }

    #[\Override]
    public function get_model_settings(): array {
        return [
            // Top P – nucleus sampling. Documented range: https://platform.openai.com/docs/api-reference/chat/create.
            // Uses the "_bounds" help string variant (with {$a->...} placeholders) because this
            // model supplies documented values; o1 uses the plain "settings_top_p" help string
            // instead, since OpenAI fixes top_p for o1 and it has no effect.
            'top_p' => self::build_setting(
                'settings_top_p',
                'aiprovider_openai',
                PARAM_FLOAT,
                'settings_top_p_bounds',
                ['min' => 0, 'max' => 1.0, 'default' => 1.0],
            ),
            // Max completion tokens – gpt-4o's documented maximum output token limit.
            'max_completion_tokens' => self::build_setting(
                'settings_max_completion_tokens',
                'aiprovider_openai',
                PARAM_INT,
                'settings_max_completion_tokens',
                ['min' => 1, 'max' => 16384],
            ),
            // Frequency Penalty – documented range: https://platform.openai.com/docs/api-reference/chat/create.
            'frequency_penalty' => self::build_setting(
                'settings_frequency_penalty',
                'aiprovider_openai',
                PARAM_RAW, // This is a raw value because it can be a float from -2.0 to 2.0.
                'settings_frequency_penalty',
                ['min' => -2.0, 'max' => 2.0, 'default' => 0],
            ),
            // Presence Penalty – documented range: https://platform.openai.com/docs/api-reference/chat/create.
            'presence_penalty' => self::build_setting(
                'settings_presence_penalty',
                'aiprovider_openai',
                PARAM_RAW, // This is a raw value because it can be a float from -2.0 to 2.0.
                'settings_presence_penalty',
                ['min' => -2.0, 'max' => 2.0, 'default' => 0],
            ),
        ];
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        $settings = $this->get_model_settings();
        foreach ($settings as $key => $setting) {
            $mform->addElement(
                $setting['elementtype'],
                $key,
                get_string($setting['label']['identifier'], $setting['label']['component']),
            );
            $mform->setType($key, $setting['type']);
            if (isset($setting['help'])) {
                $mform->addHelpButton(
                    elementname: $key,
                    identifier: $setting['help']['identifier'],
                    component: $setting['help']['component'],
                    a: $setting['help']['a'] ?? [],
                );
            }
        }
    }

    #[\Override]
    public function model_type(): array {
        return [self::MODEL_TYPE_TEXT];
    }
}
