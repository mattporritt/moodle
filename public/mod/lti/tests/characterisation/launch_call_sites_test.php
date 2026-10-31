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
require_once(__DIR__ . '/characterisation_trait.php');

/**
 * Characterisation test: the launch scripts pass the launch details to the login initiation function in the right places.
 *
 * launch.php and contentitem.php end with exit, so PHPUnit cannot run them, and a call that has drifted from the function's
 * parameters (an argument added in the middle of the list) is only seen when a launch loses data. This is how a submission review
 * launch lost its for_user. The test reads each script, finds the arguments of the call, and asserts that each launch detail the
 * script holds in a variable is passed as the parameter of the same name. It checks the call, not the launch: running the script
 * needs a web request, which is in the backlog of the P7 report.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class launch_call_sites_test extends \advanced_testcase {
    use characterisation_trait;

    /**
     * Provide the scripts that start a login.
     *
     * @return \Generator
     */
    public static function script_provider(): \Generator {
        $callers = (new class {
            use characterisation_trait;

            /**
             * Expose the callers to the data provider.
             *
             * @return array
             */
            public function callers(): array {
                return $this->lti_login_initiation_callers();
            }
        })->callers();
        foreach ($callers as $script => $function) {
            yield $script => [$script];
        }
    }

    /**
     * Split the arguments of the first call of a function in some code, at the commas that are not nested.
     *
     * @param string $code the code.
     * @param string $function the function name.
     * @return string[]|null the source text of each argument, or null if the function is not called.
     */
    private function call_arguments(string $code, string $function): ?array {
        $tokens = token_get_all($code);
        $count = count($tokens);
        for ($i = 0; $i < $count - 1; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== $function) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if ($tokens[$j] !== '(') {
                continue;
            }
            $depth = 0;
            $arguments = [];
            $current = '';
            for ($k = $j; $k < $count; $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                if (in_array($text, ['(', '[', '{'], true)) {
                    $depth++;
                    if ($depth === 1) {
                        continue;
                    }
                } else if (in_array($text, [')', ']', '}'], true)) {
                    $depth--;
                    if ($depth === 0) {
                        $arguments[] = trim($current);
                        return $arguments;
                    }
                } else if ($text === ',' && $depth === 1) {
                    $arguments[] = trim($current);
                    $current = '';
                    continue;
                }
                $current .= $text;
            }
        }
        return null;
    }

    /**
     * Each argument that is a variable named like a parameter of the login initiation function is passed as that parameter.
     *
     * @param string $script the script, relative to the code root.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('script_provider')]
    public function test_launch_details_are_passed_as_the_parameters_they_belong_to(string $script): void {
        global $CFG;
        $parameters = $this->lti_login_initiation_parameters();
        $arguments = $this->call_arguments(file_get_contents("{$CFG->dirroot}/{$script}"), 'lti_initiate_login');
        $this->assertNotNull($arguments, "$script does not call the login initiation function");
        $this->assertLessThanOrEqual(count($parameters), count($arguments));

        // The variables the scripts use for each launch detail, and the parameter each belongs to.
        $names = [
            'foruserid' => 'foruserid', 'messagetype' => 'messagetype', 'msgtype' => 'messagetype', 'courseid' => 'courseid',
            'cmid' => 'cmid', 'title' => 'title', 'text' => 'text',
        ];
        $checked = 0;
        foreach ($arguments as $position => $argument) {
            if (!preg_match('/^\$(\w+)$/', $argument, $matches) || !isset($names[$matches[1]])) {
                continue;
            }
            $this->assertSame(
                $names[$matches[1]],
                $parameters[$position] ?? null,
                "$script passes $argument as argument " . ($position + 1) . ', which is parameter $' .
                    ($parameters[$position] ?? '?')
            );
            $checked++;
        }
        $this->assertGreaterThan(0, $checked, "$script passes no launch details that could be checked");
    }
}
