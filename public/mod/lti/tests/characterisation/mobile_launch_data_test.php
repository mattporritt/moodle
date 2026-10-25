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

namespace mod_lti\characterisation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/launch_scenarios_trait.php');

/**
 * Characterisation test: the launch data the Moodle app is given is the same launch data.
 *
 * The Moodle app asks the web service for the launch data of an activity and posts it to the tool itself. This takes a
 * student's launch of a tool with every service switched on, in LTI 1.1 and 1.3, through the web service and holds it to the
 * same recorded launch as the launch data test.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class mobile_launch_data_test extends \advanced_testcase {
    use launch_scenarios_trait;

    /**
     * Provide the scenarios the app can launch: a student's launch.
     *
     * @return \Generator
     */
    public static function scenario_provider(): \Generator {
        yield 'LTI 1.1' => ['lti11_student_all_services'];
        yield 'LTI 1.3' => ['lti13_student_all_services'];
    }

    /**
     * The web service returns the recorded launch data, as a list of names and values.
     *
     * @param string $name the scenario.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenario_provider')]
    public function test_web_service_returns_the_recorded_launch(string $name): void {
        $this->resetAfterTest();
        [$actual, $tokens] = $this->launch_scenario($name);

        $this->lti_assert_matches_fixture("launch/{$name}.json", $actual, $tokens);
    }

    /**
     * Get the launch data through the web service, as the app does.
     *
     * @param \stdClass $lti the activity.
     * @param string $messagetype not used: the app only starts normal launches.
     * @param int $foruserid not used: the app only starts normal launches.
     * @return array [endpoint, parameters]
     */
    protected function fetch_launch_data(\stdClass $lti, string $messagetype, int $foruserid): array {
        $result = $this->lti_ws_get_tool_launch_data((int) $lti->id);
        $parameters = [];
        foreach ($result['parameters'] as $parameter) {
            $parameters[$parameter['name']] = $parameter['value'];
        }
        return [$result['endpoint'], $parameters];
    }

    /**
     * The web service signs a nonce of its own into an LTI 1.3 launch.
     *
     * @return bool
     */
    protected function nonce_is_chosen(): bool {
        return false;
    }
}
