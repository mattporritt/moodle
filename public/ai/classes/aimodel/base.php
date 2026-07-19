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
     * Renders each setting from {@see get_model_settings()}. A 'checkbox' elementtype is wrapped
     * in its own group, so its help button attaches to the group rather than the bare checkbox;
     * every other elementtype is added directly.
     *
     * @param MoodleQuickForm $mform The form to add the model settings to.
     */
    public function add_model_settings(MoodleQuickForm $mform): void {
        foreach ($this->get_model_settings() as $key => $setting) {
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
            $min = array_key_exists('min', $range) ? $range['min'] : null;
            $max = array_key_exists('max', $range) ? $range['max'] : null;
            if ($min === null && $max === null) {
                continue;
            }

            // PARAM_FLOAT/PARAM_RAW settings hold decimal values that may be submitted using the
            // current language's decimal separator (for example "3,5"), which is_numeric() rejects
            // outright. Normalise those the same way Moodle normally handles float form input, so
            // a locale-formatted value cannot silently skip range validation.
            if (in_array($setting['type'] ?? null, [PARAM_FLOAT, PARAM_RAW], true)) {
                $value = unformat_float($data[$key], true);
                if ($value === false) {
                    continue;
                }
            } else if (is_numeric($data[$key])) {
                $value = (float) $data[$key];
            } else {
                continue;
            }

            if (($min !== null && $value < (float) $min) || ($max !== null && $value > (float) $max)) {
                $errors[$key] = $this->get_range_error($min, $max);
            }
        }

        return $errors;
    }

    /**
     * Build the range-error message for a setting, selecting the string variant that matches
     * which bounds are documented for it.
     *
     * @param mixed $min Documented minimum, if any.
     * @param mixed $max Documented maximum, if any.
     * @return string
     */
    protected function get_range_error(mixed $min, mixed $max): string {
        if ($min !== null && $max !== null) {
            return get_string('settings_rangeerror', 'core_ai', (object) ['min' => $min, 'max' => $max]);
        } else if ($min !== null) {
            return get_string('settings_rangeerror_min', 'core_ai', (object) ['min' => $min]);
        }

        return get_string('settings_rangeerror_max', 'core_ai', (object) ['max' => $max]);
    }
}
