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

namespace aiprovider_openai;

use core_ai\aimodel\base;

/**
 * Helper class for the OpenAI provider.
 *
 * @package    aiprovider_openai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Migrate an official Chat Completions text action configuration to Responses.
     *
     * Configurations with custom extra parameters are intentionally retained because
     * their compatibility cannot be determined without changing their behaviour.
     *
     * @param array $actionconfig The action configuration to migrate.
     * @return array The migrated action configuration.
     */
    public static function migrate_text_action_config_to_responses(array $actionconfig): array {
        $settings = $actionconfig['settings'] ?? [];
        if (
            ($settings['endpoint'] ?? '') !== 'https://api.openai.com/v1/chat/completions' ||
            isset($settings['modelextraparams'])
        ) {
            return $actionconfig;
        }

        $settings['endpoint'] = 'https://api.openai.com/v1/responses';
        self::migrate_text_model_settings_to_responses($settings);
        $actionconfig['settings'] = $settings;

        $model = $settings['model'] ?? null;
        if ($model !== null && isset($actionconfig['modelsettings'][$model])) {
            $modelsettings = $actionconfig['modelsettings'][$model];
            self::migrate_text_model_settings_to_responses($modelsettings);
            $actionconfig['modelsettings'][$model] = $modelsettings;
        }

        return $actionconfig;
    }

    /**
     * Migrate model settings that are compatible with the Responses API.
     *
     * @param array $settings The model settings to migrate.
     */
    private static function migrate_text_model_settings_to_responses(array &$settings): void {
        if (isset($settings['max_completion_tokens'])) {
            $settings['max_output_tokens'] = $settings['max_completion_tokens'];
            unset($settings['max_completion_tokens']);
        }

        unset($settings['frequency_penalty'], $settings['presence_penalty']);
    }

    /**
     * Get all model classes.
     *
     * @return array Array of model classes.
     */
    public static function get_model_classes(): array {
        $models = [];
        $modelclasses = \core_component::get_component_classes_in_namespace('aiprovider_openai', 'aimodel');
        foreach ($modelclasses as $class => $path) {
            if (!class_exists($class) || !is_a($class, base::class, true)) {
                throw new \coding_exception("Model class not valid: {$class}");
            }
            $models[] = $class;
        }
        return $models;
    }

    /**
     * Get model class by name.
     *
     * @param string $modelname Model name.
     * @return base|null
     */
    public static function get_model_class(string $modelname): ?base {
        foreach (static::get_model_classes() as $classname) {
            $model = new $classname();
            if ($model->get_model_name() === $modelname) {
                return $model;
            }
        }
        return null;
    }
}
