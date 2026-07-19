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

namespace core_ai\aimodel;

use MoodleQuickForm;

/**
 * Base Model class.
 *
 * @package    core_ai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Get the display name of the model.
     * This name is used to display the model in the UI.
     *
     * @return string The display name of the model.
     */
    abstract public function get_model_display_name(): string;

    /**
     * Get the name of the model.
     * This name is used to identify the model. The system will use this model name to make the request to the AI services.
     *
     * @return string The name of the model.
     */
    abstract public function get_model_name(): string;

    /**
     * Add the model settings to the form.
     *
     * @param MoodleQuickForm $mform The form to add the model settings to.
     */
    public function add_model_settings(MoodleQuickForm $mform): void {
    }

    /**
     * Check if the model has settings.
     *
     * @return bool Whether the model has settings.
     */
    public function has_model_settings(): bool {
        return !empty($this->get_model_settings());
    }

    /**
     * Get all settings that can be configured for a model.
     *
     * @return string[] Array of settings.
     */
    public function get_model_settings(): array {
        return [];
    }

    /**
     * Build a setting definition for use in {@see get_model_settings()}.
     *
     * Centralises the array shape consumed by {@see add_model_settings()} and
     * {@see validate_model_settings()}, so provider model classes only need to supply
     * the label/help identifiers, the PARAM_* type, and optional min/max/default
     * placeholders for the help string.
     *
     * @param string $labelidentifier Label lang string identifier.
     * @param string $component Lang string component (usually the provider plugin's frankenstyle name).
     * @param mixed $type PARAM_* type.
     * @param string|null $helpidentifier Help lang string identifier.
     * @param array $helpa Help string placeholder values, for example ['min' => 0, 'max' => 2, 'default' => 1].
     * @return array
     */
    protected static function build_setting(
        string $labelidentifier,
        string $component,
        mixed $type,
        ?string $helpidentifier = null,
        array $helpa = [],
    ): array {
        $setting = [
            'elementtype' => 'text',
            'label' => [
                'identifier' => $labelidentifier,
                'component' => $component,
            ],
            'type' => $type,
        ];

        if ($helpidentifier !== null) {
            $setting['help'] = [
                'identifier' => $helpidentifier,
                'component' => $component,
            ];
            if (!empty($helpa)) {
                $setting['help']['a'] = $helpa;
            }
        }

        return $setting;
    }

    /**
     * Validate submitted model setting values against this model's documented min/max metadata.
     *
     * Only settings that carry a 'min' and/or 'max' value in their help placeholder array
     * (see {@see build_setting()}) are checked. Settings without documented bounds, and
     * non-numeric submitted values, are left untouched.
     *
     * @param array $data Submitted form data, keyed by setting name.
     * @return array Form errors keyed by setting name, suitable for merging into a moodleform validation() result.
     */
    public function validate_model_settings(array $data): array {
        $errors = [];

        foreach ($this->get_model_settings() as $key => $setting) {
            if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                continue;
            }

            $range = $setting['help']['a'] ?? [];
            if (!array_key_exists('min', $range) && !array_key_exists('max', $range)) {
                continue;
            }

            if (!is_numeric($data[$key])) {
                continue;
            }

            $value = (float) $data[$key];
            $min = $range['min'] ?? null;
            $max = $range['max'] ?? null;

            if (($min !== null && $value < (float) $min) || ($max !== null && $value > (float) $max)) {
                $errors[$key] = get_string('settings_rangeerror', 'core_ai', (object) ['min' => $min, 'max' => $max]);
            }
        }

        return $errors;
    }
}
