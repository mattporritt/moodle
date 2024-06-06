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

namespace core_ai\aiactions;

use coding_exception;
use core_ai\aiactions\responses\response_base;

/**
 * Base Action class.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var int The context ID the action was created in */
    protected int $contextid;

    /** @var int Timestamp the action object was created. */
    protected int $timecreated;

    /**
     * Class constructor.
     *
     * @throws coding_exception
     */
    public function __construct() {
        $this->timecreated = time();
        $this->ensure_configure_method_exists();
    }

    /**
     * Responsible for storing any action specific data in the database.
     *
     * @param response_base $response The response object to store.
     * @return int The id of the stored action.
     */
    abstract public function store(response_base $response): int;

    /**
     * Get the basename of the class.
     * This is used to generate the action name and description.
     *
     * @return string The basename of the class.
     */
    public function get_basename(): string {
        return basename(str_replace('\\', '/', $this::class));
    }

    /**
     * Get the action name.
     * Defaults to the action name string.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_name(): string {
        $stringid = 'action_' . $this->get_basename();
        return get_string($stringid, 'core_ai');
    }

    /**
     * Get the action description.
     * Defaults to the action description string.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_description(): string {
        $stringid = 'action_' . $this->get_basename() . '_desc';
        return get_string($stringid, 'core_ai');
    }

    /**
     * Get the system instruction for the action.
     *
     * @return string The system instruction for the action.
     * @throws coding_exception
     */
    public function get_system_instruction(): string {
        $stringid = 'action_' . $this->get_basename() . '_instruction';

        // If the string doesn't exist, return an empty string.
        if (!get_string_manager()->string_exists($stringid, 'core_ai')) {
            return '';
        }

        return get_string($stringid, 'core_ai');
    }

    /**
     * Get a configuration option.
     *
     * @param string $name The name of the configuration option to get.
     * @return mixed The value of the configuration option.
     */
    public function get_configuration(string $name): mixed {
        return $this->$name;
    }

    /**
     * Ensure the configuration method exists and is correctly defined.
     * We do it this way instead of simply declaring the method as abstract,
     * because each configuration method will have a different signature.
     *
     * @return void
     * @throws coding_exception
     */
    private function ensure_configure_method_exists(): void {
        try {
            $reflection = new \ReflectionMethod($this, 'configure');

            // Check if the method exists.
            if (!$reflection->isPublic()) {
                throw new \coding_exception('The configure method must be public in the subclass.');
            }

            // Check the return type.
            $returntype = $reflection->getReturnType();
            if ($returntype && $returntype->getName() !== 'void') {
                throw new \coding_exception('The configure method must have a void return type in the subclass.');
            }

            // Check if the method at least takes the contextid as a variable.
            $parameters = $reflection->getParameters();
            $parameterexists = false;
            foreach ($parameters as $parameter) {
                if ($parameter->getName() === 'contextid') {
                    $parameterexists = true;
                    break;
                }
            }
            if (!$parameterexists) {
                throw new \coding_exception('The configure method must take a contextid parameter.');
            }
        } catch (\ReflectionException $e) {
            throw new \coding_exception('The configure method must be implemented in the subclass.');
        }
    }
}
