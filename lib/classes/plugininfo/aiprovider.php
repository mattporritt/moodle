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
    /**
     * Should there be a way to uninstall the plugin via the administration UI.
     * By default, uninstallation is allowed.
     *
     * @return bool
     */
    public function is_uninstall_allowed(): bool {
        return true;
    }

    /**
     * This plugintype supports its plugins being disabled.
     *
     * @return bool
     */
    public static function plugintype_supports_disabling(): bool {
        return true;
    }

    /**
     * Returns the node name used in admin settings menu for this plugin settings.
     *
     * @return string node name.
     */
    public function get_settings_section_name(): string {
        return $this->type . '_' . $this->name;
    }

    /**
     * Loads plugin settings to the settings tree.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
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

        $settings = null;
        if (file_exists($this->full_path('settings.php'))) {
            $settings = new \admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // This may also set $settings to null.
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Return URL used for management of plugins of this type.
     * @return moodle_url
     */
    public static function get_manage_url(): moodle_url {
        return new \moodle_url('/admin/settings.php', [
                'section' => 'aiprovider',
        ]);
    }

    /**
     * Enable or disable a plugin.
     * When possible, the change will be stored into the config_log table, to let admins check when/who has modified it.
     *
     * @param string $pluginname The plugin name to enable/disable.
     * @param int $enabled Whether the pluginname should be enabled (1) or not (0).
     *
     * @return bool Whether $pluginname has been updated or not.
     */
    public static function enable_plugin(string $pluginname, int $enabled): bool {
        $haschanged = false;

        $plugin = 'aiprovider_' . $pluginname;
        $oldvalue = get_config($plugin, 'disabled');
        $disabled = !$enabled;
        // Only set value if there is no config setting or if the value is different from the previous one.
        if ($oldvalue == false && $disabled) {
            set_config('disabled', $disabled, $plugin);
            $haschanged = true;
        } else if ($oldvalue != false && !$disabled) {
            unset_config('disabled', $plugin);
            $haschanged = true;
        }

        if ($haschanged) {
            add_to_config_log('disabled', $oldvalue, $disabled, $plugin);
            core_plugin_manager::reset_caches();
        }

        return $haschanged;
    }

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     *
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown.
     */
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
            $disabled = get_config('aiprovider_' . $plugin, 'disabled');
            if (empty($disabled)) {
                $enabled[$plugin] = $plugin;
            }
        }
        return $enabled;
    }
}
