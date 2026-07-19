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
    public function get_model_settings(): array {
        return [
            // Temperature – documented range: https://api-docs.deepseek.com/api/create-chat-completion.
            // Uses the "_bounds" help string variant (with {$a->...} placeholders) because this
            // model supplies documented values; deepseek-reasoner uses the plain "settings_temperature"
            // help string instead, since DeepSeek ignores this parameter for that model.
            'temperature' => self::build_setting(
                'settings_temperature',
                'aiprovider_deepseek',
                PARAM_FLOAT,
                'settings_temperature_bounds',
                ['min' => 0, 'max' => 2.0, 'default' => 1.0],
            ),
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
            // Top logprobs – documented range: https://api-docs.deepseek.com/api/create-chat-completion.
            'top_logprobs' => self::build_setting(
                'settings_top_logprobs',
                'aiprovider_deepseek',
                PARAM_FLOAT,
                'settings_top_logprobs_bounds',
                ['min' => 0, 'max' => 20],
            ),
            // Top P – documented range: https://api-docs.deepseek.com/api/create-chat-completion.
            'top_p' => self::build_setting(
                'settings_top_p',
                'aiprovider_deepseek',
                PARAM_FLOAT,
                'settings_top_p_bounds',
                ['min' => 0, 'max' => 1.0, 'default' => 1.0],
            ),
            // Max tokens – deepseek-chat's documented default/maximum output token limit.
            'max_tokens' => self::build_setting(
                'settings_max_tokens',
                'aiprovider_deepseek',
                PARAM_INT,
                'settings_max_tokens',
                ['min' => 1, 'max' => 8192, 'default' => 4096],
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
    public function add_model_settings(MoodleQuickForm $mform): void {
        $settings = $this->get_model_settings();
        foreach ($settings as $key => $setting) {
            if ($setting['elementtype'] === 'checkbox') {
                $groupname = $key . '_group';
                $mform->addGroup([
                        $mform->createElement(
                            'checkbox',
                            $key,
                            get_string($setting['label']['identifier'] . '_label', $setting['label']['component']),
                            '',
                            ['class' => 'pt-1'],
                        ),
                    ], $groupname, get_string($setting['label']['identifier'], $setting['label']['component']));
                $mform->setType($key, $setting['type']);
                if (isset($setting['help'])) {
                    $mform->addHelpButton(
                        elementname: $groupname,
                        identifier: $setting['help']['identifier'],
                        component: $setting['help']['component'],
                        a: $setting['help']['a'] ?? [],
                    );
                }
            } else {
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
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
