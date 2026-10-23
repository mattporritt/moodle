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
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * The seam between the LTI characterisation tests and the LTI code they exercise.
 *
 * The characterisation tests assert behaviour, not structure, so that they can run unchanged while LTI moves from mod_lti
 * to core_ltix. Everything that names a mod_lti function, class, capability or path lives in this trait. When a migration
 * slice moves one of them, this is the only file that changes, and the assertions in the tests stay as they are.
 *
 * Names used here and where they move to (see the P7 report):
 *  - lti_get_launch_data()      => \core_ltix\helper::get_launch_data()
 *  - mod_lti_external (WS)      => core_ltix external functions (the mod_lti ones stay as deprecated wrappers)
 *  - lti_initiate_login()       => \core_ltix\helper::initiate_login()
 *  - lti_delete_type()          => \core_ltix\helper::delete_type()
 *  - jwks_helper (mod_lti)      => \core_ltix\local\ltiopenid\jwks_helper
 *  - mod_lti generator          => core_ltix generator (create_tool_types)
 *  - ltiservice_* tool settings => ltixservice_* tool settings
 *  - mod/lti/auth.php           => stays as a thin wrapper, then /ltix/auth.php
 *  - mod/lti:* capabilities     => moodle/ltix:* (tool management) and mod/lti:view (unchanged)
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait characterisation_trait {
    /**
     * Get the launch data for an activity as the launching user would receive it.
     *
     * @param \stdClass $lti the lti record, with ->cmid set.
     * @param string $nonce the nonce to sign into an LTI 1.3 launch.
     * @param string $messagetype the message type of the launch.
     * @param int $foruserid the user a submission review launch is for.
     * @return array [endpoint, parameters]
     */
    protected function lti_launch_data(
        \stdClass $lti,
        string $nonce = 'nonce',
        string $messagetype = '',
        int $foruserid = 0
    ): array {
        return lti_get_launch_data($lti, $nonce, $messagetype, $foruserid);
    }

    /**
     * Create a site level tool.
     *
     * @param array $config tool type fields and lti_* configuration, as accepted by the generator.
     * @return int the tool type id.
     */
    protected function lti_create_tool(array $config): int {
        return $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types($config);
    }

    /**
     * Create a course tool, only available in one course.
     *
     * @param array $config tool type fields and lti_* configuration, as accepted by the generator.
     * @return int the tool type id.
     */
    protected function lti_create_course_tool(array $config): int {
        return $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_course_tool_types($config);
    }

    /**
     * Create an LTI activity and return its record, ready to be launched.
     *
     * @param int $courseid the course.
     * @param array $record activity fields, such as typeid, name, toolurl, resourcekey and password.
     * @return \stdClass the lti record, with ->cmid set.
     */
    protected function lti_create_activity(int $courseid, array $record): \stdClass {
        global $DB;
        $instance = $this->getDataGenerator()->create_module('lti', ['course' => $courseid] + $record);
        $lti = $DB->get_record('lti', ['id' => $instance->id], '*', MUST_EXIST);
        $lti->cmid = $instance->cmid;
        return $lti;
    }

    /**
     * Delete a tool the way an administrator does.
     *
     * @param int $typeid the tool type id.
     */
    protected function lti_delete_tool(int $typeid): void {
        lti_delete_type($typeid);
    }

    /**
     * The capability that lets a user view (launch) an LTI activity.
     *
     * @return string
     */
    protected function lti_view_capability(): string {
        return 'mod/lti:view';
    }

    /**
     * The tool type setting that configures an LTI service for a tool, e.g. 'memberships' or 'toolsettings'.
     *
     * @param string $setting the setting name, e.g. 'memberships'.
     * @param int $value the value.
     * @return array the key and value to add to the tool configuration.
     */
    protected function lti_service_setting(string $setting, int $value): array {
        return ["ltiservice_{$setting}" => $value];
    }

    /**
     * Start an LTI 1.3 launch as Moodle does, and return the fields Moodle posts to the tool's login initiation URL.
     *
     * @param int $courseid the course.
     * @param int $typeid the tool type.
     * @param \stdClass|null $lti the activity, with ->cmid set; null for a content selection (deep linking) launch.
     * @param string $messagetype the message type.
     * @param int $foruserid the user a submission review launch is for.
     * @return array the login request fields: iss, target_link_uri, login_hint, lti_message_hint, client_id and so on.
     */
    protected function lti_start_login(
        int $courseid,
        int $typeid,
        ?\stdClass $lti = null,
        string $messagetype = 'basic-lti-launch-request',
        int $foruserid = 0
    ): array {
        $config = lti_get_type_type_config($typeid);
        $form = lti_initiate_login(
            $courseid,
            $lti->cmid ?? 0,
            $lti,
            $config,
            $messagetype,
            'Selected title',
            'Selected text',
            $foruserid
        );
        return $this->lti_form_fields($form);
    }

    /**
     * Send the tool's authentication request to the platform's auth endpoint, in process, as the current user.
     *
     * Returns what the endpoint printed and what it threw. A refusal is either; both mean no id_token was issued. PHP warnings
     * are silenced for the call: the endpoint's refusal of an unknown launch currently works by tripping them.
     *
     * @param array $params the request parameters (scope, response_type, client_id, redirect_uri, login_hint, ...).
     * @return array [output, throwable or null]
     */
    protected function lti_auth_request(array $params): array {
        return $this->lti_run_script('/mod/lti/auth.php', [], $params);
    }

    /**
     * Run a Moodle script, as the current user, in process, and return what it printed and what it threw.
     *
     * The script is found from a path or URL of the site, as a browser would reach it, so that a script that moves is followed
     * by the URLs Moodle itself hands out. PHP warnings are silenced for the call. A script that ends with exit cannot be run.
     *
     * @param string $path the path or URL of the script, relative to the site root.
     * @param array $get query parameters, in addition to any in the path.
     * @param array $post form parameters.
     * @return array [output, throwable or null]
     */
    protected function lti_run_script(string $path, array $get = [], array $post = []): array {
        global $CFG, $SESSION, $USER, $DB, $PAGE, $OUTPUT;
        $path = preg_replace('~^' . preg_quote($CFG->wwwroot, '~') . '~', '', $path);
        parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $query);
        $script = $CFG->dirroot . parse_url($path, PHP_URL_PATH);
        $this->assertFileExists($script, "There is no script at $path");

        $_GET = $get + $query;
        $_POST = $post;
        $_REQUEST = $_GET + $_POST;
        // Scripts load config.php by a relative path, so run them from their own directory like the web server does.
        $workingdirectory = getcwd();
        chdir(dirname($script));
        set_error_handler(fn() => true, E_ALL);
        $thrown = null;
        ob_start();
        try {
            include($script);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $output = ob_get_clean();
        restore_error_handler();
        chdir($workingdirectory);
        $_GET = $_POST = $_REQUEST = [];
        return [$output, $thrown];
    }

    /**
     * Read the hidden fields of the auto-submitting form that LTI endpoints print.
     *
     * @param string $html the form.
     * @return array field name => value, and 'action' for the form's action.
     */
    protected function lti_form_fields(string $html): array {
        $fields = [];
        if (trim($html) === '') {
            return $fields;
        }
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
     * The scripts that tools and other servers call without a Moodle session: the LTI service endpoints, the token and key set
     * endpoints, and the registration endpoints. Paths are relative to the Moodle code root.
     *
     * @return string[]
     */
    protected function lti_sessionless_endpoint_scripts(): array {
        return [
            'mod/lti/service.php', 'mod/lti/services.php', 'mod/lti/token.php', 'mod/lti/certs.php',
            'mod/lti/openid-configuration.php', 'mod/lti/openid-registration.php',
        ];
    }

    /**
     * The JWK set the platform publishes at its key set endpoint (certs.php).
     *
     * @return array
     */
    protected function lti_platform_key_set(): array {
        return \mod_lti\local\ltiopenid\jwks_helper::get_jwks();
    }

    /**
     * The LTI entries in the activity chooser, as the user sees them in a course.
     *
     * The chooser is core's content item service, so this only names the service, not any LTI code. It includes the "External
     * tool" entry that adds an activity with no tool.
     *
     * @param \stdClass $user the user.
     * @param \stdClass $course the course.
     * @return string[] the links of the entries that belong to LTI, by title.
     */
    protected function lti_chooser_entries(\stdClass $user, \stdClass $course): array {
        $service = new \core_course\local\service\content_item_service(
            new \core_course\local\repository\content_item_readonly_repository()
        );
        $entries = [];
        foreach ($service->get_content_items_for_user_in_course($user, $course) as $item) {
            if ($item->componentname === 'mod_lti') {
                $entries[$item->title] = $item->link;
            }
        }
        return $entries;
    }

    /**
     * Show or hide a site tool in one course's activity chooser, as a teacher does on the course tools page.
     *
     * @param int $typeid the tool type.
     * @param int $courseid the course.
     * @param bool $show whether to show it.
     */
    protected function lti_set_chooser_visibility(int $typeid, int $courseid, bool $show): void {
        \mod_lti\local\types_helper::override_type_showinactivitychooser(
            $typeid,
            $courseid,
            \core\context\course::instance($courseid),
            $show
        );
    }

    /**
     * Add a line item that belongs to the tool, not to an activity, as a tool does with a POST to the AGS line items endpoint.
     *
     * @param int $courseid the course.
     * @param int $typeid the tool type.
     * @param string $label the line item's label.
     * @param float $maximumscore the line item's maximum score.
     * @param string|null $resourceid the tool's resource id for the line item.
     * @param string|null $tag the tool's tag for the line item.
     * @return int the grade item id.
     */
    protected function lti_add_standalone_line_item(
        int $courseid,
        int $typeid,
        string $label,
        float $maximumscore,
        ?string $resourceid,
        ?string $tag
    ): int {
        $service = new \ltiservice_gradebookservices\local\service\gradebookservices();
        return $service->add_standalone_lineitem(
            (string) $courseid,
            $label,
            $maximumscore,
            'https://tool.example.com/lineitem',
            null,
            $resourceid,
            $tag,
            $typeid
        );
    }

    /**
     * The AGS line items of a course, in a form that does not depend on any id: what the tool sees of them.
     *
     * @param int $courseid the course.
     * @return array[] one entry per line item, sorted by label.
     */
    protected function lti_line_items(int $courseid): array {
        global $DB;
        $sql = "SELECT gbs.id, gi.itemname AS label, gi.grademax, gbs.resourceid, gbs.tag, gbs.subreviewurl, gbs.subreviewparams,
                       l.name AS activity, gbs.typeid, gi.courseid AS gradeitemcourse
                  FROM {ltiservice_gradebookservices} gbs
                  JOIN {grade_items} gi ON gi.id = gbs.gradeitemid
             LEFT JOIN {lti} l ON l.id = gbs.ltilinkid AND l.course = gbs.courseid
                 WHERE gbs.courseid = :courseid
              ORDER BY gi.itemname";
        $items = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $record) {
            unset($record->id);
            $record->grademax = (float) $record->grademax;
            // A line item without a submission review is null or empty depending on how it got there; the tool sees the same.
            $record->subreviewurl = $record->subreviewurl ?: null;
            $record->subreviewparams = $record->subreviewparams ?: null;
            $items[] = (array) $record;
        }
        return $items;
    }

    /**
     * Get the launch data of an activity through the web service the Moodle app uses.
     *
     * @param int $ltiid the activity.
     * @return array the web service result: endpoint and a list of name and value parameters.
     */
    protected function lti_ws_get_tool_launch_data(int $ltiid): array {
        $result = \mod_lti_external::get_tool_launch_data($ltiid);
        return \core_external\external_api::clean_returnvalue(\mod_lti_external::get_tool_launch_data_returns(), $result);
    }

    /**
     * Call an LTI service endpoint as a tool does, in process, with a bearer token granted the scope.
     *
     * @param string $resourceclass the class of the endpoint's resource, e.g. the NRPS context memberships resource.
     * @param string $serviceclass the class of the service that owns the resource.
     * @param int $typeid the tool making the call.
     * @param string $method the HTTP method.
     * @param string $pathinfo the path of the request after services.php.
     * @param string[] $scopes the scopes the token is granted.
     * @param string $accept the Accept header, or the Content-Type for a POST.
     * @param string $body the request body.
     * @param array $query query parameters.
     * @return \mod_lti\local\ltiservice\response the response.
     */
    protected function lti_service_call(
        string $resourceclass,
        string $serviceclass,
        int $typeid,
        string $method,
        string $pathinfo,
        array $scopes,
        string $accept = '',
        string $body = '',
        array $query = []
    ): \mod_lti\local\ltiservice\response {
        $token = lti_new_access_token($typeid, $scopes);
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['PATH_INFO'] = $pathinfo;
        $_SERVER['HTTP_Authorization'] = 'Bearer ' . $token->token;
        $_SERVER[$method === 'GET' ? 'HTTP_ACCEPT' : 'CONTENT_TYPE'] = $accept;
        $_GET = $query + ['type_id' => (string) $typeid];

        $response = new \mod_lti\local\ltiservice\response();
        if ($method === 'GET') {
            $response->set_accept($accept);
        } else {
            $response->set_content_type($accept);
        }
        $response->set_request_data($body);
        $resource = new $resourceclass(new $serviceclass());
        $resource->execute($response);
        $_GET = [];
        return $response;
    }

    /**
     * Assert that data is exactly what a recorded fixture holds.
     *
     * Placeholders in the fixture are replaced by the values the test created. Keys are compared without regard to their
     * order, lists with regard to theirs.
     *
     * @param string $fixture the path of the JSON fixture, relative to tests/fixtures/characterisation.
     * @param array $actual the data.
     * @param array $tokens placeholder => value, applied to the fixture text before it is decoded.
     */
    protected function lti_assert_matches_fixture(string $fixture, array $actual, array $tokens): void {
        $json = file_get_contents(__DIR__ . "/../fixtures/characterisation/{$fixture}");
        $expected = json_decode(strtr($json, $tokens), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($this->lti_canonical($expected), $this->lti_canonical($actual), "The data differs from $fixture");
    }

    /**
     * Sort associative arrays by key, recursively, so that comparison ignores the order of keys but not the order of lists.
     *
     * @param mixed $value the value.
     * @return mixed the value.
     */
    protected function lti_canonical(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map([$this, 'lti_canonical'], $value);
        if (!array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }
}
