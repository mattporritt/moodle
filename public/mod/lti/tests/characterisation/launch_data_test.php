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
 * Characterisation test: the exact data Moodle sends to a tool when an activity is launched.
 *
 * Each scenario builds a tool and an activity from the generators, takes the launch data for a given user and compares it with
 * the JSON file of the same name in tests/fixtures/characterisation/launch. Only the values that change on every launch are
 * normalised (see normalise_launch_data()). Everything else, including every service parameter and claim, is compared exactly.
 *
 * Placeholders in the fixtures ({{course}}, {{instance}} and so on) are the ids of the records created by the scenario. Ids
 * that Moodle sends as numbers, not strings, are written as "{{foruser:int}}" so that the fixtures stay valid JSON.
 *
 * These files describe what Moodle does today, so that moving LTI to core_ltix can be shown not to change it. A fixture
 * changes only when the launch data is meant to change.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class launch_data_test extends \advanced_testcase {
    use launch_scenarios_trait;

    /**
     * Provide the scenario names.
     *
     * @return \Generator
     */
    public static function scenario_provider(): \Generator {
        foreach (array_keys(self::scenarios()) as $name) {
            yield $name => [$name];
        }
    }

    /**
     * The launch data for each scenario is exactly the recorded launch data.
     *
     * @param string $name the scenario.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenario_provider')]
    public function test_launch_data_is_unchanged(string $name): void {
        $this->resetAfterTest();
        [$actual, $tokens] = $this->launch_scenario($name);

        $this->lti_assert_matches_fixture("launch/{$name}.json", $actual, $tokens);
    }
}
