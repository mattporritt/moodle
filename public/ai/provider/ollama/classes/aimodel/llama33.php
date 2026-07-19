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

namespace aiprovider_ollama\aimodel;

use core_ai\aimodel\base;

/**
 * Llama 3.3 AI model.
 *
 * @package    aiprovider_ollama
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llama33 extends base implements ollama_base {
    #[\Override]
    public function get_model_name(): string {
        return 'llama3.3';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Llama 3.3';
    }

    #[\Override]
    public function get_model_settings(): array {
        return [
            // Mirostat – documented enum: https://docs.ollama.com/modelfile.
            'mirostat' => self::build_setting(
                'settings_mirostat',
                'aiprovider_ollama',
                PARAM_INT,
                'settings_mirostat',
                ['min' => 0, 'max' => 2, 'default' => 0],
            ),
            // Temperature – Ollama documents a default but does not enforce a strict min/max.
            'temperature' => self::build_setting(
                'settings_temperature',
                'aiprovider_ollama',
                PARAM_FLOAT,
                'settings_temperature',
                ['default' => 0.8],
            ),
            // Seed – Ollama documents a default but any integer is valid.
            'seed' => self::build_setting(
                'settings_seed',
                'aiprovider_ollama',
                PARAM_INT,
                'settings_seed',
                ['default' => 0],
            ),
            // Top k – Ollama documents a default but does not enforce a strict min/max.
            'top_k' => self::build_setting(
                'settings_top_k',
                'aiprovider_ollama',
                PARAM_FLOAT,
                'settings_top_k',
                ['default' => 40],
            ),
            // Top p – nucleus sampling probability, bounded between 0.0 and 1.0.
            'top_p' => self::build_setting(
                'settings_top_p',
                'aiprovider_ollama',
                PARAM_FLOAT,
                'settings_top_p',
                ['min' => 0, 'max' => 1.0, 'default' => 0.9],
            ),
        ];
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_TEXT;
    }
}
