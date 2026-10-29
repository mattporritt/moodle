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

namespace core_ltix\local\ltiservice;

use core_ltix\local\lticore\message\context\collection\launch_context;
use core_ltix\local\lticore\message\context\item\course_context;
use core_ltix\local\lticore\message\context\item\message_context;
use core_ltix\local\lticore\message\context\item\resource_link_context;
use core_ltix\local\lticore\message\type\message_type_factory;
use core_ltix\local\placement\service\resource_link_manager;

/**
 * Tests for the deprecated launch methods of the ltixservice base class.
 *
 * The services only implement get_launch_params() and parse_val(). The deprecated get_launch_parameters() and
 * parse_value() must give launches that still use them the same parameters.
 *
 * @package    core_ltix
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ltix\local\ltiservice\service_base
 */
final class service_base_test extends \advanced_testcase {
    /**
     * Creates a tool with the services enabled, a course and an lti activity using the tool.
     *
     * @return array [course, user, tool id, lti activity, resource link]
     */
    private function create_launch_fixtures(): array {
        $this->setAdminUser();

        $type = (object) [
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED,
            'name' => 'Test tool',
            'description' => 'Example description',
            'baseurl' => 'https://example.com/tool',
            'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
        ];
        $config = (object) [
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'ltixservice_gradesynchronization' => 2,
            'ltixservice_memberships' => 1,
            'ltixservice_toolsettings' => 1,
        ];
        $typeid = \core_ltix\helper::add_type($type, $config);
        $this->create_activity_placement($typeid);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $lti = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'typeid' => $typeid,
            'instructorchoiceacceptgrades' => \core_ltix\constants::LTI_SETTING_NEVER,
        ]);
        $link = resource_link_manager::get_resource_link_by_item($lti->cmid, 'mod_lti:activityplacement');
        $this->assertNotNull($link);

        return [$course, $user, $typeid, $lti, $link];
    }

    /**
     * Enables the activity placement of a tool in every course.
     *
     * @param int $typeid The tool ID.
     */
    private function create_activity_placement(int $typeid): void {
        global $DB;

        $this->getDataGenerator()->get_plugin_generator('core_ltix')->create_tool_placements([
            'toolid' => $typeid,
            'placementtypeid' => $DB->get_field('lti_placement_type', 'id', ['type' => 'mod_lti:activityplacement']),
            'config_default_usage' => 'enabled',
        ]);
    }

    /**
     * The deprecated methods carry the deprecated attribute, so tooling and callers can see they are deprecated.
     *
     * @param string $class The class declaring the method.
     * @param string $method The deprecated method.
     * @param string $replacement The replacement the attribute points to.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deprecated_method_provider')]
    public function test_deprecated_methods_have_the_deprecated_attribute(
        string $class,
        string $method,
        string $replacement,
    ): void {
        $attributes = (new \ReflectionMethod($class, $method))->getAttributes(\core\attribute\deprecated::class);
        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertSame('6.0', $attribute->since);
        $this->assertSame($replacement, $attribute->replacement);
    }

    /**
     * Provides the deprecated methods of the service and resource base classes.
     *
     * @return array
     */
    public static function deprecated_method_provider(): array {
        return [
            'service get_launch_parameters' => [
                \core_ltix\local\ltiservice\service_base::class,
                'get_launch_parameters',
                '::get_launch_params',
            ],
            'resource parse_value' => [
                \core_ltix\local\ltiservice\resource_base::class,
                'parse_value',
                '::parse_val',
            ],
        ];
    }

    /**
     * The deprecated get_launch_parameters() returns what get_launch_params() returns for the same launch.
     */
    public function test_get_launch_parameters_delegates_to_get_launch_params(): void {
        $this->resetAfterTest();
        [$course, $user, $typeid, $lti, $link] = $this->create_launch_fixtures();

        $expectedkeys = [
            'ltixservice_gradebookservices' => ['gradebookservices_scope', 'lineitems_url'],
            'ltixservice_memberships' => ['context_memberships_url', 'context_memberships_v2_url', 'context_memberships_versions'],
            'ltixservice_toolsettings' => ['system_setting_url', 'context_setting_url', 'link_setting_url'],
        ];
        $messagetype = \core\di::get(message_type_factory::class)->from_string('basic-lti-launch-request');
        $launchcontext = launch_context::instance(
            new message_context($messagetype),
            new course_context($course),
            new resource_link_context($link),
        );

        $found = [];
        foreach (\core_ltix\helper::get_services() as $service) {
            $deprecated = $service->get_launch_parameters(
                'basic-lti-launch-request',
                $course->id,
                $user->id,
                $typeid,
                $lti->id
            );

            $service->set_type(\core_ltix\helper::get_type($typeid));
            $service->set_typeconfig(\core_ltix\helper::get_type_config($typeid));
            $this->assertEquals($service->get_launch_params($launchcontext), $deprecated);

            if (isset($expectedkeys[$service->get_component_id()])) {
                foreach ($expectedkeys[$service->get_component_id()] as $key) {
                    $this->assertArrayHasKey($key, $deprecated, $service->get_component_id());
                }
                $found[] = $service->get_component_id();
            }
        }
        $this->assertEqualsCanonicalizing(array_keys($expectedkeys), $found);
    }

    /**
     * The deprecated get_launch_parameters() sends nothing for a service that is not enabled for the tool.
     */
    public function test_get_launch_parameters_for_a_tool_without_the_services(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $type = (object) [
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED,
            'name' => 'Plain tool',
            'baseurl' => 'https://example.com/plain',
            'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
        ];
        $typeid = \core_ltix\helper::add_type($type, (object) ['lti_coursevisible' => $type->coursevisible]);
        $this->create_activity_placement($typeid);
        $course = $this->getDataGenerator()->create_course();
        $lti = $this->getDataGenerator()->create_module('lti', ['course' => $course->id, 'typeid' => $typeid]);

        foreach (\core_ltix\helper::get_services() as $service) {
            $this->assertSame([], $service->get_launch_parameters(
                'basic-lti-launch-request',
                $course->id,
                2,
                $typeid,
                $lti->id
            ), $service->get_component_id());
        }
    }

    /**
     * The deprecated parse_value() resolves the same variables as parse_val(), for the current course and request.
     */
    public function test_parse_value_delegates_to_parse_val(): void {
        global $COURSE;

        $this->resetAfterTest();
        [$course, $user, $typeid, $lti, $link] = $this->create_launch_fixtures();

        $COURSE = $course;
        $_GET['id'] = $lti->cmid;

        $messagetype = \core\di::get(message_type_factory::class)->from_string('basic-lti-launch-request');
        $launchcontext = launch_context::instance(
            new message_context($messagetype),
            new course_context($course),
            new resource_link_context($link),
        );

        $variables = [
            'ltixservice_gradebookservices' => '$LineItems.url',
            'ltixservice_memberships' => '$ToolProxyBinding.memberships.url',
        ];
        $resolved = [];
        foreach (\core_ltix\helper::get_services() as $service) {
            if (!isset($variables[$service->get_component_id()])) {
                continue;
            }
            $service->set_type(\core_ltix\helper::get_type($typeid));

            $value = $service->parse_value($variables[$service->get_component_id()]);

            $this->assertNotEquals($variables[$service->get_component_id()], $value, $service->get_component_id());
            $this->assertStringContainsString('/ltix/services.php/', $value);
            $this->assertEquals($service->parse_val($variables[$service->get_component_id()], $launchcontext), $value);
            $resolved[] = $service->get_component_id();
        }
        $this->assertEqualsCanonicalizing(array_keys($variables), $resolved);
    }

    /**
     * The deprecated parse_value() finds the activity from the lti_message_hint when there is no id in the request.
     */
    public function test_parse_value_reads_the_activity_from_the_message_hint(): void {
        global $COURSE;

        $this->resetAfterTest();
        [$course, $user, $typeid, $lti, $link] = $this->create_launch_fixtures();

        $COURSE = $course;
        $_GET['lti_message_hint'] = json_encode(['cmid' => $lti->cmid]);

        $service = \core_ltix\local\ltiservice\service_helper::get_service_by_name('memberships');
        $service->set_type(\core_ltix\helper::get_type($typeid));

        $value = $service->parse_value('$LtiLink.memberships.url');

        $this->assertNotEquals('$LtiLink.memberships.url', $value);
        $this->assertStringContainsString('/ltix/services.php/links/' . $link->get('id') . '/memberships', $value);
    }

    /**
     * The LTI 2.0 registration request carries the profile URL, which only the deprecated parse_value() can resolve.
     */
    public function test_registration_request_has_the_profile_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $toolproxy = (object) [
            'id' => 0,
            'guid' => 'abc',
            'secret' => 'def',
            'regurl' => 'https://example.com/register',
        ];
        $params = \core_ltix\helper::build_registration_request($toolproxy);

        $this->assertStringContainsString('/ltix/services.php/profile', $params['tc_profile_url']);
        $this->assertStringNotContainsString('$ToolConsumerProfile.url', $params['tc_profile_url']);
    }
}
