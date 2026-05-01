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
 * TinyMCE Editor post install hook.
 *
 * @package    editor
 * @subpackage tiny
 * @copyright  2022 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enable the TinyMCE Editor on its install.
 */
function xmldb_editor_tiny_install(): void {
    $editormanager = \core_plugin_manager::resolve_plugininfo_class('editor');
    $editormanager::enable_plugin('tiny', true);

    // Initialize standard native plugin settings to enabled.
    // The list is inlined here to keep the install step self-contained.
    $standardplugins = [
        'anchor', 'charmap', 'code', 'codesample', 'directionality', 'emoticons',
        'fullscreen', 'help', 'insertdatetime', 'lists', 'nonbreaking', 'pagebreak',
        'quickbars', 'save', 'searchreplace', 'table', 'visualblocks', 'visualchars',
        'wordcount',
    ];
    foreach ($standardplugins as $pluginname) {
        set_config('standard_plugin_' . $pluginname, 1, 'editor_tiny');
    }
}
