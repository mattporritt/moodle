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
 * Characterisation test: "Select content" returns the chosen content to the activity form.
 *
 * A teacher picks content in the tool through a deep linking launch. The tool answers by posting a signed deep linking response
 * to the return URL that Moodle gave it in the launch. This test starts that launch, takes the return URL from the launch itself
 * (so it follows the script wherever it lives), posts a response signed with the tool's key, and asserts that what comes
 * back to the form is the configuration of the activity: its name, description, tool URL and custom parameters.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class deep_linking_return_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var string The tool's private key, as a PEM. */
    private string $toolprivatekey;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The teacher. */
    private \stdClass $teacher;

    /** @var int The tool. */
    private int $typeid;

    /**
     * Create a course, a teacher and an LTI 1.3 tool that can sign deep linking responses.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatekey);
        $this->toolprivatekey = $privatekey;
        $publickey = openssl_pkey_get_details($key)['key'];

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->typeid = $this->lti_create_tool([
            'name' => 'Deep linking tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1,
            'lti_ltiversion' => '1.3.0', 'lti_clientid' => 'dl-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'RSA_KEY',
            'lti_publickey' => $publickey, 'lti_contentitem' => 1,
        ]);
        $PAGE->set_course($this->course);
        $this->setUser($this->teacher);
    }

    /**
     * Start a deep linking launch and return the URL the tool must post its response to.
     *
     * @return string the deep linking return URL.
     */
    private function start_deep_linking_launch(): string {
        $login = $this->lti_start_login($this->course->id, $this->typeid, null, 'ContentItemSelectionRequest');
        [$output] = $this->lti_auth_request([
            'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none',
            'client_id' => $login['client_id'], 'login_hint' => $login['login_hint'],
            'lti_message_hint' => $login['lti_message_hint'], 'redirect_uri' => 'https://tool.example.com/redirect',
            'state' => 'state', 'nonce' => 'nonce',
        ]);
        $fields = $this->lti_form_fields($output);
        $this->assertArrayHasKey('id_token', $fields);
        $claims = json_decode(base64_decode(strtr(explode('.', $fields['id_token'])[1], '-_', '+/')), true);
        return $claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings']['deep_link_return_url'];
    }

    /**
     * Sign a deep linking response as the tool would.
     *
     * @param array $contentitems the content items the teacher chose.
     * @return string the JWT.
     */
    private function tool_signs_response(array $contentitems): string {
        global $CFG;
        $prefix = 'https://purl.imsglobal.org/spec/lti';
        $payload = [
            'iss' => 'dl-client', 'aud' => $CFG->wwwroot, 'iat' => time(), 'exp' => time() + 60, 'nonce' => 'response-nonce',
            "$prefix/claim/message_type" => 'LtiDeepLinkingResponse', "$prefix/claim/version" => '1.3.0',
            "$prefix/claim/deployment_id" => (string) $this->typeid, "$prefix-dl/claim/content_items" => $contentitems,
        ];
        return \Firebase\JWT\JWT::encode($payload, $this->toolprivatekey, 'RS256', 'tool-key');
    }

    /**
     * Sign a deep linking response that chooses a single resource link.
     *
     * @param string $title the title of the link.
     * @return string the JWT.
     */
    private function tool_signs_link(string $title): string {
        $item = ['type' => 'ltiResourceLink', 'title' => $title, 'url' => 'https://tool.example.com/x'];
        return $this->tool_signs_response([$item]);
    }

    /**
     * What the form receives for a chosen content item.
     *
     * The page passes it to the browser as the argument of a JavaScript module call, and the call is read from the page
     * requirements.
     *
     * @param \Throwable|null $thrown what the script threw.
     * @return array the configuration handed to the form.
     */
    private function configuration_returned_to_form(?\Throwable $thrown): array {
        global $PAGE;
        $this->assertNull($thrown, $thrown ? $thrown->getMessage() : '');
        $javascript = $PAGE->requires->get_end_code();
        $this->assertMatchesRegularExpression('/mod_lti\/contentitem_return|ltix\/contentitem_return/', $javascript);
        $call = '/amd\.init\((.*?)\); M\.util\.js_complete\(\'[a-z_]+\/contentitem_return\'\)/s';
        $this->assertSame(1, preg_match($call, $javascript, $matches), 'The form is handed no content');
        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * A chosen resource link comes back to the form as the activity's configuration.
     */
    public function test_chosen_content_comes_back_to_the_form(): void {
        $returnurl = $this->start_deep_linking_launch();
        $response = $this->tool_signs_response([[
            'type' => 'ltiResourceLink', 'title' => 'Chosen quiz', 'text' => 'A quiz chosen in the tool',
            'url' => 'https://tool.example.com/quiz/42', 'custom' => ['quiz' => '42', 'mode' => 'timed'],
        ]]);

        [, $thrown] = $this->lti_run_script($returnurl, [], ['JWT' => $response]);
        $configuration = $this->configuration_returned_to_form($thrown);

        $this->assertSame('Chosen quiz', $configuration['name']);
        $this->assertSame('https://tool.example.com/quiz/42', $configuration['toolurl']);
        $this->assertStringContainsString('A quiz chosen in the tool', $configuration['introeditor']['text']);
        $this->assertSame("quiz=42\nmode=timed", $configuration['instructorcustomparameters']);
    }

    /**
     * A response signed with the wrong key is refused and nothing comes back to the form.
     */
    public function test_response_signed_with_another_key_is_refused(): void {
        $returnurl = $this->start_deep_linking_launch();
        $this->toolprivatekey = $this->other_private_key();
        $response = $this->tool_signs_link('Forged');

        [$output, $thrown] = $this->lti_run_script($returnurl, [], ['JWT' => $response]);

        $this->assertStringNotContainsString('Forged', $output);
        $this->assertNotNull($thrown);
    }

    /**
     * A response posted without the session key from the launch is refused.
     */
    public function test_response_without_the_session_key_is_refused(): void {
        $returnurl = $this->start_deep_linking_launch();
        $response = $this->tool_signs_link('No sesskey');
        $withoutsesskey = preg_replace('/&?sesskey=[^&]*/', '', $returnurl);

        [$output, $thrown] = $this->lti_run_script($withoutsesskey, [], ['JWT' => $response]);

        $this->assertStringNotContainsString('No sesskey', $output);
        $this->assertNotNull($thrown);
    }

    /**
     * A student cannot add content: the return is refused.
     */
    public function test_student_cannot_return_content(): void {
        $returnurl = $this->start_deep_linking_launch();
        $response = $this->tool_signs_link('Student pick');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $returnurl = preg_replace('/sesskey=[^&]*/', 'sesskey=' . sesskey(), $returnurl);

        [$output, $thrown] = $this->lti_run_script($returnurl, [], ['JWT' => $response]);

        $this->assertStringNotContainsString('Student pick', $output);
        $this->assertNotNull($thrown);
    }

    /**
     * Generate a private key that is not the tool's.
     *
     * @return string the PEM.
     */
    private function other_private_key(): string {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        return $pem;
    }
}
