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
 * Unit tests for file_storage can access methods.
 *
 * @package   core_files
 * @category  phpunit
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/filestorage/stored_file.php');

/**
 * Unit tests for file_storage can access methods.
 *
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass file_storage
 */
class core_files_can_access_testcase extends advanced_testcase {

    /**
     *
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->fs = get_file_storage();

        // Create default user.
        $this->user = $this->getDataGenerator()->create_user();
        @complete_user_login($this->user); // Hide session header errors when logging in user this way.
    }

    /**
     * Test can access file method for blogs.
     */
    public function test_can_access_file_blog() {
        global $DB;

        $filerecordpost = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'post',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'postfile.txt');
        $filepost = $this->fs->create_file_from_string($filerecordpost, 'the post test file');

        $filerecordattachment = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'attachment',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $fileattachment = $this->fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $accesspost = $this->fs->can_access_file(
            $filerecordpost['contextid'],
            $filerecordpost['component'],
            $filerecordpost['filearea'],
            $filerecordpost['itemid'],
            $filerecordpost['filepath'],
            $filerecordpost['filename']);
        $accessattachment = $this->fs->can_access_file(
            $filerecordattachment['contextid'],
            $filerecordattachment['component'],
            $filerecordattachment['filearea'],
            $filerecordattachment['itemid'],
            $filerecordattachment['filepath'],
            $filerecordattachment['filename']);

        // Post not found.
        $this->assertFalse($accesspost);
        $this->assertFalse($accessattachment);

        // Create default post.
        $post = new stdClass();
        $post->userid = $this->user->id;
        $post->content = 'test post content text';
        $post->module = 'blog';
        $post->id = $DB->insert_record('post', $post);

        $filerecordpost['itemid'] = $post->id;
        $filerecordattachment['itemid'] = $post->id;
        $filepost = $this->fs->create_file_from_string($filerecordpost, 'the post test file');
        $fileattachment = $this->fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $accesspost = $this->fs->can_access_file(
            $filerecordpost['contextid'],
            $filerecordpost['component'],
            $filerecordpost['filearea'],
            $filerecordpost['itemid'],
            $filerecordpost['filepath'],
            $filerecordpost['filename']);
        $accessattachment = $this->fs->can_access_file(
            $filerecordattachment['contextid'],
            $filerecordattachment['component'],
            $filerecordattachment['filearea'],
            $filerecordattachment['itemid'],
            $filerecordattachment['filepath'],
            $filerecordattachment['filename']);

