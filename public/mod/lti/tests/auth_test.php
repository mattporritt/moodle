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

/**
 * Tests for the LTI 1.3 authentication endpoint, mod/lti/auth.php.
 *
 * The endpoint is a script, not a class or function, so there is nothing for a covers attribute to name.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class auth_test extends \advanced_testcase {
    /**
     * Start a launch, and return the login request fields the platform sends to the tool.
     *
     * @return array
     */
    private function start_launch(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Auth tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => LTI_TOOL_STATE_CONFIGURED,
            'lti_ltiversion' => LTI_VERSION_1P3, 'lti_clientid' => 'auth-client',
            'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => LTI_JWK_KEYSET,
            'lti_publickeyset' => 'https://tool.example.com/jwks',
        ]);
        $this->setAdminUser();
        $form = lti_initiate_login($course->id, 0, null, lti_get_type_type_config($typeid), 'basic-lti-launch-request');
        return $this->form_fields($form);
    }

    /**
     * Send an authentication request to the endpoint.
     *
     * @param array $login the login request fields from {@see self::start_launch()}.
     * @param array $override request fields to change.
     * @return array [output, throwable or null]
     */
    private function send_request(array $login, array $override): array {
        global $CFG, $USER, $SESSION, $DB, $PAGE, $OUTPUT;

        $_POST = $override + [
            'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post',
            'client_id' => $login['client_id'], 'login_hint' => (string) $USER->id,
            'lti_message_hint' => $login['lti_message_hint'],
            'redirect_uri' => 'https://tool.example.com/redirect', 'state' => 'tool-state', 'nonce' => 'tool-nonce',
        ];
        $_REQUEST = $_POST;
        $thrown = null;
        $workingdirectory = getcwd();
        ob_start();
        try {
            chdir($CFG->dirroot . '/mod/lti');
            include($CFG->dirroot . '/mod/lti/auth.php');
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            $output = ob_get_clean();
            chdir($workingdirectory);
            $_POST = $_REQUEST = [];
        }
        return [$output, $thrown];
    }

    /**
     * Start a launch and send an authentication request for it.
     *
     * @param array $override request fields to change.
     * @return array [output, throwable or null]
     */
    private function authenticate(array $override): array {
        return $this->send_request($this->start_launch(), $override);
    }

    /**
     * Read the hidden fields of the form the endpoint prints.
     *
     * @param string $html the form.
     * @return array field name => value, and 'action' for the form's action.
     */
    private function form_fields(string $html): array {
        $fields = [];
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        foreach ($document->getElementsByTagName('form') as $form) {
            $fields['action'] = $form->getAttribute('action');
        }
        foreach ($document->getElementsByTagName('input') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        return $fields;
    }

    /**
     * OAuth errors are returned to the tool's registered redirect URI.
     *
     * @param array $override request fields to change.
     * @param string $error the expected error code.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oauth_error_provider')]
    public function test_oauth_error_is_returned_to_tool(array $override, string $error): void {
        $this->resetAfterTest();

        [$output, $thrown] = $this->authenticate($override);

        $this->assertNull($thrown);
        $fields = $this->form_fields($output);
        $this->assertSame('https://tool.example.com/redirect', $fields['action']);
        $this->assertSame($error, $fields['error']);
        $this->assertSame('tool-state', $fields['state']);
        $this->assertArrayNotHasKey('id_token', $fields);
    }

    /**
     * Requests that are answered with an OAuth error.
     *
     * @return array
     */
    public static function oauth_error_provider(): array {
        return [
            'Unsupported response type' => [['response_type' => 'code'], 'unsupported_response_type'],
            'Invalid scope' => [['scope' => 'profile'], 'invalid_scope'],
            'Invalid scope and unsupported response type' => [
                ['scope' => 'profile', 'response_type' => 'code'], 'invalid_scope',
            ],
            'Unknown client' => [['client_id' => 'someone-else'], 'unauthorized_client'],
            'Login hint for a different user' => [['login_hint' => '-1'], 'access_denied'],
        ];
    }

    /**
     * A valid request is not answered with an error.
     */
    public function test_valid_request_is_not_an_error(): void {
        $this->resetAfterTest();

        [$output, $thrown] = $this->authenticate([]);

        $this->assertNull($thrown);
        $fields = $this->form_fields($output);
        $this->assertArrayNotHasKey('error', $fields);
        $this->assertSame('tool-state', $fields['state']);
        $this->assertArrayHasKey('id_token', $fields);
    }

    /**
     * An unregistered redirect URI is never posted to, even for a request that has an error.
     */
    public function test_unregistered_redirect_uri_is_refused(): void {
        $this->resetAfterTest();

        [$output, $thrown] = $this->authenticate([
            'response_type' => 'code',
            'redirect_uri' => 'https://evil.example.com/steal',
        ]);

        $this->assertInstanceOf(\moodle_exception::class, $thrown);
        $this->assertStringNotContainsString('evil.example.com', $output);
    }

    /**
     * A launch id that is not in the session is refused as there is no trusted redirect URI.
     *
     * @param array $override request fields to change.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unknown_launch_provider')]
    public function test_unknown_launch_is_refused(array $override): void {
        $this->resetAfterTest();

        [$output, $thrown] = $this->authenticate(
            ['lti_message_hint' => json_encode(['launchid' => 'ltilaunch-unknown'])] + $override
        );

        $this->assertInstanceOf(\moodle_exception::class, $thrown);
        $this->assertSame('invalidrequest', $thrown->errorcode);
        $this->assertStringNotContainsString('tool.example.com', $output);
    }

    /**
     * Requests with an unknown launch id.
     *
     * @return array
     */
    public static function unknown_launch_provider(): array {
        return [
            'Valid parameters' => [[]],
            'Unsupported response type' => [['response_type' => 'code']],
            'Invalid scope' => [['scope' => 'profile']],
        ];
    }

    /**
     * A launch is not consumed by an invalid scope or unsupported response type, so the tool can retry.
     */
    public function test_launch_survives_scope_and_response_type_errors(): void {
        $this->resetAfterTest();
        $login = $this->start_launch();

        [$output, $thrown] = $this->send_request($login, ['response_type' => 'code']);
        $this->assertNull($thrown);
        $this->assertSame('unsupported_response_type', $this->form_fields($output)['error']);

        [$output, $thrown] = $this->send_request($login, []);
        $this->assertNull($thrown);
        $fields = $this->form_fields($output);
        $this->assertArrayNotHasKey('error', $fields);
        $this->assertArrayHasKey('id_token', $fields);
    }
}
