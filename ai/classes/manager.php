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

}
