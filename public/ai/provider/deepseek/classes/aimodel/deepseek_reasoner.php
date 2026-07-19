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

/**
 * DeepSeek reasoner AI model.
 *
 * @package    aiprovider_deepseek
 * @copyright  2025 Yusuf Wibisono <yusuf.wibisono@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deepseek_reasoner extends base implements deepseek_base {
    #[\Override]
    public function get_model_name(): string {
        return 'deepseek-reasoner';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'deepseek-reasoner';
    }

    #[\Override]
    public function get_model_settings(): array {
        return [
            // Temperature, logprobs, top_logprobs, and top_p are intentionally left without
            // documented bounds here: DeepSeek's API silently ignores these parameters for
            // deepseek-reasoner, so there is no provider-documented range to display or validate.
            'temperature' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_temperature',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_FLOAT,
                'help' => [
                    'identifier' => 'settings_temperature',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
            'logprobs' => [
                'elementtype' => 'checkbox',
                'label' => [
                    'identifier' => 'settings_logprobs',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_BOOL,
                'help' => [
                    'identifier' => 'settings_logprobs',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
            'top_logprobs' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_top_logprobs',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_FLOAT,
                'help' => [
                    'identifier' => 'settings_top_logprobs',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
            'top_p' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_top_p',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_FLOAT,
                'help' => [
                    'identifier' => 'settings_top_p',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
            // Max tokens – deepseek-reasoner's documented default/maximum output token limit
            // (higher than deepseek-chat because reasoning tokens count towards the output).
            'max_tokens' => self::build_setting(
                'settings_max_tokens',
                'aiprovider_deepseek',
                PARAM_INT,
                'settings_max_tokens',
                ['min' => 1, 'max' => 32768, 'default' => 32768],
            ),
            'frequency_penalty' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_frequency_penalty',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_RAW, // This is a raw value because it can be a float from -2.0 to 2.0.
                'help' => [
                    'identifier' => 'settings_frequency_penalty',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
            'presence_penalty' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_presence_penalty',
                    'component' => 'aiprovider_deepseek',
                ],
                'type' => PARAM_RAW, // This is a raw value because it can be a float from -2.0 to 2.0.
                'help' => [
                    'identifier' => 'settings_presence_penalty',
                    'component' => 'aiprovider_deepseek',
                ],
            ],
        ];
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
