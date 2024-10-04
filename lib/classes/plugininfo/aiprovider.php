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

namespace core\plugininfo;

use core_plugin_manager;
use moodle_url;

/**
 * AI placement plugin info class.
 *
 * @package    core
 * @copyright 2024 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiprovider extends base {
    #[\Override]
    public function is_uninstall_allowed(): bool {
        return true;
    }

    #[\Override]
    public static function plugintype_supports_disabling(): bool {
        return true;
    }

    #[\Override]
    public function get_settings_section_name(): string {
        return $this->type . '_' . $this->name;
    }

    #[\Override]
    public function load_settings(
        \part_of_admin_tree $adminroot,
        $parentnodename,
        $hassiteconfig,
    ): void {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        /** @var \admin_root $ADMIN */
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig) {
            return;
        }

        $section = $this->get_settings_section_name();

        // Load the specific settings.
        $settings = new \core_ai\admin\admin_settingspage_provider(
            name: $section,
            visiblename: $this->displayname,
            req_capability: 'moodle/site:config',
            hidden: true,
        );
        if (file_exists($this->full_path('settings.php'))) {
            include($this->full_path('settings.php')); // This may also set $settings to null.
            // Show the save changes button between the specific settings and the actions table.
            $settings->add(new \admin_setting_savebutton("{$section}/savebutton"));
        }

        // Load the actions table.
        $providerclass = "\\{$section}\\provider";
        $provider = new $providerclass();
        if (file_exists($this->full_path('setting_actions.php'))) {
            include($this->full_path('setting_actions.php')); // This may also set $settings to null.
        } else {
            // Provider action settings heading.
            $settings->add(new \admin_setting_heading("{$section}/generals",
                new \lang_string('provideractionsettings', 'core_ai'),
                new \lang_string('provideractionsettings_desc', 'core_ai', $provider->get_name())));
            // Load the setting table of actions that this provider supports.
            $settings->add(new \core_ai\admin\admin_setting_action_manager(
                $section,
                \core_ai\table\aiprovider_action_management_table::class,
                'manageaiproviders',
                new \lang_string('manageaiproviders', 'core_ai'),
            ));
        }
        $ADMIN->add($parentnodename, $settings);
        // Load any action settings for this provider.
        $actionlist = $provider->get_action_list();
        foreach ($actionlist as $action) {
            $actionsettings = $provider->get_action_settings($action, $ADMIN, $section, $hassiteconfig);
            if (!empty($actionsettings)) {
                $actionname = substr($action, (strrpos($action, '\\') + 1));
                $settings = new \admin_settingpage($section . '_' . $actionname, $action::get_name(), 'moodle/site:config', true);
                $descplaceholder = [
                    'providername' => $provider->get_name(),
                    'actionname' => $action::get_name(),
                ];
                $setting = new \admin_setting_heading("{$section}_actions/heading",
                    new \lang_string('actionsettingprovider', 'core_ai', $provider->get_name()),
                    new \lang_string('actionsettingprovider_desc', 'core_ai', $descplaceholder));
                $settings->add($setting);
                foreach ($actionsettings as $setting) {
                    $settings->add($setting);
                }
                $ADMIN->add('root', $settings);
            }
        }
    }

    #[\Override]
    public static function get_manage_url(): moodle_url {
        return new moodle_url('/admin/settings.php', [
            'section' => 'aiprovider',
        ]);
    }

    #[\Override]
    public static function enable_plugin(string $pluginname, int $enabled): bool {
        $plugin = 'aiprovider_' . $pluginname;
        $oldvalue = self::is_plugin_enabled($pluginname);
        $newvalue = (bool)$enabled;

        if ($oldvalue !== $newvalue) {
            if ($newvalue) {
                set_config('enabled', $enabled, $plugin);
            } else {
                unset_config('enabled', $plugin);
            }

            add_to_config_log('enabled', $oldvalue, $newvalue, $plugin);
            core_plugin_manager::reset_caches();
            return true;
        }

        return false;
    }

    #[\Override]
    public static function get_enabled_plugins(): ?array {
        $pluginmanager = core_plugin_manager::instance();
        $plugins = $pluginmanager->get_installed_plugins('aiprovider');

        if (!$plugins) {
            return [];
        }

        $plugins = array_keys($plugins);

        // Filter to return only enabled plugins.
        $enabled = [];
        foreach ($plugins as $plugin) {
            if (self::is_plugin_enabled($plugin)) {
                $enabled[$plugin] = $plugin;
            }
        }
        return $enabled;
    }

    /**
     * Check if a provider plugin is enabled in config.
     *
     * @param string $plugin The plugin to check.
     * @return bool Return true if enabled.
     */
    public static function is_plugin_enabled(string $plugin): bool {
        $config = get_config('aiprovider_' . $plugin, 'enabled');
        return $config == 1;
    }
}
