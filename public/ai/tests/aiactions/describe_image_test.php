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

/**
 * Tests for the describe image action.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\describe_image
 */
final class describe_image_test extends \advanced_testcase {
    /**
     * Create a stored PNG fixture.
     *
     * @return \stored_file
     */
    private function create_image(): \stored_file {
        return get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'core_ai',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'black.png',
        ], __DIR__ . '/../fixtures/black.png');
    }

    /**
     * Test configuration and prompt construction.
     */
    public function test_configuration(): void {
        $this->resetAfterTest();
        $action = new describe_image(
            contextid: \context_system::instance()->id,
            userid: 7,
            image: $this->create_image(),
            purpose: 'A concise accessibility description',
            context: 'An example shown in a geometry lesson',
            language: 'English',
        );

        $this->assertEquals(7, $action->get_configuration('userid'));
        $this->assertStringContainsString('concise accessibility', $action->get_prompt());
        $this->assertStringContainsString('geometry lesson', $action->get_prompt());
        $this->assertStringContainsString('English', $action->get_prompt());
    }

    /**
     * Test unsupported MIME types are rejected at the action boundary.
     */
    public function test_unsupported_mimetype(): void {
        $this->resetAfterTest();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'core_ai',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'image.svg',
        ], '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->expectException(\core\exception\coding_exception::class);
        new describe_image(1, 7, $file, 'Describe it', 'A test', 'English');
    }

    /**
     * Test files with a supported MIME type must contain a valid image.
     */
    public function test_invalid_image_content(): void {
        $this->resetAfterTest();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'core_ai',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'invalid.png',
        ], 'This is not an image.');

        $this->expectException(\core\exception\coding_exception::class);
        $this->expectExceptionMessage('The supplied file is not a valid image.');
        new describe_image(1, 7, $file, 'Describe it', 'A test', 'English');
    }

    /**
     * Test deterministic provider limits use the standard AI error shape.
     */
    public function test_provider_limits(): void {
        $image = $this->createMock(\stored_file::class);
        $image->method('get_mimetype')->willReturn('image/png');
        $image->method('get_imageinfo')->willReturn([
            'width' => 8_001,
            'height' => 100,
        ]);
        $image->method('get_filesize')->willReturn(21 * 1024 * 1024);
        $action = new describe_image(1, 7, $image, 'Describe it', 'A test', 'English');

        $sizeerror = $action->validate_provider_limits(maxfilesize: 20 * 1024 * 1024);
        $this->assertEquals(400, $sizeerror['errorcode']);
        $this->assertStringContainsString('no larger than', $sizeerror['errormessage']);

        $dimensionerror = $action->validate_provider_limits(maxdimension: 8_000);
        $this->assertEquals(400, $dimensionerror['errorcode']);
        $this->assertStringContainsString('8000 pixels', $dimensionerror['errormessage']);

        $this->assertTrue($action->validate_provider_limits());
    }

    /**
     * Test action-specific result storage.
     */
    public function test_store(): void {
        global $DB;
        $this->resetAfterTest();
        $action = new describe_image(1, 7, $this->create_image(), 'Detailed description', 'Course context', 'French');
        $response = new response_describe_image(success: true);
        $response->set_response_data([
            'id' => 'response-1',
            'fingerprint' => 'fingerprint-1',
            'generatedcontent' => 'Une image noire.',
            'finishreason' => 'stop',
            'prompttokens' => 10,
            'completiontokens' => 4,
            'model' => 'vision-model',
        ]);

        $record = $DB->get_record('ai_action_describe_image', ['id' => $action->store($response)], '*', MUST_EXIST);
        $this->assertEquals('black.png', $record->filename);
        $this->assertEquals('image/png', $record->mimetype);
        $this->assertEquals('Detailed description', $record->purpose);
        $this->assertEquals('Course context', $record->context);
        $this->assertEquals('French', $record->language);
        $this->assertEquals('Une image noire.', $record->generatedcontent);
    }
}
