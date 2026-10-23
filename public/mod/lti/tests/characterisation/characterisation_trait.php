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
            $courseid, $lti->cmid ?? 0, $lti, $config, $messagetype, 'Selected title', 'Selected text', $foruserid
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
        global $CFG, $SESSION, $USER, $DB, $PAGE, $OUTPUT;
        $_POST = $_REQUEST = $params;
        set_error_handler(fn() => true, E_ALL);
        $thrown = null;
        ob_start();
        try {
            include($CFG->dirroot . '/mod/lti/auth.php');
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $output = ob_get_clean();
        restore_error_handler();
        $_POST = $_REQUEST = [];
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
}
