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
     * Test can access file method for blogs.
     */
    public function test_can_access_file_blog() {
        $this->resetAfterTest();
        global $DB;

        $fs = get_file_storage();

        $filerecordpost = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'post',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'postfile.txt');
        $filepost = $fs->create_file_from_string($filerecordpost, 'the post test file');

        $filerecordattachment = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'attachment',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $fileattachment = $fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $accesspost = $fs->can_access_file(
            $filerecordpost['contextid'],
            $filerecordpost['component'],
            $filerecordpost['filearea'],
            $filerecordpost['itemid'],
            $filerecordpost['filepath'],
            $filerecordpost['filename']);
        $accessattachment = $fs->can_access_file(
            $filerecordattachment['contextid'],
            $filerecordattachment['component'],
            $filerecordattachment['filearea'],
            $filerecordattachment['itemid'],
            $filerecordattachment['filepath'],
            $filerecordattachment['filename']);

        // Post not found.
        $this->assertFalse($accesspost);
        $this->assertFalse($accessattachment);

        // Create default user.
        $user = $this->getDataGenerator()->create_user();
        @complete_user_login($user); // Hide session header errors when logging in user this way.

        // Create default post.
        $post = new stdClass();
        $post->userid = $user->id;
        $post->content = 'test post content text';
        $post->module = 'blog';
        $post->id = $DB->insert_record('post', $post);


        $filerecordpost['itemid'] = $post->id;
        $filerecordattachment['itemid'] = $post->id;
        $filepost = $fs->create_file_from_string($filerecordpost, 'the post test file');
        $fileattachment = $fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $accesspost = $fs->can_access_file(
            $filerecordpost['contextid'],
            $filerecordpost['component'],
            $filerecordpost['filearea'],
            $filerecordpost['itemid'],
            $filerecordpost['filepath'],
            $filerecordpost['filename']);
        $accessattachment = $fs->can_access_file(
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
        $this->resetAfterTest();

        $fs = get_file_storage();

        $filerecordpost = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'post',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'postfile.txt');
        $filepost = $fs->create_file_from_string($filerecordpost, 'the post test file');

        $filerecordattachment = array(
            'contextid' =>  1,
            'component' => 'blog',
            'filearea' => 'attachment',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'attatchmentfile.txt');
        $fileattachment = $fs->create_file_from_string($filerecordattachment, 'the attachment test file');

        $filepost->can_access();

    }
}
