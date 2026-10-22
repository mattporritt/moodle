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

namespace core_ltix;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the LTI 1.3 OIDC authentication endpoint, \core_ltix\helper::get_auth_endpoint().
 *
 * @package   core_ltix
 * @copyright 2026 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(helper::class)]
final class auth_endpoint_test extends \advanced_testcase {
    /**
     * Set up an LTI 1.3 activity, a student and an auth request for it, stored as a login initiation would.
     *
     * @return array [$user, $cm, $launchid]
     */
    private function setup_launch(string $role = 'student'): array {
        global $SESSION, $_POST;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, $role);

        $typeid = $generator->get_plugin_generator('core_ltix')->create_tool_types([
            'name' => 'LTI 1.3 tool',
            'baseurl' => 'https://tool.example.com/launch',
            'state' => 1, // Configured.
            'coursevisible' => 1, // Preconfigured, so the activity placement can be enabled for the tool.
            'lti_ltiversion' => 'LTI-1p3',
            'lti_clientid' => 'client1',
            'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/launch',
            'lti_publickeytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://tool.example.com/jwks',
        ]);
        $this->ensure_activity_placement($typeid);
        $instance = $generator->create_module('lti', ['course' => $course->id, 'typeid' => $typeid]);
        $cm = get_coursemodule_from_instance('lti', $instance->id);
        $config = helper::get_type_type_config($typeid);

        $this->setUser($user);
        $request = helper::build_login_request($course->id, $cm->id, $instance, $config, 'basic-lti-launch-request');

        $_POST = [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'client_id' => 'client1',
            'redirect_uri' => 'https://tool.example.com/launch',
            'login_hint' => (string) $user->id,
            'lti_message_hint' => $request['lti_message_hint'],
            'response_mode' => 'form_post',
            'nonce' => 'nonce1',
            'state' => 'state1',
        ];

        return [$user, $cm, json_decode($request['lti_message_hint'])->launchid];
    }

    /**
     * Give the tool an activity placement where placements exist and the tool was not given one when it was created.
     *
     * @param int $typeid the tool type id.
     */
    private function ensure_activity_placement(int $typeid): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('lti_placement')) {
            return;
        }
        $placementtypeid = $DB->get_field('lti_placement_type', 'id', ['type' => 'mod_lti:activityplacement'], MUST_EXIST);
        if (!$DB->record_exists('lti_placement', ['toolid' => $typeid, 'placementtypeid' => $placementtypeid])) {
            $this->getDataGenerator()->get_plugin_generator('core_ltix')->create_tool_placements([
                'toolid' => $typeid,
                'placementtypeid' => $placementtypeid,
            ]);
        }
    }

    /**
     * Run the auth endpoint and return what it printed.
     *
     * @return string
     */
    private function run_auth_endpoint(): string {
        ob_start();
        try {
            helper::get_auth_endpoint();
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    public function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    /**
     * A user who may enter the course but not view the activity must be refused, not given a signed launch.
     *
     * Today require_login() already refuses such a user because the activity is not visible to them, so the explicit
     * capability check in the endpoint is defence in depth. This test pins the outcome either way.
     */
    public function test_auth_refuses_user_without_view_capability_on_the_activity(): void {
        global $DB;
        $this->resetAfterTest();

        [$user, $cm] = $this->setup_launch('editingteacher');
        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability('mod/lti:view', CAP_PROHIBIT, $role->id, \context_module::instance($cm->id)->id, true);

        $output = '';
        $refused = null;
        try {
            $output = $this->run_auth_endpoint();
        } catch (\moodle_exception $e) {
            $refused = $e;
        }
        $this->assertNotNull($refused, 'The auth endpoint should refuse a user who cannot view the activity');
        $this->assertStringNotContainsString('ltiAuthForm', $output);
    }

    /**
     * A user who can view the activity gets the auth response posted back to the tool.
     */
    public function test_auth_succeeds_with_view_capability(): void {
        $this->resetAfterTest();

        $this->setup_launch();
        $output = $this->run_auth_endpoint();

        $this->assertStringContainsString('ltiAuthForm', $output);
        $this->assertStringContainsString('name="state" value="state1"', $output);
        $this->assertStringNotContainsString('name="error"', $output);
    }

    /**
     * The launch data is removed once used, so replaying the same auth request is refused.
     */
    public function test_auth_request_cannot_be_replayed(): void {
        global $SESSION;
        $this->resetAfterTest();

        [, , $launchid] = $this->setup_launch();
        $this->assertStringContainsString('ltiAuthForm', $this->run_auth_endpoint());
        $this->assertFalse(isset($SESSION->$launchid));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidrequest', 'error'));
        $this->run_auth_endpoint();
    }
}
