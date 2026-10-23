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

use backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/characterisation_trait.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Characterisation test: AGS line items survive course backup and restore, and course copy.
 *
 * A course with an LTI activity that has a line item (with a submission review), and a line item the tool created on its own,
 * is backed up and restored into a new course and copied. The line items of the new course have to be what the tool created,
 * attached to the new course's activity and grade items. The test uses core's backup and copy APIs only.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class backup_restore_test extends \advanced_testcase {
    use characterisation_trait;

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
        $typeid = $this->lti_create_tool([
            'name' => 'AGS tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => 'LTI-1p0',
            'lti_resourcekey' => 'key', 'lti_password' => 'secret',
        ] + $this->lti_service_setting('gradesynchronization', 2));
        $this->lti_create_activity($this->course->id, [
            'name' => 'Graded link', 'typeid' => $typeid, 'instructorchoiceacceptgrades' => 1, 'grade' => 80,
            'resourcekey' => '', 'password' => '',
            'lineitemresourceid' => 'resource-1', 'lineitemtag' => 'tag-1',
            'lineitemsubreviewurl' => 'https://tool.example.com/review', 'lineitemsubreviewparams' => 'mode=1',
        ]);
        $this->lti_add_standalone_line_item($this->course->id, $typeid, 'Standalone item', 55, 'resource-2', 'tag-2');

        $this->expected = $this->line_items_without_course_ids($this->course->id);
        $this->assertCount(2, $this->expected, 'The course should start with two line items');
    }

    /**
     * What the tool sees of a course's line items, without the ids that differ between a course and its copy.
     *
     * @param int $courseid the course.
     * @return array[] the line items.
     */
    private function line_items_without_course_ids(int $courseid): array {
        return array_map(function (array $item) {
            unset($item['gradeitemcourse']);
            return $item;
        }, $this->lti_line_items($courseid));
    }

    /**
     * Assert that a course holds the line items the original course holds, with grade items in the course itself.
     *
     * @param int $courseid the new course.
     */
    private function assert_line_items_survived(int $courseid): void {
        $this->assertSame($this->expected, $this->line_items_without_course_ids($courseid));
        foreach ($this->lti_line_items($courseid) as $item) {
            $this->assertEquals($courseid, $item['gradeitemcourse'], 'The line item must have a grade item in the new course');
        }
        // The original is untouched.
        $this->assertSame($this->expected, $this->line_items_without_course_ids($this->course->id));
    }

    /**
     * Line items survive a backup restored into a new course.
     */
    public function test_line_items_survive_backup_and_restore(): void {
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

        $this->assert_line_items_survived($newcourseid);
    }

    /**
     * Line items survive a course copy.
     */
    public function test_line_items_survive_course_copy(): void {
        $this->expectOutputRegex('/Course copy: Copy completed/');
        $formdata = (object) [
            'courseid' => $this->course->id, 'fullname' => 'Copied course', 'shortname' => 'COPIED',
            'category' => $this->course->category, 'visible' => 1, 'startdate' => time(), 'enddate' => 0, 'idnumber' => 'copied', 'userdata' => 0,
        ];
        $result = \copy_helper::create_copy(\copy_helper::process_formdata($formdata));
        $newcourseid = \restore_controller::load_controller($result['restoreid'])->get_courseid();

        $this->runAdhocTasks('\core\task\asynchronous_copy_task');

        $this->assertSame('Copied course', get_course($newcourseid)->fullname);
        $this->assert_line_items_survived($newcourseid);
    }
}
