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

use core_ai\actions\base;

/**
 * Test ai subsystem manager methods.
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

        // Assert array keys match the expected actions.
        $this->assertEquals([
                'generate_text',
                'generate_image',
                'summarise_text',
                'translate_text',
        ], array_keys($actions));

        // Assert array values are instances of the expected action classes.
        $this->assertInstanceOf(\core_ai\actions\generate_text::class, $actions['generate_text']);
        $this->assertInstanceOf(\core_ai\actions\summarise_text::class, $actions['summarise_text']);
    }

    /**
     * Test get_providers_for_actions.
     */
    public function test_get_providers_for_actions(): void {
        $manager = new manager();
        $actions = ['generate_text', 'summarise_text'];

        // Get the providers for the actions.
        $providers = $manager->get_providers_for_actions($actions);

        // Assert that the providers array is indexed by action name.
        $this->assertEquals($actions, array_keys($providers));
    }

    /**
     * Test get_action.
     */
    public function test_get_action(): void {
        $action = \core_ai\manager::get_action('generate_text');
        // Assert class is an instance of base.
        $this->assertInstanceOf(base::class, $action);
    }

    public function test_process_action() {
        // Create a partial mock for YourClass to mock the call_action_provider method.
        $managermock = $this->getMockBuilder(manager::class)
                ->onlyMethods(['call_action_provider'])
                ->getMock();

        $expectedResult = (object)['success' => true];

        // Set up the expectation for call_action_provider to return the defined result.
        $managermock->expects($this->any())
                ->method('call_action_provider')
                ->willReturn($expectedResult);

        $action = $managermock::get_action('generate_image');

        $result = $managermock->process_action($action);
        error_log(print_r($result, true));
    }
}
