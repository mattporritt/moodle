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

/**
 * Tiny text editor webservice definitions.
 *
 * @package    editor_tiny
 * @copyright  2025 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'editor_tiny_get_configuration' => [
        'classname' => \editor_tiny\external\get_configuration::class,
        'description' => 'Returns the TinyMCE configuration for a context.',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'editor_tiny_set_standard_plugin_state' => [
        'classname' => \editor_tiny\external\set_standard_plugin_state::class,
        'description' => 'Set the enabled/disabled state of a native TinyMCE standard plugin.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
