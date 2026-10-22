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
 * Unit tests for mod_lti upgradelib.
 *
 * @package    mod_lti
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgradelib_test extends \advanced_testcase {

    /**
     * Prepares things before this test case is initialised.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/db/upgradelib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Test covering the creation of links for existing tools during upgrade.
     *
     * @return void
     */
    public function test_create_resource_links(): void {
        $this->resetAfterTest();
        global $DB;

        /** @var \mod_lti_generator $ltigenerator */
        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('mod_lti');

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Site tool.
        $tool1id = $ltigenerator->create_tool_types([
            'name' => 'Test tool 1',
            'description' => 'Good example description',
            'tooldomain' => 'example.com',
            'baseurl' => 'https://example.com/launch',
            'lti_contentitem' => 1,
        ]);

        // Create tool1 instance in course1.
        $tool1instance1id = $this->create_legacy_instance($course1, $tool1id);
        // Create tool1 instance in course2.
        $tool1instance2id = $this->create_legacy_instance($course2, $tool1id);

        // Course tool.
        $tool2id = $ltigenerator->create_course_tool_types([
            'name' => 'Test tool 2',
            'description' => 'Good example description',
            'tooldomain' => 'example2.com',
            'baseurl' => 'https://example2.com/launch',
            'lti_contentitem' => 1,
            'course' => $course2->id,
        ]);

        // Create tool2 instance in course2.
        $tool2instanceid = $this->create_legacy_instance($course2, $tool2id);
        // Manually configured instance (legacy).
        $manuallyconfigureinstanceid = $this->create_legacy_instance($course1, null, 'https://example3.com/launch');
        // Simulate restored tool instance. Instance where typeid = null, which can occur during restore when a tool is
        // not included and can't be linked.
        $restoredinstanceid = $this->create_legacy_instance($course1, null, 'https://example3.com/launch');

        // The ids of all lti instances.
        $instanceids = [
            $tool1instance1id,
            $tool1instance2id,
            $tool2instanceid,
            $manuallyconfigureinstanceid,
            $restoredinstanceid,
        ];

        // Create the links, verifying that a link is created for each lti instance, and has the same id.
        $migrationhelper = new lti_migration_upgrade_helper();
        $migrationhelper->create_resource_links();

        $links = $DB->get_records('lti_resource_link');
        sort($links);
        $linkids = array_column($links, 'id');
        $this->assertEquals($instanceids, $linkids);

        // Insert another link, verifying that the table sequence has been reset properly as part of the link creation.
        $newlink = new \core_ltix\local\lticore\models\resource_link(0, (object) [
            'typeid' => 4,
            'component' => 'mod_lti',
            'itemtype' => 'mod_lti:activityplacement',
            'itemid' => 432,
            'contextid' => 33,
            'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
            'title' => 'My resource',
        ]);
        $newlink->save();
        $this->assertEquals(max($instanceids) + 1, $newlink->get('id'));

        // Verify idempotence.
        $migrationhelper->create_resource_links();
        $links = $DB->get_records('lti_resource_link');
        sort($links);
        $linkids = array_column($links, 'id');
        $this->assertEquals(array_merge($instanceids, [$newlink->get('id')]), $linkids);
    }

    /**
     * Creates a simulated legacy LTI instance without resource links.
     *
     * This method inserts LTI instance data directly into the relevant database tables, intentionally skipping the
     * creation of resource links in order to accurately simulate a legacy tool instance. It serves as an alternative to
     * the generator method create_instance(), which is not suitable for creating legacy instances since it always
     * automatically creates resource links for any new instance.
     *
     * @param object $course The course object
     * @param int|null $typeid Type ID of the LTI tool
     * @param string $toolurl The tool's URL
     * @return int The ID od the created tool instance
     */
    private function create_legacy_instance(object $course, ?int $typeid, string $toolurl = ''): int {
        global $DB;

        $toolinstanceid = $DB->insert_record('lti', [
            'course' => $course->id,
            'typeid' => $typeid,
            'toolurl' => $toolurl,
        ]);

        $cmdid = $DB->insert_record('course_modules', [
            'course' => $course->id,
            'module' => $DB->get_field('modules', 'id', ['name' => 'lti']),
            'instance' => $toolinstanceid
        ]);

        $DB->insert_record('context', [
            'contextlevel' => CONTEXT_MODULE,
            'instanceid' => $cmdid
        ]);

        return $toolinstanceid;
    }
}
