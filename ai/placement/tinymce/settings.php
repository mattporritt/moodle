<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for the TinyMCE placement.
 *
 * @package     aiplacement_tinymce
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Placement specific settings.
    $settings = new admin_settingpage('aiplacement_tinymce',
            new lang_string('pluginname', 'aiplacement_tinymce'), 'moodle/site:config', true);

    $settings->add(new admin_setting_heading('aiplacement_tinymce/general',
            new lang_string('placementsettings', 'core_ai'),
            new lang_string('placementsettings_desc', 'core_ai')));

    // Enable/disable image generation setting.
    $settings->add(new admin_setting_configcheckbox('aiplacement_tinymce/generateimage',
            new lang_string('generateimagesetting', 'aiplacement_tinymce'),
            new lang_string('generateimagesetting_desc', 'aiplacement_tinymce'),
            1));

    // Enable/disable text generation setting.
    $settings->add(new admin_setting_configcheckbox('aiplacement_tinymce/generatetext',
            new lang_string('generatetextsetting', 'aiplacement_tinymce'),
            new lang_string('generatetextsetting_desc', 'aiplacement_tinymce'),
            1));
}
