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

declare(strict_types=1);

namespace editor_tiny;

use advanced_testcase;

/**
 * Unit tests for the editor_tiny manager class.
 *
 * @package     editor_tiny
 * @covers      \editor_tiny\manager
 * @copyright   2026 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends advanced_testcase {
    /**
     * get_configurable_standard_plugins returns a non-empty array of plugin names.
     *
     * @covers ::get_configurable_standard_plugins
     */
    public function test_get_configurable_standard_plugins_returns_plugins(): void {
        $plugins = manager::get_configurable_standard_plugins();
        $this->assertIsArray($plugins);
        $this->assertNotEmpty($plugins);
    }

    /**
     * get_configurable_standard_plugins includes known configurable native plugins.
     *
     * @covers ::get_configurable_standard_plugins
     */
    public function test_get_configurable_standard_plugins_includes_expected_plugins(): void {
        $plugins = manager::get_configurable_standard_plugins();
        $this->assertContains('charmap', $plugins);
        $this->assertContains('table', $plugins);
        $this->assertContains('fullscreen', $plugins);
        $this->assertContains('wordcount', $plugins);
        $this->assertContains('searchreplace', $plugins);
    }

    /**
     * get_configurable_standard_plugins excludes hardcoded disabled plugins.
     *
     * @covers ::get_configurable_standard_plugins
     */
    public function test_get_configurable_standard_plugins_excludes_hardcoded_disabled(): void {
        $plugins = manager::get_configurable_standard_plugins();
        $this->assertNotContains('image', $plugins);
        $this->assertNotContains('media', $plugins);
        $this->assertNotContains('autosave', $plugins);
        $this->assertNotContains('preview', $plugins);
        $this->assertNotContains('link', $plugins);
    }

    /**
     * get_configurable_standard_plugins returns the same instance on repeated calls (static cache).
     *
     * @covers ::get_configurable_standard_plugins
     */
    public function test_get_configurable_standard_plugins_returns_same_result_on_repeated_calls(): void {
        $first = manager::get_configurable_standard_plugins();
        $second = manager::get_configurable_standard_plugins();
        $this->assertSame($first, $second);
    }

    /**
     * Disabling a plugin via admin config excludes it from plugin configuration.
     *
     * @covers ::get_plugin_configuration
     */
    public function test_admin_disabled_plugin_excluded_from_plugin_configuration(): void {
        $this->resetAfterTest();

        $manager = new manager();
        $context = \context_system::instance();

        // Charmap should be present by default.
        $config = $manager->get_plugin_configuration($context);
        $this->assertArrayHasKey('charmap', $config);

        // Disable charmap via site config.
        set_config('standard_plugin_charmap', 0, 'editor_tiny');

        $config = $manager->get_plugin_configuration($context);
        $this->assertArrayNotHasKey('charmap', $config);
    }

    /**
     * Re-enabling a plugin via admin config restores it to plugin configuration.
     *
     * @covers ::get_plugin_configuration
     */
    public function test_re_enabling_plugin_restores_it_to_plugin_configuration(): void {
        $this->resetAfterTest();

        set_config('standard_plugin_table', 0, 'editor_tiny');

        $manager = new manager();
        $context = \context_system::instance();
        $config = $manager->get_plugin_configuration($context);
        $this->assertArrayNotHasKey('table', $config);

        set_config('standard_plugin_table', 1, 'editor_tiny');

        $config = $manager->get_plugin_configuration($context);
        $this->assertArrayHasKey('table', $config);
    }

    /**
     * Multiple plugins can be disabled simultaneously via admin config.
     *
     * @covers ::get_plugin_configuration
     */
    public function test_multiple_admin_disabled_plugins_excluded_from_plugin_configuration(): void {
        $this->resetAfterTest();

        set_config('standard_plugin_charmap', 0, 'editor_tiny');
        set_config('standard_plugin_wordcount', 0, 'editor_tiny');
        set_config('standard_plugin_fullscreen', 0, 'editor_tiny');

        $manager = new manager();
        $context = \context_system::instance();
        $config = $manager->get_plugin_configuration($context);

        $this->assertArrayNotHasKey('charmap', $config);
        $this->assertArrayNotHasKey('wordcount', $config);
        $this->assertArrayNotHasKey('fullscreen', $config);
        // Other plugins should remain.
        $this->assertArrayHasKey('table', $config);
        $this->assertArrayHasKey('searchreplace', $config);
    }

    /**
     * Admin config cannot re-enable a hardcoded disabled plugin.
     *
     * @covers ::get_plugin_configuration
     */
    public function test_hardcoded_disabled_plugin_stays_disabled_regardless_of_config(): void {
        $this->resetAfterTest();

        // Setting a hardcoded-disabled plugin to enabled in config should have no effect.
        set_config('standard_plugin_image', 1, 'editor_tiny');

        $manager = new manager();
        $context = \context_system::instance();
        $config = $manager->get_plugin_configuration($context);
        $this->assertArrayNotHasKey('image', $config);
    }

    /**
     * Plugins enabled by default are present in plugin configuration without explicit config.
     *
     * @covers ::get_plugin_configuration
     */
    public function test_default_enabled_plugins_present_without_config(): void {
        $this->resetAfterTest();

        $manager = new manager();
        $context = \context_system::instance();
        $config = $manager->get_plugin_configuration($context);

        $this->assertArrayHasKey('charmap', $config);
        $this->assertArrayHasKey('table', $config);
        $this->assertArrayHasKey('wordcount', $config);
    }
}
