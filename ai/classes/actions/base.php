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

namespace core_ai\actions;

use coding_exception;

/**
 * Base Action class.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /**
     * Get the basename of the class.
     * This is used to generate the action name and description.
     *
     * @return string The basename of the class.
     */
    private function get_basename(): string {
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
}
