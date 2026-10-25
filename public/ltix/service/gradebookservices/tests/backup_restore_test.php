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

namespace ltixservice_gradebookservices;

use backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for the backup and restore of AGS line items, and for course copy.
 *
 * @package    ltixservice_gradebookservices
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_ltixservice_gradebookservices_plugin
 * @covers     \restore_ltixservice_gradebookservices_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var \stdClass The course with the line items. */
    private \stdClass $course;

    /** @var array What the tool sees of the line items of the course. */
    private array $expected;

    /**
     * Create a course with a tool that has grade synchronisation, a graded activity and a standalone line item.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_lti');
        $typeid = $generator->create_tool_types([
            'name' => 'AGS tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => 'LTI-1p0',
            'lti_resourcekey' => 'key', 'lti_password' => 'secret', 'ltixservice_gradesynchronization' => 2,
            'coursevisible' => 1, // Preconfigured, so the activity placement can be enabled for the tool.
        ]);
        $this->ensure_activity_placement($typeid);
        $this->getDataGenerator()->create_module('lti', [
            'course' => $this->course->id, 'name' => 'Graded link', 'typeid' => $typeid,
            'instructorchoiceacceptgrades' => 1, 'grade' => 80, 'resourcekey' => '', 'password' => '',
            'lineitemresourceid' => 'resource-1', 'lineitemtag' => 'tag-1',
            'lineitemsubreviewurl' => 'https://tool.example.com/review', 'lineitemsubreviewparams' => 'mode=1',
        ]);
        $service = new local\service\gradebookservices();
        $service->add_standalone_lineitem(
            (string) $this->course->id,
            'Standalone item',
            55,
            'https://tool.example.com/lineitem',
            null,
            'resource-2',
            'tag-2',
            $typeid
        );

        $this->expected = $this->line_items_without_course_ids($this->course->id);
        $this->assertCount(2, $this->expected, 'The course should start with two line items');
    }

    /**
     * Give the tool an activity placement where placements exist and the tool was not given one when it was created.
     *
     * @param int $typeid the tool type id.
     */
    private function ensure_activity_placement(int $typeid): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('lti_placement')) {
            return;
        }
        $placementtypeid = $DB->get_field('lti_placement_type', 'id', ['type' => 'mod_lti:activityplacement'], MUST_EXIST);
        if (!$DB->record_exists('lti_placement', ['toolid' => $typeid, 'placementtypeid' => $placementtypeid])) {
            $this->getDataGenerator()->get_plugin_generator('core_ltix')->create_tool_placements([
                'toolid' => $typeid,
                'placementtypeid' => $placementtypeid,
            ]);
        }
    }

    /**
     * The line items of a course, without the ids that differ between a course and its copy.
     *
     * @param int $courseid the course.
     * @return array[] one entry per line item, sorted by label.
     */
    private function line_items_without_course_ids(int $courseid): array {
        global $DB;
        $sql = "SELECT gbs.id, gi.itemname AS label, gi.grademax, gbs.resourceid, gbs.tag, gbs.subreviewurl,
                       gbs.subreviewparams, l.name AS activity, gbs.typeid
                  FROM {ltixservice_gradebookservices} gbs
                  JOIN {grade_items} gi ON gi.id = gbs.gradeitemid
             LEFT JOIN {lti} l ON l.id = gbs.ltilinkid AND l.course = gbs.courseid
                 WHERE gbs.courseid = :courseid
                       AND gi.courseid = gbs.courseid
              ORDER BY gi.itemname";
        $items = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $record) {
            unset($record->id);
            $record->grademax = (float) $record->grademax;
            $record->subreviewurl = $record->subreviewurl ?: null;
            $record->subreviewparams = $record->subreviewparams ?: null;
            $items[] = (array) $record;
        }
        return $items;
    }

    /**
     * Back up the course and extract the backup.
     *
     * @return string the backup id, which is also the name of the extracted directory in the backup temp directory.
     */
    private function backup_course(): string {
        global $CFG, $USER;
        $controller = new \backup_controller(
            backup::TYPE_1COURSE,
            $this->course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $controller->get_backupid();
        $controller->execute_plan();
        $file = $controller->get_results()['backup_destination'];
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), "{$CFG->tempdir}/backup/{$backupid}");
        $controller->destroy();
        return $backupid;
    }

    /**
     * Restore an extracted backup into a new course.
     *
     * @param string $backupid the backup id.
     * @return int the new course id.
     */
    private function restore_into_new_course(string $backupid): int {
        global $USER;
        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $this->course->category);
        $restore = new \restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();
        return $newcourseid;
    }

    /**
     * Assert that a course holds the line items the original course holds, and that the original is untouched.
     *
     * @param int $courseid the new course.
     */
    private function assert_line_items_survived(int $courseid): void {
        $this->assertSame($this->expected, $this->line_items_without_course_ids($courseid));
        $this->assertSame($this->expected, $this->line_items_without_course_ids($this->course->id));
    }

    /**
     * The line items are written to the backup, under the plugin element of the core plugin type.
     */
    public function test_backup_contains_line_items(): void {
        global $CFG;
        $backupid = $this->backup_course();
        $xml = '';
        foreach (glob("{$CFG->tempdir}/backup/{$backupid}/activities/lti_*/lti.xml") as $file) {
            $xml .= file_get_contents($file);
        }
        $this->assertStringContainsString('<plugin_ltixservice_gradebookservices_lti>', $xml);
        $this->assertStringContainsString('<resourceid>resource-1</resourceid>', $xml);
        $this->assertStringContainsString('<resourceid>resource-2</resourceid>', $xml);
        $this->assertStringNotContainsString('subplugin_ltiservice_gradebookservices_lti', $xml);
    }

    /**
     * Line items survive a backup restored into a new course.
     */
    public function test_line_items_survive_backup_and_restore(): void {
        $newcourseid = $this->restore_into_new_course($this->backup_course());
        $this->assert_line_items_survived($newcourseid);
    }

    /**
     * Line items in a backup made before the service was a core plugin type (Moodle 5.3 and earlier) are restored.
     */
    public function test_line_items_of_a_legacy_backup_are_restored(): void {
        global $CFG;
        $backupid = $this->backup_course();
        $files = glob("{$CFG->tempdir}/backup/{$backupid}/activities/lti_*/lti.xml");
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            // A 5.3 backup holds the same data under the old subplugin element.
            $xml = file_get_contents($file);
            $this->assertStringContainsString('plugin_ltixservice_gradebookservices_lti', $xml);
            file_put_contents($file, str_replace(
                'plugin_ltixservice_gradebookservices_lti',
                'subplugin_ltiservice_gradebookservices_lti',
                $xml
            ));
        }

        $newcourseid = $this->restore_into_new_course($backupid);
        $this->assert_line_items_survived($newcourseid);
    }

    /**
     * Line items survive a course copy.
     */
    public function test_line_items_survive_course_copy(): void {
        $this->expectOutputRegex('/Course copy: Copy completed/');
        $formdata = (object) [
            'courseid' => $this->course->id, 'fullname' => 'Copied course', 'shortname' => 'COPIED',
            'category' => $this->course->category, 'visible' => 1, 'startdate' => time(), 'enddate' => 0,
            'idnumber' => 'copied', 'userdata' => 0,
        ];
        $result = \copy_helper::create_copy(\copy_helper::process_formdata($formdata));
        $newcourseid = \restore_controller::load_controller($result['restoreid'])->get_courseid();

        $this->runAdhocTasks('\core\task\asynchronous_copy_task');

        $this->assertSame('Copied course', get_course($newcourseid)->fullname);
        $this->assert_line_items_survived($newcourseid);
    }
}
