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

/**
 * External API to set a users AI policy acceptance.
 *
 * @package    core_ai
 * @copyright  Meirza <meirza.arson@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_action extends \core_table\external\dynamic\get {

    /**
     * Creates a new instance of the specified table class.
     *
     * This method takes the class name of a table and a unique ID, extracts the plugin name
     * from the unique ID, and creates a new instance of the table class using the plugin name.
     *
     * @param string $tableclass The fully qualified class name of the table.
     * @param string $uniqueid The unique identifier string, from which the plugin name is extracted.
     */
    public static function create_new_instance(string $tableclass, string $uniqueid) {
        $pluginname = end(explode('-', $uniqueid));
        $instance = new $tableclass($pluginname);
        return $instance;
    }

}
