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
 * Characterisation test: the LTI 1.3 launches that go through the auth endpoint, end to end.
 *
 * A launch starts with Moodle's login initiation request to the tool, comes back as the tool's authentication request to
 * auth.php, and ends with a signed id_token posted to the tool. These tests run the two Moodle steps with the user's session
 * in between, as a browser would, and assert on what the tool receives or that it receives nothing. This is the path the
 * new window, grade report and deep linking launches take.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class auth_endpoint_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var string The prefix of every LTI 1.3 claim. */
    private const CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/';

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student. */
    private \stdClass $student;

    /** @var \stdClass The teacher. */
    private \stdClass $teacher;

    /** @var int The LTI 1.3 tool. */
    private int $typeid;

    /** @var \stdClass The activity of the tool. */
    private \stdClass $lti;

    /**
     * Create a course with an LTI 1.3 tool and an activity, a student and a teacher.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->typeid = $this->lti_create_tool([
            'name' => 'Auth tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => '1.3.0',
            'lti_clientid' => 'auth-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://tool.example.com/jwks', 'lti_contentitem' => 1,
        ]);
        $this->lti = $this->lti_create_activity($this->course->id, [
            'typeid' => $this->typeid, 'resourcekey' => '', 'password' => '', 'launchcontainer' => 2,
        ]);
        $PAGE->set_course($this->course);
    }

    /**
     * Run the tool's authentication request for a login initiated by Moodle.
     *
     * @param array $login the fields Moodle posted to the tool's login URL.
     * @param array $override request fields to change, as a misbehaving or malicious tool might.
     * @return array [output, throwable or null]
     */
    private function authenticate(array $login, array $override = []): array {
        $request = $override + [
            'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none',
            'client_id' => $login['client_id'], 'login_hint' => $login['login_hint'],
            'lti_message_hint' => $login['lti_message_hint'],
            'redirect_uri' => 'https://tool.example.com/redirect', 'state' => 'tool-state', 'nonce' => 'tool-nonce',
        ];
        return $this->lti_auth_request($request);
    }

    /**
     * Decode the claims of the id_token in what the auth endpoint printed.
     *
     * @param string $output what the endpoint printed.
     * @return array the claims
     */
    private function id_token_claims(string $output): array {
        $fields = $this->lti_form_fields($output);
        $this->assertArrayHasKey('id_token', $fields, 'The endpoint did not issue an id_token');
        $payload = explode('.', $fields['id_token'])[1];
        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Assert that the auth endpoint refused: it issued no id_token.
     *
     * @param string $output what the endpoint printed.
     */
    private function assert_no_id_token(string $output): void {
        $this->assertArrayNotHasKey('id_token', $this->lti_form_fields($output));
    }

    /**
     * An activity launch completes: the tool receives an id_token for the user, the activity and its own nonce.
     */
    public function test_activity_launch_completes_with_signed_id_token(): void {
        $this->setUser($this->student);
        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        $this->assertSame('https://tool.example.com/launch', $login['target_link_uri']);
        $this->assertSame('auth-client', $login['client_id']);
        $this->assertEquals($this->student->id, $login['login_hint']);

        [$output, $thrown] = $this->authenticate($login);
        $this->assertNull($thrown);
        $fields = $this->lti_form_fields($output);
        $this->assertSame('https://tool.example.com/redirect', $fields['action']);
        $this->assertSame('tool-state', $fields['state']);

        $claims = $this->id_token_claims($output);
        $this->assertSame('tool-nonce', $claims['nonce']);
        $this->assertSame('auth-client', $claims['aud']);
        $this->assertEquals($this->student->id, $claims['sub']);
        $this->assertSame('LtiResourceLinkRequest', $claims[self::CLAIM . 'message_type']);
        $this->assertSame((string) $this->typeid, $claims[self::CLAIM . 'deployment_id']);
        $this->assertSame((string) $this->lti->id, (string) $claims[self::CLAIM . 'resource_link']['id']);
        $this->assertSame('iframe', $claims[self::CLAIM . 'launch_presentation']['document_target']);
        $this->assertArrayNotHasKey(self::CLAIM . 'for_user', $claims);
    }

    /**
     * An activity set to open in a new window completes and tells the tool.
     */
    public function test_new_window_launch_completes(): void {
        global $DB;
        $DB->set_field('lti', 'launchcontainer', 4, ['id' => $this->lti->id]);
        $this->setUser($this->student);
        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login);
        $claims = $this->id_token_claims($output);
        $this->assertSame('window', $claims[self::CLAIM . 'launch_presentation']['document_target']);
    }

    /**
     * A submission review (grade report) launch completes and names the user whose work is reviewed.
     */
    public function test_submission_review_launch_completes_with_for_user(): void {
        $this->setUser($this->teacher);
        $login = $this->lti_start_login(
            $this->course->id,
            $this->typeid,
            $this->lti,
            'LtiSubmissionReviewRequest',
            (int) $this->student->id
        );
        [$output] = $this->authenticate($login);
        $claims = $this->id_token_claims($output);
        $this->assertEquals($this->teacher->id, $claims['sub']);
        $this->assertSame('LtiSubmissionReviewRequest', $claims[self::CLAIM . 'message_type']);
        $this->assertEquals($this->student->id, $claims[self::CLAIM . 'for_user']['user_id']);
    }

    /**
     * A content selection (deep linking) launch from the course completes with the deep linking request the tool needs.
     */
    public function test_deep_linking_launch_completes(): void {
        $this->setUser($this->teacher);
        $login = $this->lti_start_login($this->course->id, $this->typeid, null, 'ContentItemSelectionRequest');
        [$output, $thrown] = $this->authenticate($login);
        $this->assertNull($thrown);
        $claims = $this->id_token_claims($output);

        $this->assertSame('tool-nonce', $claims['nonce']);
        $this->assertEquals($this->teacher->id, $claims['sub']);
        $this->assertSame('LtiDeepLinkingRequest', $claims[self::CLAIM . 'message_type']);
        $settings = $claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings'];
        $this->assertNotEmpty($settings['accept_types']);
        $this->assertNotEmpty($settings['accept_presentation_document_targets']);
        $this->assertNotEmpty($settings['deep_link_return_url']);
        $this->assertSame((int) $this->course->id, (int) $this->query_param($settings['deep_link_return_url'], 'course'));
        $this->assertSame($this->typeid, (int) $this->query_param($settings['deep_link_return_url'], 'id'));
        $this->assertNotEmpty($this->query_param($settings['deep_link_return_url'], 'sesskey'));
    }

    /**
     * A student cannot start a deep linking launch.
     */
    public function test_deep_linking_launch_is_refused_without_permission(): void {
        $this->setUser($this->student);
        $login = $this->lti_start_login($this->course->id, $this->typeid, null, 'ContentItemSelectionRequest');
        [$output] = $this->authenticate($login);
        $this->assert_no_id_token($output);
    }

    /**
     * Read one query parameter of a URL.
     *
     * @param string $url the URL.
     * @param string $name the parameter.
     * @return string|null the value.
     */
    private function query_param(string $url, string $name): ?string {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        return $query[$name] ?? null;
    }

    /**
     * An authentication request cannot be replayed: the second use of the same launch is refused.
     */
    public function test_replayed_auth_request_is_refused(): void {
        $this->setUser($this->student);
        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);

        [$first] = $this->authenticate($login);
        $this->assertNotEmpty($this->id_token_claims($first));

        [$second] = $this->authenticate($login);
        $this->assert_no_id_token($second);
    }

    /**
     * A user who is not allowed to view the activity is refused.
     */
    public function test_user_without_view_capability_is_refused(): void {
        global $DB;
        $this->setUser($this->student);
        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            $this->lti_view_capability(),
            CAP_PROHIBIT,
            $roleid,
            \context_module::instance($this->lti->cmid)->id,
            true
        );

        [$output] = $this->authenticate($login);
        $this->assert_no_id_token($output);
    }

    /**
     * Requests that do not match what Moodle started are refused. A wrong client id or user is answered with an OAuth error.
     */
    public function test_mismatched_requests_are_refused(): void {
        $this->setUser($this->student);

        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login, ['client_id' => 'someone-else']);
        $this->assert_no_id_token($output);
        $this->assertSame('unauthorized_client', $this->lti_form_fields($output)['error']);

        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login, ['login_hint' => (string) $this->teacher->id]);
        $this->assert_no_id_token($output);
        $this->assertSame('access_denied', $this->lti_form_fields($output)['error']);

        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login, ['response_type' => 'code']);
        $this->assert_no_id_token($output);

        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login, ['scope' => 'profile']);
        $this->assert_no_id_token($output);

        // A redirect URI the tool did not register is never posted to.
        $login = $this->lti_start_login($this->course->id, $this->typeid, $this->lti);
        [$output] = $this->authenticate($login, ['redirect_uri' => 'https://evil.example.com/steal']);
        $this->assert_no_id_token($output);
        $this->assertStringNotContainsString('evil.example.com', $output);
    }
}
