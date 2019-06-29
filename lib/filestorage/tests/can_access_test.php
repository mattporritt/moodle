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

        $accesspost = $this->fs->can_access_file($filepost);
        $accessattachment = $this->fs->can_access_file($fileattachment);

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

        $accesspost = $this->fs->can_access_file($filepost);
        $accessattachment = $this->fs->can_access_file($fileattachment);

        $this->assertTrue($accesspost);
        $this->assertTrue($accessattachment);

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

        $accessoutcome = $this->fs->can_access_file($fileoutcome);
        $accessscale = $this->fs->can_access_file($filescale);

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
        $accessfeedback = $this->fs->can_access_file($filefeedback);

        $this->assertTrue($accessfeedback);
    }

    /**
     * Test can access file method for grades.
     */
    public function test_can_access_file_tag() {
        $tag = $this->getDataGenerator()->create_tag();

        $filerecorddescription = array(
            'contextid' =>  10,
            'component' => 'tag',
            'itemid' => $tag->id,
            'filearea' => 'description',
            'filepath' => '/',
            'filename' => 'descriptionfile.txt');
        $filedescription = $this->fs->create_file_from_string($filerecorddescription, 'the description test file');

        $accessdescription = $this->fs->can_access_file($filedescription);

        // Fail due to wrong context.
        $this->assertFalse($accessdescription);

        $filerecorddescription['contextid'] = 1;
        $filedescription = $this->fs->create_file_from_string($filerecorddescription, 'the description test file');

        $accessdescription = $this->fs->can_access_file($filedescription);

        $this->assertTrue($accessdescription);

    }

    /**
     * Test can access file method for grades.
     */
    public function test_can_access_file_badges() {
        global $DB;
        $now = time();
        $user = $this->getDataGenerator()->create_user();

        // Mock up a badge.
        $badgerecord = new stdClass();
        $badgerecord->id = null;
        $badgerecord->name = 'Test badge';
        $badgerecord->description = 'Testing badges';
        $badgerecord->timecreated = $now - 12;
        $badgerecord->timemodified = $now - 12;
        $badgerecord->usercreated = $user->id;
        $badgerecord->usermodified = $user->id;
        $badgerecord->issuername = 'Test issuer';
        $badgerecord->issuerurl = 'http://issuer-url.domain.co.nz';
        $badgerecord->issuercontact = 'issuer@example.com';
        $badgerecord->expiredate = null;
        $badgerecord->expireperiod = null;
        $badgerecord->type = 1;
        $badgerecord->courseid = null;
        $badgerecord->messagesubject = 'Test message subject for badge';
        $badgerecord->message = 'Test message body for badge';
        $badgerecord->attachment = 1;
        $badgerecord->notification = 0;
        $badgerecord->status = 0;
        $badgerecord->version = 'Version';
        $badgerecord->language = 'en';
        $badgerecord->imagecaption = 'Image caption';
        $badgerecord->imageauthorname = 'Image authors name';
        $badgerecord->imageauthoremail = 'author@example.com';
        $badgerecord->imageauthorname = 'Image authors name';

        $badgeid = $DB->insert_record('badge', $badgerecord, true);
        $badge = new \core_badges\badge($badgeid);

        // based on badges/tests/badgeslib_test.php
    }
}
