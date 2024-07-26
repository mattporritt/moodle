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

namespace core_ai\external;

use core_ai\manager;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External API to set provider action enabled.
 *
 * @package    core_ai
 * @copyright  Meirza <meirza.arson@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_action extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'plugin' => new external_value(PARAM_PLUGIN, 'The name of the plugin', VALUE_REQUIRED),
                'action' => new external_value(PARAM_NOTAGS, 'The name of the action', VALUE_REQUIRED),
                'state' => new external_value(PARAM_INT, 'The target state', VALUE_REQUIRED),
            ]
        );
    }

    public static function execute(
        string $plugin,
        string $action,
        int $state,
    ): array {
        // Parameter validation.
        [
            'plugin' => $plugin,
            'action' => $action,
            'state' => $state,
        ] = self::validate_parameters(self::execute_parameters(), [
            'plugin' => $plugin,
            'action' => $action,
            'state' => $state,
        ]);

        // Init the current action class.
        $actionclass = new $action;

        if (!empty($state)) {
            \core\notification::add(
                get_string('plugin_enabled', 'core_admin', $actionclass->get_name()),
                \core\notification::SUCCESS
            );
        } else {
            \core\notification::add(
                get_string('plugin_disabled', 'core_admin', $actionclass->get_name()),
                \core\notification::SUCCESS
            );
        }

        manager::enable_action($plugin, $action, $state);

        return [];
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([]);
    }
}
