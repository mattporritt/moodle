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

/**
 * Course copy tests.
 *
 * @package    core_backup
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/*
 * Course copy tests.
 */
class core_backup_copy_testcase extends advanced_testcase {
    protected $course;  // Course id used for testing.

    /**
     * Set up actions.
     */
    protected function setUp() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->enableavailability = true;
        $CFG->enablecompletion = true;

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(
            array('format' => 'topics', 'numsections' => 3,
                'enablecompletion' => COMPLETION_ENABLED),
            array('createsections' => true));

    }

    /**
     * Test creating a course copy.
     */
    public function test_create_copy() {

        // Mock up the form data.
        $formdata = new \stdClass;
        $formdata->courseid = $this->course->id;
        $formdata->fullname = 'foo';
        $formdata->shortname = 'bar';
        $formdata->category = 1;
        $formdata->visible = 1;
        $formdata->startdate = 1582376400;
        $formdata->enddate = 0;
        $formdata->idnumber = 123;
        $formdata->userdata = 1;
        $formdata->manualenrols = array(1, 3, 5);

        $coursecopy = new \core_backup\copy\core_backup_copy($formdata);
        $result = $coursecopy->create_copy();

        // Load the controllers, to extract the data we need.
        $bc = \backup_controller::load_controller($result['backupid']);
        $rc = \restore_controller::load_controller($result['restoreid']);

        // Check the backup controller.
        $this->assertEquals($formdata, $bc->get_copy());
        $this->assertEquals($result, $bc->get_copy()->copyids);
        $this->assertEquals(backup::MODE_COPY, $bc->get_mode());
        $this->assertEquals($this->course->id, $bc->get_courseid());
        $this->assertEquals(backup::TYPE_1COURSE, $bc->get_type());

        // Check the restore controller.
        $newcourseid = $rc->get_courseid();
        $newcourse = get_course($newcourseid);

        $this->assertEquals($formdata, $rc->get_copy());
        $this->assertEquals($result, $rc->get_copy()->copyids);
        $this->assertEquals($formdata->fullname, $newcourse->fullname);
        $this->assertEquals(backup::MODE_COPY, $rc->get_mode());
        $this->assertEquals($newcourseid, $rc->get_courseid());

        // Check the created ad-hoc task.
        $now = time();
        $task = \core\task\manager::get_next_adhoc_task($now);

        $this->assertInstanceOf('\\core\\task\\asynchronous_copy_task', $task);
        $this->assertEquals($result, (array)$task->get_custom_data());
        $this->assertFalse($task->is_blocking());

        \core\task\manager::adhoc_task_complete($task);
    }

    /**
     * Test getting the current copies.
     */
    public function test_get_copies() {
        global $USER;

        // Mock up the form data.
        $formdata = new \stdClass;
        $formdata->courseid = $this->course->id;
        $formdata->fullname = 'foo';
        $formdata->shortname = 'bar';
        $formdata->category = 1;
        $formdata->visible = 1;
        $formdata->startdate = 1582376400;
        $formdata->enddate = 0;
        $formdata->userdata = 1;
        $formdata->manualenrols = array(1, 3, 5);

        $formdata2 = clone($formdata);
        $formdata2->shortname = 'tree';

        // Create some copies.
        $coursecopy = new \core_backup\copy\core_backup_copy($formdata);
        $result = $coursecopy->create_copy();

        // Backup, awaiting.
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id);
        $this->assertEquals($result['backupid'], $copies[0]->backupid);
        $this->assertEquals($result['restoreid'], $copies[0]->restoreid);
        $this->assertEquals(\backup::STATUS_AWAITING, $copies[0]->status);
        $this->assertEquals(\backup::OPERATION_BACKUP, $copies[0]->operation);

        $bc = \backup_controller::load_controller($result['backupid']);

        // Backup, in progress.
        $bc->set_status(\backup::STATUS_EXECUTING);
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id);
        $this->assertEquals($result['backupid'], $copies[0]->backupid);
        $this->assertEquals($result['restoreid'], $copies[0]->restoreid);
        $this->assertEquals(\backup::STATUS_EXECUTING, $copies[0]->status);
        $this->assertEquals(\backup::OPERATION_BACKUP, $copies[0]->operation);

        // Restore, ready to process.
        $bc->set_status(\backup::STATUS_FINISHED_OK);
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id);
        $this->assertEquals($result['backupid'], $copies[0]->backupid);
        $this->assertEquals($result['restoreid'], $copies[0]->restoreid);
        $this->assertEquals(\backup::STATUS_REQUIRE_CONV, $copies[0]->status);
        $this->assertEquals(\backup::OPERATION_RESTORE, $copies[0]->operation);

        // No records.
        $bc->set_status(\backup::STATUS_FINISHED_ERR);
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id);
        $this->assertEmpty($copies);

        $coursecopy2 = new \core_backup\copy\core_backup_copy($formdata2);
        $result2 = $coursecopy2->create_copy();
        // Set the second copy to be complete.
        $bc = \backup_controller::load_controller($result2['backupid']);
        $bc->set_status(\backup::STATUS_FINISHED_OK);
        // Set the restore to be finished.
        $rc = \backup_controller::load_controller($result2['restoreid']);
        $rc->set_status(\backup::STATUS_FINISHED_OK);

        // No records.
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id);
        $this->assertEmpty($copies);
    }

    /**
     * Test getting the current copies.
     */
    public function test_get_copies_course() {
        global $USER;

        // Mock up the form data.
        $formdata = new \stdClass;
        $formdata->courseid = $this->course->id;
        $formdata->fullname = 'foo';
        $formdata->shortname = 'bar';
        $formdata->category = 1;
        $formdata->visible = 1;
        $formdata->startdate = 1582376400;
        $formdata->enddate = 0;
        $formdata->userdata = 1;
        $formdata->manualenrols = array(1, 3, 5);

        // Create some copies.
        $coursecopy = new \core_backup\copy\core_backup_copy($formdata);
        $coursecopy->create_copy();

        // No copies match this course id.
        $copies = \core_backup\copy\core_backup_copy::get_copies($USER->id, ($this->course->id + 1));
        $this->assertEmpty($copies);
    }
}

