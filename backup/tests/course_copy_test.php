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
require_once($CFG->libdir . '/completionlib.php');

/*
 * Course copy tests.
 */
class core_backup_course_copy_testcase extends advanced_testcase {
    protected $course;  // Course id used for testing.
    protected $userid;    // User used to perform backups.
    protected $courseusers;  // Ids of users in test course.
    protected $activitynames; // Names of the created activities.

    protected function setUp() {
        global $DB, $CFG, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->enableavailability = true;
        $CFG->enablecompletion = true;

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            array('format' => 'topics', 'numsections' => 3,
                'enablecompletion' => COMPLETION_ENABLED),
            array('createsections' => true));
        $forum = $generator->create_module('forum', array(
            'course' => $course->id));
        $forum2 = $generator->create_module('forum', array(
            'course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL));

        // We need a grade, easiest is to add an assignment.
        $assignrow = $generator->create_module('assign', array(
            'course' => $course->id));
        $assign = new assign(context_module::instance($assignrow->cmid), false, false);
        $item = $assign->get_grade_item();

        // Make a test grouping as well.
        $grouping = $generator->create_grouping(array('courseid' => $course->id,
            'name' => 'Grouping!'));

        // Create some users.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        $user4 = $generator->create_user();
        $this->courseusers = array(
            $user1->id, $user2->id, $user3->id, $user4->id
        );

        // Enrol users into the course.
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'editingteacher');
        $generator->enrol_user($user3->id, $course->id, 'manager');
        $generator->enrol_user($user4->id, $course->id, 'editingteacher');
        $generator->enrol_user($user4->id, $course->id, 'manager');

        $availability = '{"op":"|","show":false,"c":[' .
            '{"type":"completion","cm":' . $forum2->cmid .',"e":1},' .
            '{"type":"grade","id":' . $item->id . ',"min":4,"max":94},' .
            '{"type":"grouping","id":' . $grouping->id . '}' .
            ']}';
        $DB->set_field('course_modules', 'availability', $availability, array(
            'id' => $forum->cmid));
        $DB->set_field('course_sections', 'availability', $availability, array(
            'course' => $course->id, 'section' => 1));

        $this->course  = $course;
        $this->userid = $USER->id; // Admin.
        $this->activitynames = array(
            $forum->name,
            $forum2->name,
            $assignrow->name
        );

        // Disable all loggers
        $CFG->backup_error_log_logger_level = backup::LOG_NONE;
        $CFG->backup_output_indented_logger_level = backup::LOG_NONE;
        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $CFG->backup_database_logger_level = backup::LOG_NONE;
        $CFG->backup_file_logger_level_extra = backup::LOG_NONE;
    }
    /*
     * Test course copy.
     */
    public function test_course_copy() {
        global $DB;

        // Mock up the form data.
        $formdata = new \stdClass;
        $formdata->courseid = $this->course->id;
        $formdata->fullname = 'copy course';
        $formdata->shortname = 'copy course short';
        $formdata->category = 1;
        $formdata->visible = 0;
        $formdata->startdate = 1582376400;
        $formdata->enddate = 1582386400;
        $formdata->idnumber = 123;
        $formdata->userdata = 1;
        $formdata->manualenrols = array(1, 3, 5);

        // Create the course copy records and associated ad-hoc task.
        $coursecopy = new \core_backup\copy\core_backup_copy($formdata);
        $copyids = $coursecopy->create_copy();

        $courseid = $this->course->id;

        // We are expecting trace output during this test.
        $this->expectOutputRegex("/$courseid/");

        // Execute adhoc task.
        $now = time();
        $task = \core\task\manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\core\\task\\asynchronous_copy_task', $task);
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        $postbackuprec = $DB->get_record('backup_controllers', array('backupid' => $copyids['backupid']));
        $postrestorerec = $DB->get_record('backup_controllers', array('backupid' => $copyids['restoreid']));

        // Check backup was completed successfully.
        $this->assertEquals(backup::STATUS_FINISHED_OK, $postbackuprec->status);
        $this->assertEquals(1.0, $postbackuprec->progress);

        // Check restore was completed successfully.
        $this->assertEquals(backup::STATUS_FINISHED_OK, $postrestorerec->status);
        $this->assertEquals(1.0, $postrestorerec->progress);

        // Check the restored course itself.
        $coursecontext = context_course::instance($postrestorerec->itemid);
        $users = get_enrolled_users($coursecontext);

        $modinfo = get_fast_modinfo($postrestorerec->itemid);
        $course = $modinfo->get_course();

        $this->assertEquals($formdata->startdate, $course->startdate);
        $this->assertEquals($formdata->enddate, $course->enddate);
        $this->assertEquals('copy course', $course->fullname);
        $this->assertEquals('copy course short',  $course->shortname);
        $this->assertEquals(0,  $course->visible);
        $this->assertEquals(123,  $course->idnumber);

        foreach ($modinfo->get_cms() as $cm) {
            $this->assertContains($cm->get_formatted_name(), $this->activitynames);
        }

        foreach ($this->courseusers as $user) {
            $this->assertEquals($user, $users[$user]->id);
        }

        $this->assertEquals(count($this->courseusers), count($users));
    }
}

