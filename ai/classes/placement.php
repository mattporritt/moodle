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
 * Class placement.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class placement {

    abstract protected function get_action_list(): array;

    /**
     * Get the actions that this placement supports.
     * Returns an array of instance objects indexed by action name.
     *
     * @return array An array of action class names.
     */
    public function get_supported_actions(): array{
        // Get the list of actions that this placement supports.
        $actions = $this->get_action_list();

        // Create an array of action instances.
        $action_instances = [];
        foreach($actions as $action){
            $action_instances[$action] = manager::get_action($action);
        }

        return $action_instances;
    }

    /**
     * Given an action class name, return an array of sub actions
     * that this placement supports.
     *
     * @param string $classname The action class name.
     * @return array An array of supported sub actions.
     */
    public function get_sub_actions(string $classname): array{
        return [];
    }
}
