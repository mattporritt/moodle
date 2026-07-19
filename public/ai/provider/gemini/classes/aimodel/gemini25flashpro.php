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

namespace aiprovider_gemini\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * Gemini 2.5 Flash Pro AI model.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 Anupama Sarjoshi <anupama.sarjoshi@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini25flashpro extends base implements gemini_base {
    #[\Override]
    public function get_model_name(): string {
        return 'gemini-2.5-pro';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Gemini 2.5 Pro';
    }

    #[\Override]
    public function get_model_settings(): array {
        return [
            // Temperature: controls how creative the AI responses are.
            // Documented range: https://ai.google.dev/api/generate-content#v1beta.GenerationConfig.
            'temperature' => self::build_setting(
                'settings_temperature',
                'aiprovider_gemini',
                PARAM_FLOAT,
                'settings_temperature',
                ['min' => 0, 'max' => 2.0, 'default' => 1.0],
            ),
            // Top‑p: controls randomness using nucleus sampling.
            'top_p' => self::build_setting(
                'settings_top_p',
                'aiprovider_gemini',
                PARAM_FLOAT,
                'settings_top_p',
                ['min' => 0, 'max' => 1.0, 'default' => 0.95],
            ),
            // Top‑k: maximum number of tokens considered when sampling.
            'top_k' => self::build_setting(
                'settings_top_k',
                'aiprovider_gemini',
                PARAM_FLOAT,
                'settings_top_k',
                ['min' => 0, 'max' => 100, 'default' => 40],
            ),
            // Max output tokens: limits the number of tokens the model will generate.
            'max_output_tokens' => self::build_setting(
                'settings_max_output_tokens',
                'aiprovider_gemini',
                PARAM_INT,
                'settings_max_output_tokens',
                ['min' => 1, 'max' => 65536, 'default' => 65536],
            ),
            // Stop sequences: character sequences where the AI should stop generating text.
            'stop_sequences' => [
                'elementtype' => 'text',
                'label' => [
                    'identifier' => 'settings_stop_sequences',
                    'component' => 'aiprovider_gemini',
                ],
                'type' => PARAM_TEXT, // String or comma-separated list of sequences.
                'help' => [
                    'identifier' => 'settings_stop_sequences',
                    'component' => 'aiprovider_gemini',
                ],
            ],
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

    #[\Override]
    public function get_model_endpoint(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent';
    }
}
