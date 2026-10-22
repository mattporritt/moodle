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

namespace mod_lti;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests that launch.php hands the targeted user to the LTI 1.3 login initiation.
 *
 * launch.php ends the request with exit, so it cannot be run from PHPUnit. The call is checked against the real
 * signature of \core_ltix\helper::initiate_login() instead, because inserting a parameter into that signature once
 * silently shifted $foruserid into $text and dropped the for_user claim from submission review launches.
 *
 * @package   mod_lti
 * @copyright 2026 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class launch_foruser_test extends \advanced_testcase {
    /**
     * The user id given to launch.php must arrive in the foruserid parameter of initiate_login().
     */
    public function test_launch_passes_foruserid_to_initiate_login(): void {
        global $CFG;

        $source = file_get_contents("{$CFG->dirroot}/mod/lti/launch.php");
        $this->assertEquals(
            1,
            preg_match('/helper::initiate_login\((.*?)\);/s', $source, $matches),
            'launch.php should call \core_ltix\helper::initiate_login() once'
        );
        $args = array_map('trim', explode(',', $matches[1]));

        $names = array_map(
            fn(\ReflectionParameter $param) => $param->getName(),
            (new \ReflectionMethod(\core_ltix\helper::class, 'initiate_login'))->getParameters()
        );
        $position = array_search('foruserid', $names);
        $this->assertNotFalse($position, 'initiate_login() should have a foruserid parameter');

        $this->assertSame('$foruserid', $args[$position] ?? null);
        // Every other optional argument launch.php supplies before it must be an empty string, not the user id.
        foreach (array_slice($args, 0, $position) as $index => $arg) {
            $this->assertNotSame('$foruserid', $arg, "foruserid passed in the position of {$names[$index]}");
        }
    }

    /**
     * The user id passed to initiate_login() must be stored for the auth step that builds the for_user claim.
     */
    public function test_initiate_login_stores_foruserid(): void {
        global $SESSION;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $config = (object) [
            'typeid' => 5,
            'lti_toolurl' => 'https://tool.example.com/launch',
            'lti_clientid' => 'client1',
            'lti_initiatelogin' => 'https://tool.example.com/login',
        ];
        $instance = (object) ['id' => 7, 'toolurl' => ''];

        \core_ltix\helper::initiate_login(
            $course->id,
            3,
            $instance,
            $config,
            'LtiSubmissionReviewRequest',
            '',
            '',
            '',
            $user->id
        );

        $launchdata = null;
        foreach ($SESSION as $key => $value) {
            if (str_starts_with($key, 'ltilaunch7_')) {
                $launchdata = explode(',', $value);
            }
        }
        $this->assertNotNull($launchdata);
        $this->assertEquals('LtiSubmissionReviewRequest', $launchdata[3]);
        $this->assertEquals($user->id, $launchdata[4]);
        $this->assertEmpty($launchdata[6]);
    }
}
