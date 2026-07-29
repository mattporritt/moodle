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

namespace aiprovider_deepseek;

use GuzzleHttp\Psr7\Response;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Tests for DeepSeek provider image description processing.
 *
 * @package    aiprovider_deepseek
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_deepseek\process_describe_image
 */
final class process_describe_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test official text-only models return a normal provider failure.
     */
    public function test_official_model_is_rejected(): void {
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

        $method = new \ReflectionMethod(process_describe_image::class, 'query_ai_api');
        $result = $method->invoke(new process_describe_image($provider, $action));

        $this->assertFalse($result['success']);
        $this->assertEquals('unsupportedmodel', $result['error']);
    }

    /**
     * Test a custom compatible endpoint receives image content.
     */
    public function test_custom_endpoint_request(): void {
        global $CFG;
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'deepseek-vl2',
            'endpoint' => 'https://vision.example.test/chat/completions',
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

        $this->assertEquals('deepseek-vl2', $body->model);
        $this->assertStringStartsWith('data:image/png;base64,', $body->messages[1]->content[1]->image_url->url);
    }

    /**
     * Test successful compatible-endpoint response conversion.
     */
    public function test_handle_api_success(): void {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'deepseek-vl2',
            'endpoint' => 'https://vision.example.test/chat/completions',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');
        $response = new Response(200, [], json_encode([
            'id' => 'response-1',
            'choices' => [['message' => ['content' => 'A black square.'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
            'model' => 'deepseek-vl2',
        ]));

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke(new process_describe_image($provider, $action), $response);

        $this->assertEquals('A black square.', $result['generatedcontent']);
        $this->assertEquals('deepseek-vl2', $result['model']);
    }

    /**
     * Test oversized images are rejected before their content is read.
     */
    public function test_image_size_limit(): void {
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'deepseek-vl2',
            'endpoint' => 'https://vision.example.test/chat/completions',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = $this->createMock(\stored_file::class);
        $image->method('get_mimetype')->willReturn('image/png');
        $image->method('get_imageinfo')->willReturn(['width' => 100, 'height' => 100]);
        $image->method('get_filesize')->willReturn((20 * 1024 * 1024) + 1);
        $image->expects($this->never())->method('get_content');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'query_ai_api');
        $result = $method->invoke(new process_describe_image($provider, $action));

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['errorcode']);
    }

    /**
     * Test an empty compatible-endpoint response becomes a provider failure.
     */
    public function test_empty_response_is_failure(): void {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'deepseek-vl2',
            'endpoint' => 'https://vision.example.test/chat/completions',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');
        $response = new Response(200, [], json_encode(['choices' => []]));

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke(new process_describe_image($provider, $action), $response);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }
}
