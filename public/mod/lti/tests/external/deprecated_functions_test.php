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

namespace mod_lti\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that the web service framework reports the deprecated mod_lti external functions as deprecated.
 *
 * The framework reads the flag from the external class (execute_is_deprecated() or <method>_is_deprecated()), not from the
 * deprecation attribute, so a function can carry the attribute and still be reported as current.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class deprecated_functions_test extends \advanced_testcase {
    /**
     * Each external function that mod_lti deprecated when LTI moved to core_ltix is reported as deprecated.
     *
     * @param string $name The external function name.
     */
    #[DataProvider('deprecated_function_provider')]
    public function test_function_is_reported_as_deprecated(string $name): void {
        $this->resetAfterTest();

        $info = external_api::external_function_info($name);

        $this->assertTrue($info->deprecated ?? false, "$name is not reported as deprecated");
    }

    /**
     * An external function of mod_lti or core_ltix with a deprecation attribute on its class or method is reported as deprecated.
     */
    public function test_function_with_deprecation_attribute_is_reported_as_deprecated(): void {
        global $DB;
        $this->resetAfterTest();

        $found = 0;
        $functions = $DB->get_records_select('external_functions', 'component IN (?, ?)', ['mod_lti', 'core_ltix'], 'name');
        foreach ($functions as $function) {
            $method = $function->methodname ?: 'execute';
            if (
                !\core\deprecation::is_deprecated($function->classname) &&
                !\core\deprecation::is_deprecated([$function->classname, $method])
            ) {
                continue;
            }
            $found++;
            $info = external_api::external_function_info($function);
            $this->assertTrue(
                $info->deprecated ?? false,
                "{$function->name} has a deprecation attribute but is not reported as deprecated"
            );
        }

        $this->assertGreaterThan(0, $found);
    }

    /**
     * The replacement functions in core_ltix are not reported as deprecated.
     */
    public function test_core_ltix_functions_are_not_reported_as_deprecated(): void {
        global $DB;
        $this->resetAfterTest();

        foreach ($DB->get_records('external_functions', ['component' => 'core_ltix'], 'name') as $function) {
            $info = external_api::external_function_info($function);
            $this->assertEmpty($info->deprecated ?? false, "{$function->name} is reported as deprecated");
        }
    }

    /**
     * The deprecated function names.
     *
     * @return array
     */
    public static function deprecated_function_provider(): array {
        return [
            'mod_lti_create_tool_proxy' => ['mod_lti_create_tool_proxy'],
            'mod_lti_create_tool_type' => ['mod_lti_create_tool_type'],
            'mod_lti_delete_course_tool_type' => ['mod_lti_delete_course_tool_type'],
            'mod_lti_delete_tool_proxy' => ['mod_lti_delete_tool_proxy'],
            'mod_lti_delete_tool_type' => ['mod_lti_delete_tool_type'],
            'mod_lti_get_tool_proxies' => ['mod_lti_get_tool_proxies'],
            'mod_lti_get_tool_proxy_registration_request' => ['mod_lti_get_tool_proxy_registration_request'],
            'mod_lti_get_tool_types' => ['mod_lti_get_tool_types'],
            'mod_lti_get_tool_types_and_proxies' => ['mod_lti_get_tool_types_and_proxies'],
            'mod_lti_get_tool_types_and_proxies_count' => ['mod_lti_get_tool_types_and_proxies_count'],
            'mod_lti_is_cartridge' => ['mod_lti_is_cartridge'],
            'mod_lti_toggle_showinactivitychooser' => ['mod_lti_toggle_showinactivitychooser'],
            'mod_lti_update_tool_type' => ['mod_lti_update_tool_type'],
        ];
    }
}
