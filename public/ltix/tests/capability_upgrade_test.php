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

/**
 * Tests that permissions customised on the 5.3 mod_lti capabilities carry over to the new core_ltix capabilities on upgrade.
 *
 * The core upgrade adds the new moodle/ltix:* capabilities through update_capabilities(), which copies permissions from the
 * capability named in 'clonepermissionsfrom'. These tests start from a 5.3-shaped site (old capabilities present, new ones absent,
 * with customised permissions), run that same function, and check nothing is gained or lost.
 *
 * @package    core_ltix
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class capability_upgrade_test extends \advanced_testcase {
    /** @var string[] The 5.3 capability => the core_ltix capability that replaces it. */
    private const CAPABILITY_MAP = [
        'mod/lti:manage' => 'moodle/ltix:manage',
        'mod/lti:admin' => 'moodle/ltix:admin',
        'mod/lti:addcoursetool' => 'moodle/ltix:addcoursetool',
        'mod/lti:addpreconfiguredinstance' => 'moodle/ltix:viewcoursetools',
        'mod/lti:requesttooladd' => 'moodle/ltix:requesttooladd',
    ];

    /** @var int Role id of the editing teacher role. */
    private int $teacherrole;

    /** @var int Role id of the student role. */
    private int $studentrole;

    /** @var int Role id of the manager role. */
    private int $managerrole;

    /** @var int Role id of a custom role created for the test. */
    private int $customrole;

    /** @var int Context id of the system context. */
    private int $systemcontext;

    /** @var int Context id of a course. */
    private int $coursecontext;

    /** @var int Context id of a course category. */
    private int $categorycontext;

    /**
     * Turn the test site into a 5.3-shaped one with customised permissions on the old capabilities.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $this->studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $this->customrole = $this->getDataGenerator()->create_role(['shortname' => 'ltiupgradecustom']);

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->systemcontext = \context_system::instance()->id;
        $this->coursecontext = \context_course::instance($course->id)->id;
        $this->categorycontext = \context_coursecat::instance($category->id)->id;

        // Remove the new capabilities and any permissions on them, then add the old ones back, as on a 5.3 site.
        $new = array_values(self::CAPABILITY_MAP);
        $old = array_keys(self::CAPABILITY_MAP);
        $DB->delete_records_list('role_capabilities', 'capability', array_merge($new, $old));
        $DB->delete_records_list('capabilities', 'name', array_merge($new, $old));
        foreach ($old as $name) {
            $DB->insert_record('capabilities', (object) [
                'name' => $name, 'captype' => 'write', 'contextlevel' => CONTEXT_COURSE, 'component' => 'mod_lti',
                'riskbitmask' => 0,
            ]);
        }
        \cache::make('core', 'capabilities')->delete('core_capabilities');

        // Customised permissions: site-level, plus overrides at course and category level.
        // The editing teacher has 'manage' removed and 'addpreconfiguredinstance' prevented. A custom role and the student role
        // have been given things they do not have by default.
        $this->set_permission('mod/lti:manage', $this->managerrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:manage', $this->customrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:admin', $this->managerrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:addcoursetool', $this->managerrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:addcoursetool', $this->customrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:addpreconfiguredinstance', $this->managerrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:addpreconfiguredinstance', $this->teacherrole, $this->systemcontext, CAP_PREVENT);
        $this->set_permission('mod/lti:addpreconfiguredinstance', $this->customrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:requesttooladd', $this->studentrole, $this->systemcontext, CAP_ALLOW);
        $this->set_permission('mod/lti:manage', $this->studentrole, $this->coursecontext, CAP_ALLOW);
        $this->set_permission('mod/lti:addcoursetool', $this->teacherrole, $this->coursecontext, CAP_PREVENT);
        $this->set_permission('mod/lti:addpreconfiguredinstance', $this->studentrole, $this->categorycontext, CAP_PROHIBIT);
    }

    /**
     * Record a permission directly, as it would have been stored on the 5.3 site.
     *
     * @param string $capability the capability name.
     * @param int $roleid the role.
     * @param int $contextid the context.
     * @param int $permission the CAP_* value.
     */
    private function set_permission(string $capability, int $roleid, int $contextid, int $permission): void {
        global $DB;
        $DB->insert_record('role_capabilities', (object) [
            'contextid' => $contextid, 'roleid' => $roleid, 'capability' => $capability, 'permission' => $permission,
            'timemodified' => time(), 'modifierid' => 0,
        ]);
    }

    /**
     * Get the permissions stored for a capability as a sorted list of "role:context:permission".
     *
     * @param string $capability the capability name.
     * @return string[] the entries.
     */
    private function get_permissions(string $capability): array {
        global $DB;
        $entries = [];
        foreach ($DB->get_records('role_capabilities', ['capability' => $capability]) as $record) {
            $entries[] = "{$record->roleid}:{$record->contextid}:{$record->permission}";
        }
        sort($entries);
        return $entries;
    }

    /**
     * Every new capability ends up with exactly the permissions of the capability it replaces, in every context.
     */
    public function test_new_capabilities_copy_customised_permissions(): void {
        $this->assertNotEmpty($this->get_permissions('mod/lti:manage'));

        update_capabilities('moodle');

        foreach (self::CAPABILITY_MAP as $old => $new) {
            $this->assertSame($this->get_permissions($old), $this->get_permissions($new), "$new should match $old");
        }
    }

    /**
     * Permissions that were customised at site level are not replaced by the archetype defaults.
     */
    public function test_site_level_customisations_are_not_reset_to_defaults(): void {
        global $DB;

        update_capabilities('moodle');

        // The editing teacher had 'manage' removed at site level, so must not gain it back from the archetype.
        $this->assertFalse($DB->record_exists('role_capabilities', [
            'capability' => 'moodle/ltix:manage', 'roleid' => $this->teacherrole, 'contextid' => $this->systemcontext,
        ]));
        // A custom role and the student role keep what they had been given.
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:manage', 'roleid' => $this->customrole, 'contextid' => $this->systemcontext,
        ]));
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:requesttooladd', 'roleid' => $this->studentrole, 'contextid' => $this->systemcontext,
        ]));
        // A prevented capability stays prevented, rather than becoming allowed.
        $this->assertEquals(CAP_PREVENT, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:viewcoursetools', 'roleid' => $this->teacherrole, 'contextid' => $this->systemcontext,
        ]));
    }

    /**
     * Overrides at course and category level are copied, including prevent and prohibit.
     */
    public function test_role_overrides_are_copied(): void {
        global $DB;

        update_capabilities('moodle');

        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:manage', 'roleid' => $this->studentrole, 'contextid' => $this->coursecontext,
        ]));
        $this->assertEquals(CAP_PREVENT, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:addcoursetool', 'roleid' => $this->teacherrole, 'contextid' => $this->coursecontext,
        ]));
        $this->assertEquals(CAP_PROHIBIT, $DB->get_field('role_capabilities', 'permission', [
            'capability' => 'moodle/ltix:viewcoursetools', 'roleid' => $this->studentrole, 'contextid' => $this->categorycontext,
        ]));
    }

    /**
     * mod/lti:addpreconfiguredinstance is still enforced by mod_lti, so its permissions are copied, not moved.
     */
    public function test_still_enforced_capability_keeps_its_own_permissions(): void {
        $before = $this->get_permissions('mod/lti:addpreconfiguredinstance');
        $this->assertNotEmpty($before);

        update_capabilities('moodle');

        $this->assertSame($before, $this->get_permissions('mod/lti:addpreconfiguredinstance'));
        $this->assertNotEmpty($this->get_permissions('moodle/ltix:viewcoursetools'));
    }

    /**
     * Running the capability update again changes nothing and does not fail.
     */
    public function test_repeating_the_update_is_harmless(): void {
        update_capabilities('moodle');
        $first = [];
        foreach (self::CAPABILITY_MAP as $new) {
            $first[$new] = $this->get_permissions($new);
        }

        update_capabilities('moodle');

        foreach (self::CAPABILITY_MAP as $new) {
            $this->assertSame($first[$new], $this->get_permissions($new));
        }
    }
}
