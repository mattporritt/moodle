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

namespace core_ai\aiactions;

use core_ai\aiactions\responses\response_describe_image;
use core_ai\aiactions\describe_image;
use core_h5p\file_storage;

/**
 * Test describe_image action methods.
 *
 * @package    core_ai
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\base
 */
final class describe_image_test extends \advanced_testcase {

    /**
     * Test configure method.
     */
    public function test_configure(): void {
        $this->resetAfterTest();

        $contextid = 1;
        $userid = 1;
        // Create a file to store.
        $fs = get_file_storage();
        $filerecord = new \stdClass();
        $filerecord->contextid = 1;
        $filerecord->component = 'core_ai';
        $filerecord->filearea = 'draft';
        $filerecord->itemid = 0;
        $filerecord->filepath = '/';
        $filerecord->filename = 'test.txt';
        $file = $fs->create_file_from_string($filerecord, 'This is a test file');

        $action = new describe_image(
            contextid: $contextid,
            userid: $userid,
            image: $file
        );
        $this->assertEquals($userid, $action->get_configuration('userid'));
        $this->assertEquals($file, $action->get_configuration('image'));
    }

    /**
     * Test store method.
     */
    public function test_store(): void {
        $this->resetAfterTest();
        global $CFG, $DB;

        $contextid = 1;
        $userid = 1;
        $imagepath = self::get_fixture_path('core_ai', 'black.png'); // Get the test image from the fixtures file.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => 1,
            'component' => 'core_ai',
            'filearea'  => 'testfiles',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'black.png',
        ];

        // Create the stored file from the fixture
        $image = $fs->create_file_from_pathname($filerecord, $imagepath);

        $action = new describe_image(
            contextid: $contextid,
            userid: $userid,
            image: $image
        );

        $body = [
            'id' => 'chatcmpl-123',
            'fingerprint' => 'fp_44709d6fcb',
            'generatedcontent' => 'This is the generated content',
            'finishreason' => 'stop',
            'prompttokens' => 9,
            'completiontokens' => 12,
            'model' => 'gpt-4o',
        ];
        $actionresponse = new response_describe_image(
            success: true,
        );
        $actionresponse->set_response_data($body);

        $storeid = $action->store($actionresponse);

        // Check the stored record.
        $record = $DB->get_record('ai_action_describe_image', ['id' => $storeid]);
        $this->assertEquals($body['id'], $record->responseid);
        $this->assertEquals($body['fingerprint'], $record->fingerprint);
        $this->assertEquals($body['generatedcontent'], $record->generatedcontent);
        $this->assertEquals($body['finishreason'], $record->finishreason);
        $this->assertEquals($body['prompttokens'], $record->prompttokens);
        $this->assertEquals($body['completiontokens'], $record->completiontoken);
    }
}
