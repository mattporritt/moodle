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

use core_ai\aiactions\responses\response_generate_image;
use core_ai\aiactions\generate_image;

/**
 * Test generate_image action methods.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\base
 */
final class generate_image_test extends \advanced_testcase {

    /**
     * Test constructor method.
     */
    public function test_constructor(): void {
        $contextid = 1;
        $userid = 1;
        $prompttext = 'This is a test prompt';
        $aspectratio = 'square';
        $quality = 'hd';
        $numimages = 1;
        $style = 'vivid';
        $action = new generate_image(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompttext,
            quality: $quality,
            aspectratio: $aspectratio,
            numimages: $numimages,
            style: $style
        );

        $this->assertEquals($userid, $action->get_configuration('userid'));
        $this->assertEquals($prompttext, $action->get_configuration('prompttext'));
        $this->assertEquals($aspectratio, $action->get_configuration('aspectratio'));
        $this->assertEquals($quality, $action->get_configuration('quality'));
        $this->assertEquals($numimages, $action->get_configuration('numimages'));
        $this->assertEquals($style, $action->get_configuration('style'));
    }

    /**
     * Test store method.
     */
    public function test_store(): void {
        $this->resetAfterTest();
        global $DB;

        $contextid = 1;
        $userid = 1;
        $prompttext = 'This is a test prompt';
        $aspectratio = 'square';
        $quality = 'hd';
        $numimages = 1;
        $style = 'vivid';
        $action = new generate_image(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompttext,
            quality: $quality,
            aspectratio: $aspectratio,
            numimages: $numimages,
            style: $style
        );

        $body = [
            'revisedprompt' => 'This is a revised prompt',
            'sourceurl' => 'https://example.com/image.png',
            'model' => 'dall-e-3',
        ];
        $actionresponse = new response_generate_image(
            success: true,
        );
        $actionresponse->set_response_data($body);

        $storeid = $action->store($actionresponse);

        // Check the stored record.
        $record = $DB->get_record('ai_action_generate_image', ['id' => $storeid]);
        $this->assertEquals($prompttext, $record->prompt);
        $this->assertEquals($numimages, $record->numberimages);
        $this->assertEquals($quality, $record->quality);
        $this->assertEquals($aspectratio, $record->aspectratio);
        $this->assertEquals($style, $record->style);
        $this->assertEquals($body['sourceurl'], $record->sourceurl);
        $this->assertEquals($body['revisedprompt'], $record->revisedprompt);
        // No draftfile in the response (for example a provider that failed before creating one), so no
        // contenthash is available to record.
        $this->assertNull($record->contenthash);
        $this->assertNull($record->localpathnamehash);
    }

    /**
     * Test that the draft file's contenthash is recorded at generation time, ready for later
     * correlation by core_ai\hook_listener::correlate_generated_image().
     */
    public function test_store_records_draftfile_contenthash(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $action = new generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
            quality: 'hd',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );

        $user = $this->getDataGenerator()->create_user();
        $fs = get_file_storage();
        $draftfile = $fs->create_file_from_string(
            [
                'contextid' => \context_user::instance($user->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes',
        );

        $body = [
            'draftfile' => $draftfile,
            'revisedprompt' => 'This is a revised prompt',
            'sourceurl' => null,
            'model' => 'dall-e-3',
        ];
        $actionresponse = new response_generate_image(success: true);
        $actionresponse->set_response_data($body);

        $storeid = $action->store($actionresponse);

        $record = $DB->get_record('ai_action_generate_image', ['id' => $storeid]);
        $this->assertEquals($draftfile->get_contenthash(), $record->contenthash);
        $this->assertNull($record->localpathnamehash);
    }
}
