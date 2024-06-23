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

namespace core_ai;

use core_ai\actions\base;

/**
 * AI subsystem manager.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Get communication provider class name from the plugin name.
     *
     * @param string $plugin The component name.
     * @throws \coding_exception If the plugin name does not start with 'aiprovider_' or 'aiplacement_'.
     * @return string The class name of the provider.
     */
    private function get_ai_plugin_classname(string $plugin): string {
        if (strpos($plugin, 'aiprovider_') === 0) {
            return "{$plugin}\\provider";
        } elseif (strpos($plugin, 'aiplacement_') === 0) {
            return "{$plugin}\\placement";
        } else {
            // Explode if neither.
            throw new \coding_exception("Plugin name does not start with 'aiprovider_' or 'aiplacement_': " . $plugin);
        }
    }

    /**
     * Get the list of actions that this provider or placement supports,
     * given the name of the plugin.
     *
     * @param string $pluginname The name of the plugin to get the actions for.
     * @throws \coding_exception
     * @return array An array of action class names.
     */
    public static function get_supported_actions(string $pluginname): array {
        $instance = new self();
        $pluginclassname = $instance->get_ai_plugin_classname($pluginname);
        $plugin = new $pluginclassname();
        return $plugin->get_supported_actions();
    }

    /**
     * Given a list of actions get the provider plugins that support them.
     * Will return an array of arrays, indexed by action name.
     *
     * @param array $actions An array of action class names.
     * @throws \coding_exception
     * @return array An array of provider instances indexed by action name.
     */
    public static function get_providers_for_actions(array $actions): array {
        $instance = new self();
        $providers = [];
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('aiprovider');
        foreach ($actions as $action) {
            $providers[$action] = [];
            foreach ($plugins as $plugin) {
                $pluginclassname = $instance->get_ai_plugin_classname($plugin->component);
                $plugin = new $pluginclassname();
                if (in_array($action, $plugin->get_action_list())) {
                    $providers[$action][] = $plugin;
                }
            }
        }
        return $providers;
    }

    /**
     * Given an action name, return an instance of the action.
     *
     * @param string $actionname
     * @return base
     */
    public static function get_action(string $actionname): base {
        $classname = '\\core_ai\\actions\\' . $actionname;

        return new $classname();
    }

    /**
     * Call the action provider.
     * The named provider will process the action and return the result.
     *
     * @param provider $provider The provider to call.
     * @param string $methodname The method to call on the provider for the action.
     * @param base $action The action to process.
     * @return \stdClass The result of the action.
     */
    protected function call_action_provider(
            provider $provider,
            string $methodname,
            base $action
    ): \stdClass {
        return $provider->$methodname($action);
    }

    /**
     * Process an action.
     *
     * @param base $action The action to process.
     * @throws \coding_exception
     * @return \stdClass The result of the action.
     */
    public function process_action(base $action): \stdClass {
        // Get the action base name.
        $actionname = $action->get_basename();
        $methodname = 'process_action_' . $actionname;

        // Get the providers that support the action.
        $providers = self::get_providers_for_actions([$actionname]);

        // Loop through the providers and process the action.
        foreach ($providers[$actionname] as $provider) {
            $result = $this->call_action_provider($provider, $methodname, $action);
            if ($result) {
                return $result;
            }
        }

        // Return the result.

        return new \stdClass();
    }
}
