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
}
