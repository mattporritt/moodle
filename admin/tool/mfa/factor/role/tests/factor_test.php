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

namespace factor_role;

/**
 * Tests for role factor.
 *
 * @covers      \factor_role\factor
 * @package     factor_role
 * @copyright   2023 Stevani Andolo <stevani@hotmail.com.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factor_test extends \advanced_testcase {

    /**
     * Tests getting the summary condition
     *
     * @covers ::get_summary_condition
     * @covers ::get_roles
     */
    public function test_get_summary_condition() {
        $this->resetAfterTest();

        set_config('enabled', 1, 'factor_role');
        $rolefactor = \tool_mfa\plugininfo\factor::get_factor('role');

        // Admin is disabled by default in this role.
        $selectedroles = get_config('factor_role', 'roles');
        $this->assertStringContainsString(
            $rolefactor->get_roles($selectedroles),
            $rolefactor->get_summary_condition()
        );

        // Disabled role factor for managers.
        set_config('roles', '1', 'factor_role');

        $selectedroles = get_config('factor_role', 'roles');
        $this->assertStringContainsString(
            $rolefactor->get_roles($selectedroles),
            $rolefactor->get_summary_condition()
        );

        // Disabled role factor for students.
        set_config('roles', '5', 'factor_role');

        $selectedroles = get_config('factor_role', 'roles');
        $this->assertStringContainsString(
            $rolefactor->get_roles($selectedroles),
            $rolefactor->get_summary_condition()
        );

        // Disabled role factor for admins, managers and students.
        set_config('roles', 'admin,1,5', 'factor_role');

        $selectedroles = get_config('factor_role', 'roles');
        $this->assertStringContainsString(
            $rolefactor->get_roles($selectedroles),
            $rolefactor->get_summary_condition()
        );

        // Enable all roles.
        unset_config('roles', 'factor_role');
        $this->assertEquals(
            get_string('summarycondition', 'factor_role', get_string('none')),
            $rolefactor->get_summary_condition()
        );
    }
}
