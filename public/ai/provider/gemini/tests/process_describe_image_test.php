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

namespace aiprovider_gemini;

use GuzzleHttp\Psr7\Response;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Tests for Gemini image description processing.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_gemini\process_describe_image
 */
final class process_describe_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test the inline image request conversion.
     */
    public function test_create_request_object(): void {
        global $CFG;
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'create_request_object');
        $request = $method->invoke(new process_describe_image($provider, $action), 'user-hash');
        $body = json_decode($request->getBody()->getContents());

        $this->assertStringContainsString('Be concise', $body->contents[0]->parts[0]->text);
        $this->assertEquals('image/png', $body->contents[0]->parts[1]->inline_data->mime_type);
        $this->assertNotEmpty($body->contents[0]->parts[1]->inline_data->data);
    }

    /**
     * Test successful response conversion.
     */
    public function test_handle_api_success(): void {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, ['systeminstruction' => 'Describe the image.']);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');
        $response = new Response(200, [], json_encode([
            'responseId' => 'response-1',
            'candidates' => [[
                'content' => ['parts' => [['text' => 'A black square.']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 4],
            'modelVersion' => 'gemini-2.5-flash',
        ]));

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke(new process_describe_image($provider, $action), $response);

        $this->assertEquals('A black square.', $result['generatedcontent']);
        $this->assertEquals('gemini-2.5-flash', $result['model']);
    }

    /**
     * Test blocked or empty successful HTTP responses become provider failures.
     *
     * @param array $responsebody Response body to test.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_response_provider')]
    public function test_invalid_response_is_failure(array $responsebody): void {
        $processor = $this->create_processor();
        $response = new Response(200, [], json_encode($responsebody));

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke($processor, $response);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }

    /**
     * Provide blocked or empty successful HTTP responses.
     *
     * @return array
     */
    public static function invalid_response_provider(): array {
        return [
            'safety block' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
            'missing candidates' => [[]],
            'empty content' => [['candidates' => [[
                'content' => ['parts' => [['text' => '   ']]],
                'finishReason' => 'STOP',
            ]]]],
        ];
    }

    /**
     * Test the inline request limit accounts for base64 expansion and prompt overhead.
     */
    public function test_inline_request_size_limit(): void {
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, ['systeminstruction' => 'Describe the image.']);
        $image = $this->createMock(\stored_file::class);
        $image->method('get_mimetype')->willReturn('image/png');
        $image->method('get_imageinfo')->willReturn(['width' => 100, 'height' => 100]);
        $image->method('get_filesize')->willReturn(16 * 1024 * 1024);
        $image->expects($this->never())->method('get_content');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'query_ai_api');
        $result = $method->invoke(new process_describe_image($provider, $action));

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['errorcode']);
    }

    /**
     * Create an image-description processor for response tests.
     *
     * @return process_describe_image
     */
    private function create_processor(): process_describe_image {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, ['systeminstruction' => 'Describe the image.']);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        return new process_describe_image($provider, $action);
    }
}
