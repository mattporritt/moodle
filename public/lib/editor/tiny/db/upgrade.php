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
 * Tiny text editor upgrade script.
 *
 * @package    editor_tiny
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run all editor_tiny upgrade steps between the current DB version and the version on disk.
 *
 * @param int $oldversion The old version of the plugin in the DB.
 * @return bool
 */
function xmldb_editor_tiny_upgrade($oldversion) {
    if ($oldversion < 2026050101) {
        // Initialize standard native plugin settings to enabled for existing installs.
        // The list is inlined here to keep the upgrade step self-contained. It represents
        // the configurable set of native TinyMCE plugins at the time this step was written
        // (get_tinymce_plugins() minus the hardcoded-disabled set in get_disabled_tinymce_plugins()).
        $standardplugins = [
            'anchor', 'charmap', 'code', 'codesample', 'directionality', 'emoticons',
            'fullscreen', 'help', 'insertdatetime', 'lists', 'nonbreaking', 'pagebreak',
            'quickbars', 'save', 'searchreplace', 'table', 'visualblocks', 'visualchars',
            'wordcount',
        ];
        foreach ($standardplugins as $pluginname) {
            if (get_config('editor_tiny', 'standard_plugin_' . $pluginname) === false) {
                set_config('standard_plugin_' . $pluginname, 1, 'editor_tiny');
            }
        }
        upgrade_plugin_savepoint(true, 2026050101, 'editor', 'tiny');
    }

    return true;
}
