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
 * Settings for the course assist placement.
 *
 * @package     aiplacement_courseassist
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Placement specific settings.
    $settings = new admin_settingpage('aiplacement_courseassist',
            new lang_string('pluginname', 'aiplacement_courseassist'), 'moodle/site:config', true);

    $settings->add(new admin_setting_heading('aiplacement_courseassist/general',
            new lang_string('placementsettings', 'core_ai'),
            new lang_string('placementsettings_desc', 'core_ai')));

    // Placeholder setting.
    $settings->add(new admin_setting_configcheckbox('aiplacement_courseassist/placeholder',
            'Enable awesome mode',
            'This is a placeholder setting, for demo purposes only.',
            0));
}
