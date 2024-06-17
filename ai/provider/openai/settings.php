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
 * Plugin administration pages are defined here.
 *
 * @package     aiprovider_openai
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Provider specific settings.
    $settings = new admin_settingpage('aiprovider_openai',
            new lang_string('pluginname', 'aiprovider_openai'), 'moodle/site:config', true);

    $settings->add(new admin_setting_heading('aiprovider_openai/general',
            new lang_string('providersettings', 'core_ai'),
            new lang_string('providersettings_desc', 'core_ai')));

    // Setting to store OpenAI API key.
    $settings->add(new admin_setting_configpasswordunmask('aiprovider_openai/apikey',
            new lang_string('apikey', 'aiprovider_openai'),
            new lang_string('apikey_desc', 'aiprovider_openai'),
            ''));

    // Setting to store OpenAI organization ID.
    $settings->add(new admin_setting_configtext('aiprovider_openai/orgid',
            new lang_string('orgid', 'aiprovider_openai'),
            new lang_string('orgid_desc', 'aiprovider_openai'),
            '',
            PARAM_TEXT));

    $settings->add(new admin_setting_heading('aiprovider_openai/generals',
            new lang_string('provideractionsettings', 'core_ai'),
            new lang_string('provideractionsettings_desc', 'core_ai')));

    // Get the list of actions that this provider supports.
    $actions = \core_ai\manager::get_supported_actions('aiprovider_openai');
    // Load the setting table of actions that this provider supports.
    $settings->add(new \core_ai\admin\admin_setting_action_manager(
            'aiprovider_openai',
            $actions,
            \core_ai\admin\tables\aiprovider_action_management_table::class,
            'manageaiproviders',
            new lang_string('manageaiproviders', 'core_ai'),
    ));


}
