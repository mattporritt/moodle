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

namespace core_ai;

/**
 * Test communication helper methods.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\manager
 */
class manager_test extends \advanced_testcase {

    /**
     * Test get_ai_plugin_classname.
     */
    public function test_get_ai_plugin_classname(): void {

        $manager = new manager();

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($manager, 'get_ai_plugin_classname');

        // Test a provider plugin,
        $classname = $method->invoke($manager, 'aiprovider_fooai');
        $this->assertEquals('aiprovider_fooai\\provider', $classname);

        // Test a placement plugin.
        $classname = $method->invoke($manager, 'aiplacement_fooplacement');
        $this->assertEquals('aiplacement_fooplacement\\placement', $classname);

        // Test an invalid plugin.
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Plugin name does not start with \'aiprovider_\' or \'aiplacement_\': bar');
        $method->invoke($manager, 'bar');
    }
    /**
     * Test get_supported_actions.
     */
    public function test_get_supported_actions(): void {

        $manager = new manager();
        $actions = $manager->get_supported_actions('aiprovider_openai');

        $this->assertEquals([
            \core_ai\actions\generate_text::class,
            \core_ai\actions\summarise_text::class
        ], $actions);
    }

}
