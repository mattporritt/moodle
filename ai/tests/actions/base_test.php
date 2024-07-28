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

namespace core_ai\actions;

/**
 * Test response_base action methods.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\actions\base
 */
final class base_test extends \advanced_testcase {

    /**
     * Test get_basename.
     */
    public function test_get_basename(): void {
        $action = \core_ai\manager::get_action('core_ai\\aiactions\\generate_text');
        $basename = $action->get_basename($action);
        $this->assertEquals('generate_text', $basename);
    }

    /**
     * Test get_name.
     */
    public function test_get_name(): void {
        $action = \core_ai\manager::get_action('core_ai\\aiactions\\generate_text');

        $this->assertEquals(
                get_string('action_generate_text', 'core_ai'),
                $action->get_name()
        );
    }

    /**
     * Test get_description.
     */
    public function test_get_description(): void {
        $action = \core_ai\manager::get_action('core_ai\\aiactions\\generate_text');

        $this->assertEquals(
                get_string('action_generate_text_desc', 'core_ai'),
                $action->get_description()
        );
    }

}