        $this->assertTrue($accesspost);
        $this->assertTrue($accessattachment);

    }

    /**
     * Test can access file method for blogs.
     */
    public function test_can_access_blog() {
        global $DB;

        $filerecordpost = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'post',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'postfile.txt');
        $filepost = $this->fs->create_file_from_string($filerecordpost, 'the post test file');

        $filerecordattachment = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'attachment',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $fileattachment = $this->fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        // Post not found.
        $this->assertFalse($filepost->can_access());
        $this->assertFalse($fileattachment->can_access());

        // Create default post.
        $post = new stdClass();
        $post->userid = $this->user->id;
        $post->content = 'test post content text';
        $post->module = 'blog';
        $post->id = $DB->insert_record('post', $post);

        $filerecordpost['itemid'] = $post->id;
        $filerecordattachment['itemid'] = $post->id;
        $filepost = $this->fs->create_file_from_string($filerecordpost, 'the post test file');
        $fileattachment = $this->fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $this->assertTrue($filepost->can_access());
        $this->assertTrue($fileattachment->can_access());

    }

    /**
     * Test can access file method for grades.
     */
    public function test_can_access_file_grade() {
        global $DB;

        // Outcome and scale files.
        $filerecordoutcome = array(
            'contextid' =>  1,
            'component' => 'grade',
            'filearea' => 'outcome',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'outcomefile.txt');
        $fileoutcome = $this->fs->create_file_from_string($filerecordoutcome, 'the outcome test file');

        $filerecordscale = array(
            'contextid' =>  1,
            'component' => 'grade',
            'filearea' => 'scale',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $filescale = $this->fs->create_file_from_string($filerecordscale, 'the scale test file');

        $accessoutcome = $this->fs->can_access_file(
            $filerecordoutcome['contextid'],
            $filerecordoutcome['component'],
            $filerecordoutcome['filearea'],
            $filerecordoutcome['itemid'],
            $filerecordoutcome['filepath'],
            $filerecordoutcome['filename']);
        $accessscale = $this->fs->can_access_file(
            $filerecordscale['contextid'],
            $filerecordscale['component'],
            $filerecordscale['filearea'],
            $filerecordscale['itemid'],
            $filerecordscale['filepath'],
            $filerecordscale['filename']);

        $this->assertTrue($accessoutcome);
        $this->assertTrue($accessscale);

        // Feedback and history feedback files.

        // Create the data we need for the tests.
        $course1 = $this->getDataGenerator()->create_course();
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $assign1context = context_module::instance($assign1->cmid);

        // Enrol user in course;
        $this->getDataGenerator()->enrol_user($this->user->id, $course1->id);

        $gradeitem = new grade_item($this->getDataGenerator()->create_grade_item(
            [
                'courseid' => $course1->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assign1->id
            ]
            ), false);

        // Create grade entry.
        $grades['feedback'] = 'Nice feedback!';
        $grades['feedbackformat'] = FORMAT_MOODLE;
        $grades['feedbackfiles'] = [
            'contextid' => $assign1context->id,
            'component' => 'grade',
            'filearea' => 'feedback',
            'itemid' => $gradeitem->id
        ];

        $grades['userid'] = $this->user->id;
        grade_update('mod/assign', $gradeitem->courseid, $gradeitem->itemtype, $gradeitem->itemmodule, $gradeitem->iteminstance,
            $gradeitem->itemnumber, $grades);
        $grades = $DB->get_records('grade_grades');
        $gradeid = $DB->get_field('grade_grades', 'id', array('itemid' => $gradeitem->id));


        // Create file.
        $filerecordfeedback = array(
            'contextid' => $assign1context->id,
            'component' => 'grade',
            'filearea' => 'feedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'feedback1.txt'
        );
        $filefeedback = $this->fs->create_file_from_string($filerecordfeedback, 'feedback file');

        // Test access.
        $accessfeedback = $this->fs->can_access_file(
            $filerecordfeedback['contextid'],
            $filerecordfeedback['component'],
            $filerecordfeedback['filearea'],
            $filerecordfeedback['itemid'],
            $filerecordfeedback['filepath'],
            $filerecordfeedback['filename']);

        $this->assertTrue($accessfeedback);
    }

    /**
     * Test can access file method for grades.
     */
    public function test_can_access_grade() {
        global $DB;

        // Outcome and scale files.
        $filerecordoutcome = array(
            'contextid' =>  1,
            'component' => 'grade',
            'filearea' => 'outcome',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'outcomefile.txt');
        $fileoutcome = $this->fs->create_file_from_string($filerecordoutcome, 'the outcome test file');

        $filerecordscale = array(
            'contextid' =>  1,
            'component' => 'grade',
            'filearea' => 'scale',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $filescale = $this->fs->create_file_from_string($filerecordscale, 'the scale test file');

        $this->assertTrue($fileoutcome->can_access());
        $this->assertTrue($filescale->can_access());

        // Feedback and history feedback files.

        // Create the data we need for the tests.
        $course1 = $this->getDataGenerator()->create_course();
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $assign1context = context_module::instance($assign1->cmid);

        // Enrol user in course;
        $this->getDataGenerator()->enrol_user($this->user->id, $course1->id);

        $gradeitem = new grade_item($this->getDataGenerator()->create_grade_item(
            [
                'courseid' => $course1->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assign1->id
            ]
            ), false);

        // Create grade entry.
        $grades['feedback'] = 'Nice feedback!';
        $grades['feedbackformat'] = FORMAT_MOODLE;
        $grades['feedbackfiles'] = [
            'contextid' => $assign1context->id,
            'component' => 'grade',
            'filearea' => 'feedback',
            'itemid' => $gradeitem->id
        ];

        $grades['userid'] = $this->user->id;
        grade_update('mod/assign', $gradeitem->courseid, $gradeitem->itemtype, $gradeitem->itemmodule, $gradeitem->iteminstance,
            $gradeitem->itemnumber, $grades);
        $grades = $DB->get_records('grade_grades');
        $gradeid = $DB->get_field('grade_grades', 'id', array('itemid' => $gradeitem->id));


        // Create file.
        $filerecordfeedback = array(
            'contextid' => $assign1context->id,
            'component' => 'grade',
            'filearea' => 'feedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'feedback1.txt'
        );
        $filefeedback = $this->fs->create_file_from_string($filerecordfeedback, 'feedback file');

        // Test access.
        $this->assertTrue($filefeedback->can_access());

    }
}
