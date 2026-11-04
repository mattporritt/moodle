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
require_once(__DIR__ . '/service_calls_trait.php');

/**
 * Characterisation test: LTI 2.0 tool proxy registration.
 *
 * An administrator starts a registration, and Moodle gives the tool a registration key and secret. The tool answers by posting
 * its tool proxy, signed with them, to the tool proxy service. These tests make that post in process and check what Moodle
 * answers, the state of the registration, and the tool it adds. A post that is not signed with the secret, or whose tool proxy
 * is not valid, is refused and adds no tool.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class tool_proxy_test extends \advanced_testcase {
    use characterisation_trait;
    use service_calls_trait;

    /** @var string The media type of a tool proxy. */
    private const TOOL_PROXY = 'application/vnd.ims.lti.v2.toolproxy+json';

    /** @var \stdClass The registration Moodle started for the tool. */
    private \stdClass $registration;

    /**
     * Start a registration, as an administrator does, which gives the tool a key (the guid) and secret to sign with.
     *
     * @param string[] $services the services to offer the tool, all of them by default.
     */
    private function start_registration(array $services = []): void {
        global $DB;
        $this->setAdminUser();
        $config = (object) [
            'lti_registrationname' => 'Registered tool', 'lti_registrationurl' => 'https://tool.example.com/register',
        ];
        if ($services) {
            $config->lti_services = $services;
        }
        $id = lti_add_tool_proxy($config);
        $this->registration = $DB->get_record('lti_tool_proxies', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The tool proxy a tool posts: its vendor, the secret to use from now on, the one service it asks for and one launchable tool.
     *
     * @param array $changes parts of the tool proxy to replace.
     * @return array
     */
    private function tool_proxy(array $changes = []): array {
        // The tool is given the address of the Moodle profile, which names the registration, as the context of its services.
        $profile = lti_get_service_by_name('profile');
        $profile->set_tool_proxy($this->registration);
        $context = $profile->get_service_path() . $profile->get_resources()[0]->get_path() . '#';
        return $changes + [
            '@context' => ['http://purl.imsglobal.org/ctx/lti/v2/ToolProxy', ['tcp' => $context]],
            '@type' => 'ToolProxy',
            'lti_version' => 'LTI-2p0',
            'security_contract' => [
                'shared_secret' => 'secret-for-later',
                'tool_service' => [['@type' => 'RestServiceProfile', 'service' => 'tcp:ToolProxySettings', 'action' => ['GET']]],
            ],
            'tool_profile' => [
                'lti_version' => 'LTI-2p0',
                'product_instance' => [
                    'guid' => 'product-1',
                    'product_info' => [
                        'product_name' => ['default_value' => 'Registered tool'],
                        'product_version' => '1.0',
                        'product_family' => ['code' => 'family', 'vendor' => ['code' => 'vendor-1']],
                    ],
                ],
                'base_url_choice' => [
                    ['default_base_url' => 'https://tool.example.com', 'secure_base_url' => 'https://tool.example.com'],
                ],
                'resource_handler' => [[
                    'resource_name' => ['default_value' => 'Registered activity'],
                    'resource_type' => ['code' => 'activity'],
                    'icon_info' => ['default_location' => ['path' => '/icon.png']],
                    'message' => [[
                        'message_type' => 'basic-lti-launch-request',
                        'path' => '/launch',
                        'enabled_capability' => [],
                        'parameter' => [['name' => 'custom_one', 'fixed' => 'value one']],
                    ]],
                ]],
            ],
        ];
    }

    /**
     * Post a tool proxy to the tool proxy service as the tool does, signed with the registration key.
     *
     * @param array $toolproxy the tool proxy.
     * @param string $secret the secret to sign with, the registration secret by default.
     * @return object
     */
    private function post(array $toolproxy, string $secret = ''): object {
        return $this->lti_signed_service_call(
            \ltiservice_toolproxy\local\resources\toolproxy::class,
            \ltiservice_toolproxy\local\service\toolproxy::class,
            'POST',
            '/toolproxy',
            $this->registration->guid,
            $secret ?: $this->registration->secret,
            self::TOOL_PROXY,
            json_encode($toolproxy)
        );
    }

    /**
     * The registration as it is stored now.
     *
     * @return \stdClass
     */
    private function stored_registration(): \stdClass {
        global $DB;
        return $DB->get_record('lti_tool_proxies', ['id' => $this->registration->id], '*', MUST_EXIST);
    }

    /**
     * The tools added for the registration.
     *
     * @return \stdClass[]
     */
    private function tools_of_registration(): array {
        global $DB;
        return array_values($DB->get_records('lti_types', ['toolproxyid' => $this->registration->id]));
    }

    /**
     * A valid tool proxy is accepted: Moodle answers with the id of the tool proxy and keeps the tool's secret, and the tool the
     * proxy describes is added, waiting for an administrator to accept it.
     */
    public function test_a_valid_tool_proxy_is_accepted(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->start_registration();
        $this->assertEmpty($this->tools_of_registration());

        $response = $this->post($this->tool_proxy());

        $this->assertSame(201, $response->get_code());
        $this->assertSame('application/vnd.ims.lti.v2.toolproxy.id+json', $response->get_content_type());
        $this->lti_assert_matches_fixture(
            'services/tool_proxy_accepted.json',
            json_decode($response->get_body(), true),
            ['{{wwwroot}}' => $CFG->wwwroot, '{{guid}}' => $this->registration->guid]
        );

        $stored = $this->stored_registration();
        $this->assertEquals(LTI_TOOL_PROXY_STATE_ACCEPTED, $stored->state);
        $this->assertSame('secret-for-later', $stored->secret);
        $this->assertSame('vendor-1', $stored->vendorcode);
        $tools = $this->tools_of_registration();
        $this->assertCount(1, $tools);
        $this->assertSame('Registered activity', $tools[0]->name);
        $this->assertSame('https://tool.example.com/launch', $tools[0]->baseurl);
        $this->assertSame(LTI_VERSION_2, $tools[0]->ltiversion);
        $this->assertEquals(LTI_TOOL_STATE_PENDING, $tools[0]->state);
        $this->assertSame('https://tool.example.com/icon.png', $tools[0]->icon);
    }

    /**
     * Once accepted, the tool proxy signs its calls with the secret it chose, and not the registration secret.
     */
    public function test_the_secret_the_tool_chose_replaces_the_registration_secret(): void {
        $this->resetAfterTest();
        $this->start_registration();
        $registrationsecret = $this->registration->secret;
        $this->post($this->tool_proxy());

        $stored = $this->stored_registration();

        $this->assertNotSame($registrationsecret, $stored->secret);
        $this->assertSame('secret-for-later', $stored->secret);
        $this->assertSame($this->registration->guid, $stored->guid);
    }

    /**
     * A post signed with a secret other than the registration secret is refused, and the registration stays as it was.
     *
     * The answer is a 400: the service sets 401 and then replaces it, as it does for any tool proxy it does not accept.
     */
    public function test_a_post_signed_with_another_secret_is_refused(): void {
        $this->resetAfterTest();
        $this->start_registration();

        $response = $this->post($this->tool_proxy(), 'notthesecret');

        $this->assertDebuggingCalled();
        $this->assertSame(400, $response->get_code());
        $this->assertEmpty($response->get_body());
        $stored = $this->stored_registration();
        $this->assertEquals(LTI_TOOL_PROXY_STATE_CONFIGURED, $stored->state);
        $this->assertSame($this->registration->secret, $stored->secret);
        $this->assertEmpty($this->tools_of_registration());
    }

    /**
     * A tool proxy that does not give a shared secret is rejected: the registration is marked rejected and no tool is added.
     */
    public function test_a_tool_proxy_without_a_secret_is_rejected(): void {
        $this->resetAfterTest();
        $this->start_registration();
        $toolproxy = $this->tool_proxy();
        unset($toolproxy['security_contract']['shared_secret']);

        $response = $this->post($toolproxy);

        $this->assertDebuggingCalled();
        $this->assertSame(400, $response->get_code());
        $this->assertEmpty($response->get_body());
        $this->assertEquals(LTI_TOOL_PROXY_STATE_REJECTED, $this->stored_registration()->state);
        $this->assertEmpty($this->tools_of_registration());
    }

    /**
     * A tool proxy that asks for a service Moodle did not offer to it is rejected, and no tool is added.
     */
    public function test_a_tool_proxy_asking_for_a_service_that_was_not_offered_is_rejected(): void {
        $this->resetAfterTest();
        $this->start_registration(['profile']);

        $response = $this->post($this->tool_proxy());

        $this->assertDebuggingCalled();
        $this->assertSame(400, $response->get_code());
        $this->assertEquals(LTI_TOOL_PROXY_STATE_REJECTED, $this->stored_registration()->state);
        $this->assertEmpty($this->tools_of_registration());
    }

    /**
     * A tool proxy that asks for a service that does not exist is rejected, and no tool is added.
     */
    public function test_a_tool_proxy_asking_for_a_service_that_does_not_exist_is_rejected(): void {
        $this->markTestSkipped(
            'Skipped until MDL-90477 is fixed: a tool proxy asking for an unknown service raises a TypeError.'
        );
        $this->resetAfterTest();
        $this->start_registration();

        $toolproxy = $this->tool_proxy();
        $toolproxy['security_contract']['tool_service'][0]['service'] = 'tcp:NoSuchService';
        $response = $this->post($toolproxy);

        $this->assertDebuggingCalled();
        $this->assertSame(400, $response->get_code());
        $this->assertEquals(LTI_TOOL_PROXY_STATE_REJECTED, $this->stored_registration()->state);
        $this->assertEmpty($this->tools_of_registration());
    }
}
