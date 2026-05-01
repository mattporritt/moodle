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

namespace editor_tiny\external;

use advanced_testcase;
use editor_tiny\manager;
use invalid_parameter_exception;
use required_capability_exception;

/**
 * Unit tests for editor_tiny\external\set_standard_plugin_state.
 *
 * @package    editor_tiny
 * @covers     \editor_tiny\external\set_standard_plugin_state
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_standard_plugin_state_test extends advanced_testcase {
    /**
     * Set up for each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that an admin can disable a standard plugin.
     */
    public function test_execute_disable_plugin(): void {
        $this->setAdminUser();

        $plugins = manager::get_configurable_standard_plugins();
        $plugin = reset($plugins);

        // Ensure it starts enabled.
        set_config('standard_plugin_' . $plugin, 1, 'editor_tiny');

        $result = set_standard_plugin_state::execute($plugin, 0);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $this->assertEquals('0', get_config('editor_tiny', 'standard_plugin_' . $plugin));
    }

    /**
     * Test that an admin can enable a standard plugin.
     */
    public function test_execute_enable_plugin(): void {
        $this->setAdminUser();

        $plugins = manager::get_configurable_standard_plugins();
        $plugin = reset($plugins);

        // Start disabled.
        set_config('standard_plugin_' . $plugin, 0, 'editor_tiny');

        $result = set_standard_plugin_state::execute($plugin, 1);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $this->assertEquals('1', get_config('editor_tiny', 'standard_plugin_' . $plugin));
    }

    /**
     * Test that a non-admin cannot toggle plugin state.
     */
    public function test_execute_requires_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugins = manager::get_configurable_standard_plugins();
        $plugin = reset($plugins);

        $this->expectException(required_capability_exception::class);
        set_standard_plugin_state::execute($plugin, 0);
    }

    /**
     * Test that an invalid plugin name is rejected.
     */
    public function test_execute_invalid_plugin(): void {
        $this->setAdminUser();

        $this->expectException(invalid_parameter_exception::class);
        set_standard_plugin_state::execute('notarealplugin', 1);
    }

    /**
     * Test that unconfigured plugins default to enabled (no config key yet).
     */
    public function test_execute_default_no_config(): void {
        $this->setAdminUser();

        $plugins = manager::get_configurable_standard_plugins();
        $plugin = reset($plugins);

        // Remove any existing config.
        unset_config('standard_plugin_' . $plugin, 'editor_tiny');
        $this->assertFalse(get_config('editor_tiny', 'standard_plugin_' . $plugin));

        // Disabling from no-config state should still work.
        set_standard_plugin_state::execute($plugin, 0);
        $this->assertEquals('0', get_config('editor_tiny', 'standard_plugin_' . $plugin));
    }
}
