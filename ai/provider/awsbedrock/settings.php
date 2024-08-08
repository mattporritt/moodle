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
 * @package     aiprovider_awsbedrock
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Provider specific settings heading.
    $settings = new admin_settingpage('aiprovider_awsbedrock',
            new lang_string('pluginname', 'aiprovider_awsbedrock'), 'moodle/site:config', true);

    $settings->add(new admin_setting_heading('aiprovider_awsbedrock/general',
            new lang_string('providersettings', 'core_ai'),
            new lang_string('providersettings_desc', 'core_ai')));

    // Setting to store AWS API key.
    $settings->add(new admin_setting_configtext('aiprovider_awsbedrock/apikey',
            new lang_string('apikey', 'aiprovider_awsbedrock'),
            new lang_string('apikey_desc', 'aiprovider_awsbedrock'),
            ''));

    // Setting to store AWS secret.
    $settings->add(new admin_setting_configpasswordunmask('aiprovider_awsbedrock/apisecret',
            new lang_string('apisecret', 'aiprovider_awsbedrock'),
            new lang_string('apisecret_desc', 'aiprovider_awsbedrock'),
            '',
            PARAM_TEXT));

    // Setting to enable/disable global rate limiting.
    $settings->add(new admin_setting_configcheckbox('aiprovider_awsbedrock/enableglobalratelimit',
            new lang_string('enableglobalratelimit', 'aiprovider_awsbedrock'),
            new lang_string('enableglobalratelimit_desc', 'aiprovider_awsbedrock'),
            0));

    // Setting to set how many requests per hour are allowed for the global rate limit.
    // Should only be enabled when global rate limiting is enabled.
    $settings->add(new admin_setting_configtext('aiprovider_awsbedrock/globalratelimit',
            new lang_string('globalratelimit', 'aiprovider_awsbedrock'),
            new lang_string('globalratelimit_desc', 'aiprovider_awsbedrock'),
            100,
            PARAM_INT));
    new admin_settingdependency('aiprovider_awsbedrock/globalratelimit', 'aiprovider_awsbedrock/enableglobalratelimit', 'eq', 1);

    // Setting to enable/disable user rate limiting.
    $settings->add(new admin_setting_configcheckbox('aiprovider_awsbedrock/enableuserratelimit',
            new lang_string('enableuserratelimit', 'aiprovider_awsbedrock'),
            new lang_string('enableuserratelimit_desc', 'aiprovider_awsbedrock'),
            0));

    // Setting to set how many requests per hour are allowed for the user rate limit.
    // Should only be enabled when user rate limiting is enabled.
    $settings->add(new admin_setting_configtext('aiprovider_awsbedrock/userratelimit',
            new lang_string('userratelimit', 'aiprovider_awsbedrock'),
            new lang_string('userratelimit_desc', 'aiprovider_awsbedrock'),
            10,
            PARAM_INT));
    new admin_settingdependency('aiprovider_awsbedrock/userratelimit', 'aiprovider_awsbedrock/enableuserratelimit', 'eq', 1);
}
