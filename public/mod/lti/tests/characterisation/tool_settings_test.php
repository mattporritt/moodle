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
 * Characterisation test: the tool settings service.
 *
 * A tool reads and writes its own settings at three levels: for the tool in the whole site (system), for the tool in a course
 * (context) and for one activity (link). An LTI 2.0 tool proxy signs its calls with OAuth 1.0; the tests write at each level,
 * read back, and hold the answer that combines the three levels to a recorded response. A tool that is not a tool proxy calls
 * with a bearer token; its refusals are tested too.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class tool_settings_test extends \advanced_testcase {
    use characterisation_trait;
    use service_calls_trait;

    /** @var string The tool settings scope. */
    private const SCOPE = 'https://purl.imsglobal.org/spec/lti-ts/scope/toolsetting';
    /** @var string The media type of settings as a flat list. */
    private const SIMPLE = 'application/vnd.ims.lti.v2.toolsettings.simple+json';
    /** @var string The media type of settings with the level they belong to. */
    private const FULL = 'application/vnd.ims.lti.v2.toolsettings+json';

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $lti;

    /** @var int The tool. */
    private int $typeid;

    /** @var \stdClass|null The tool proxy, when the tool is an LTI 2.0 tool proxy. */
    private ?\stdClass $proxy = null;

    /**
     * Create a course, a tool that has the tool settings service switched on, and an activity of the tool.
     *
     * @param bool $toolproxy true for an LTI 2.0 tool proxy that signs its calls, false for a tool that uses a bearer token.
     */
    private function create_course_and_activity(bool $toolproxy = false): void {
        global $DB;
        $this->course = $this->getDataGenerator()->create_course();
        if ($toolproxy) {
            $proxyid = lti_add_tool_proxy((object) [
                'lti_registrationname' => 'Proxy', 'lti_registrationurl' => 'https://t.example.com',
            ]);
            $DB->update_record('lti_tool_proxies', [
                'id' => $proxyid, 'guid' => 'proxy-guid', 'secret' => 'proxy-secret', 'vendorcode' => 'vendor',
                'state' => LTI_TOOL_PROXY_STATE_ACCEPTED, 'toolproxy' => $this->tool_proxy_json(),
            ]);
            $this->proxy = $DB->get_record('lti_tool_proxies', ['id' => $proxyid], '*', MUST_EXIST);
            $this->typeid = lti_add_type(
                (object) ['state' => LTI_TOOL_STATE_CONFIGURED, 'ltiversion' => LTI_VERSION_2, 'toolproxyid' => $proxyid,
                    'enabledcapability' => '', 'parameter' => ''],
                (object) ['lti_toolurl' => 'https://tool.example.com/launch', 'lti_typename' => 'Proxy tool',
                    'lti_coursevisible' => 1, 'lti_forcessl' => 0]
            );
        } else {
            $this->typeid = $this->create_tool('Settings tool', 'tool');
        }
        $this->lti = $this->lti_create_activity($this->course->id, [
            'name' => 'Settings activity', 'typeid' => $this->typeid, 'resourcekey' => '', 'password' => '',
        ]);
    }

    /**
     * The tool proxy the tool registered: the services it asked for.
     *
     * @return string
     */
    private function tool_proxy_json(): string {
        $services = array_map(
            fn($id) => ['service' => "tcp:{$id}", 'action' => ['GET', 'PUT']],
            ['ToolProxySettings', 'ToolProxyBindingSettings', 'LtiLinkSettings']
        );
        return json_encode([
            '@context' => ['http://purl.imsglobal.org/ctx/lti/v2/ToolProxy', ['tcp' => 'https://example.com/profile#']],
            'security_contract' => ['shared_secret' => 'proxy-secret', 'tool_service' => $services],
        ]);
    }

    /**
     * Create an LTI 1.3 tool that has the tool settings service switched on.
     *
     * @param string $name the name.
     * @param string $host the host of its URLs.
     * @return int the tool.
     */
    private function create_tool(string $name, string $host): int {
        return $this->lti_create_tool([
            'name' => $name, 'baseurl' => "https://{$host}.example.com/launch", 'state' => 1,
            'lti_ltiversion' => '1.3.0', 'lti_clientid' => "{$host}-client",
            'lti_initiatelogin' => "https://{$host}.example.com/login",
            'lti_redirectionuris' => "https://{$host}.example.com/redirect",
            'lti_keytype' => 'JWK_KEYSET', 'lti_publickeyset' => "https://{$host}.example.com/jwks",
        ] + $this->lti_service_setting('toolsettings', 1));
    }

    /**
     * Call one of the tool settings endpoints, as the tool proxy if there is one, or else as a tool with a bearer token.
     *
     * @param string $level system, context or link.
     * @param string $method GET or PUT.
     * @param string $type the Accept header for a GET, the Content-Type for a PUT.
     * @param string $body the request body.
     * @param array $query query parameters.
     * @param string[] $scopes the scopes of the token, for a tool that is not a tool proxy.
     * @param string $secret the secret to sign with, for a tool proxy; its own by default.
     * @return \mod_lti\local\ltiservice\response
     */
    private function call(
        string $level,
        string $method,
        string $type,
        string $body = '',
        array $query = [],
        array $scopes = [self::SCOPE],
        string $secret = ''
    ) {
        $subject = $this->proxy ? $this->proxy->guid : "tool/{$this->typeid}";
        $paths = [
            'system' => $this->proxy ? "/toolproxy/{$this->proxy->guid}" : "/tool/{$this->typeid}",
            'context' => "/CourseSection/{$this->course->id}/bindings/" .
                ($this->proxy ? "{$this->proxy->vendorcode}/{$this->proxy->guid}" : "tool/{$this->typeid}"),
            'link' => "/links/{$this->lti->id}",
        ];
        $resources = [
            'system' => \ltiservice_toolsettings\local\resources\systemsettings::class,
            'context' => \ltiservice_toolsettings\local\resources\contextsettings::class,
            'link' => \ltiservice_toolsettings\local\resources\linksettings::class,
        ];
        $service = \ltiservice_toolsettings\local\service\toolsettings::class;
        if ($this->proxy) {
            return $this->lti_signed_service_call(
                $resources[$level],
                $service,
                $method,
                $paths[$level],
                $this->proxy->guid,
                $secret ?: $this->proxy->secret,
                $type,
                $body,
                $query
            );
        }
        return $this->lti_service_call(
            $resources[$level],
            $service,
            $this->typeid,
            $method,
            $paths[$level],
            $scopes,
            $type,
            $body,
            $query
        );
    }

    /**
     * The placeholders of the fixtures.
     *
     * @return array
     */
    private function tokens(): array {
        global $CFG;
        return [
            '{{wwwroot}}' => $CFG->wwwroot, '{{course}}' => $this->course->id, '{{instance}}' => $this->lti->id,
            '{{proxy}}' => $this->proxy->id,
        ];
    }

    /**
     * Settings written at each level are read back at that level.
     */
    public function test_settings_are_written_and_read_at_each_level(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity(true);
        $written = [
            'system' => ['systemkey' => 'system value'],
            'context' => ['contextkey' => 'context value'],
            'link' => ['linkkey' => 'link value'],
        ];

        foreach ($written as $level => $settings) {
            $this->assertSame([], json_decode($this->call($level, 'GET', self::SIMPLE)->get_body(), true), $level);
            $response = $this->call($level, 'PUT', self::SIMPLE, json_encode($settings));
            $this->assertSame(200, $response->get_code(), $level);
            $response = $this->call($level, 'GET', self::SIMPLE);
            $this->assertSame(200, $response->get_code(), $level);
            $this->assertSame($settings, json_decode($response->get_body(), true), $level);
        }
    }

    /**
     * A write at one level does not change the other levels.
     */
    public function test_a_write_changes_only_its_own_level(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity(true);

        $this->call('context', 'PUT', self::SIMPLE, json_encode(['contextkey' => 'context value']));

        $this->assertSame([], json_decode($this->call('system', 'GET', self::SIMPLE)->get_body(), true));
        $this->assertSame([], json_decode($this->call('link', 'GET', self::SIMPLE)->get_body(), true));
    }

    /**
     * Reading the settings of a link with the levels combined gives the settings of all three levels, as recorded.
     */
    public function test_the_levels_are_combined_for_a_link(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity(true);
        $this->call('system', 'PUT', self::SIMPLE, json_encode(['shared' => 'from system', 'systemkey' => 'system value']));
        $this->call('context', 'PUT', self::SIMPLE, json_encode(['shared' => 'from context', 'contextkey' => 'context value']));
        $this->call('link', 'PUT', self::SIMPLE, json_encode(['shared' => 'from link', 'linkkey' => 'link value']));

        $all = $this->call('link', 'GET', self::FULL, '', ['bubble' => 'all']);
        $distinct = $this->call('link', 'GET', self::FULL, '', ['bubble' => 'distinct']);

        $this->assertSame(200, $all->get_code());
        $this->assertSame(200, $distinct->get_code());
        $this->lti_assert_matches_fixture(
            'services/tool_settings_link_all.json',
            json_decode($all->get_body(), true),
            $this->tokens()
        );
        $this->lti_assert_matches_fixture(
            'services/tool_settings_link_distinct.json',
            json_decode($distinct->get_body(), true),
            $this->tokens()
        );
    }

    /**
     * A token without the tool settings scope is refused at every level, and nothing is written.
     */
    public function test_a_token_without_the_scope_is_refused(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $other = ['https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly'];

        foreach (['system', 'context', 'link'] as $level) {
            $response = $this->call($level, 'GET', self::SIMPLE, '', [], $other);
            $this->assertSame(401, $response->get_code(), $level);
            $this->assertEmpty($response->get_body(), $level);
            $response = $this->call($level, 'PUT', self::SIMPLE, json_encode(['key' => 'value']), [], $other);
            $this->assertSame(401, $response->get_code(), $level);
        }
        foreach (['system', 'context', 'link'] as $level) {
            $this->assertSame([], json_decode($this->call($level, 'GET', self::SIMPLE)->get_body(), true), $level);
        }
    }

    /**
     * A tool cannot use the settings of another tool.
     */
    public function test_a_token_of_another_tool_is_refused(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $othertool = $this->create_tool('Other tool', 'other');

        // The other tool's token, on the settings of the first tool.
        $response = $this->lti_service_call(
            \ltiservice_toolsettings\local\resources\systemsettings::class,
            \ltiservice_toolsettings\local\service\toolsettings::class,
            $othertool,
            'GET',
            "/tool/{$this->typeid}",
            [self::SCOPE],
            self::SIMPLE,
            '',
            ['type_id' => (string) $this->typeid]
        );

        $this->assertSame(401, $response->get_code());
    }

    /**
     * A tool proxy that signs with a secret other than its own is refused and nothing is written.
     */
    public function test_a_request_signed_with_another_secret_is_refused(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity(true);

        foreach (['system', 'context', 'link'] as $level) {
            $response = $this->call($level, 'PUT', self::SIMPLE, json_encode(['key' => 'value']), [], [], 'notthesecret');
            $this->assertDebuggingCalled();
            $this->assertSame(401, $response->get_code(), $level);
            $this->assertSame([], json_decode($this->call($level, 'GET', self::SIMPLE)->get_body(), true), $level);
        }
    }

    /**
     * A tool that is not an LTI 2.0 tool proxy can write its settings.
     */
    public function test_a_tool_that_is_not_a_tool_proxy_writes_its_settings(): void {
        $this->markTestSkipped(
            'Skipped until MDL-90476 is fixed: saving settings with no tool proxy fails, as toolproxyid is NOT NULL.'
        );
        $this->resetAfterTest();
        $this->create_course_and_activity();

        foreach (['system', 'context', 'link'] as $level) {
            $response = $this->call($level, 'PUT', self::SIMPLE, json_encode(['key' => $level]));
            $this->assertSame(200, $response->get_code(), $level);
            $this->assertSame(['key' => $level], json_decode($this->call($level, 'GET', self::SIMPLE)->get_body(), true));
        }
    }

    /**
     * The settings of a link that does not exist give a 404 and not an error.
     */
    public function test_the_settings_of_an_unknown_link_are_a_404(): void {
        $this->markTestSkipped(
            'Skipped until MDL-90480 is fixed: an unknown link raises a missing record exception, not a 404.'
        );
        $this->resetAfterTest();
        $this->create_course_and_activity();

        $this->lti->id = $this->lti->id + 1000;
        $response = $this->call('link', 'GET', self::SIMPLE);

        $this->assertSame(404, $response->get_code());
    }
}
