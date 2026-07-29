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

namespace aiprovider_awsbedrock;

use Aws\Result;
use GuzzleHttp\Psr7\Utils;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Tests for AWS Bedrock image description processing.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_awsbedrock\process_describe_image
 */
final class process_describe_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test an Amazon Nova multimodal request.
     */
    public function test_create_nova_request(): void {
        global $CFG;
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'amazon.nova-pro-v1:0',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'create_request');
        $request = $method->invoke(new process_describe_image($provider, $action));
        $body = json_decode($request['body']);

        $this->assertEquals('png', $body->messages[0]->content[0]->image->format);
        $this->assertNotEmpty($body->messages[0]->content[0]->image->source->bytes);
        $this->assertStringContainsString('Be concise', $body->messages[0]->content[1]->text);
    }

    /**
     * Test Anthropic image limits are checked before an upstream request.
     */
    public function test_anthropic_image_limits(): void {
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = $this->createMock(\stored_file::class);
        $image->method('get_mimetype')->willReturn('image/png');
        $image->method('get_imageinfo')->willReturn(['width' => 100, 'height' => 100]);
        $image->method('get_filesize')->willReturn(3_932_161);
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'query_ai_api');
        $result = $method->invoke(new process_describe_image($provider, $action));

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['errorcode']);
        $this->assertStringContainsString('no larger than', $result['errormessage']);
    }

    /**
     * Test successful Nova response conversion.
     */
    public function test_handle_api_success(): void {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = $this->create_provider($actionclass, [
            'model' => 'amazon.nova-pro-v1:0',
            'systeminstruction' => 'Describe the image.',
        ]);
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');
        $response = new Result([
            'body' => Utils::streamFor(json_encode([
                'output' => ['message' => ['content' => [['text' => 'A black square.']]]],
                'stopReason' => 'end_turn',
                'usage' => ['inputTokens' => 10, 'outputTokens' => 4],
                'model' => 'amazon.nova-pro-v1:0',
            ])),
            '@metadata' => ['headers' => ['x-amzn-requestid' => 'response-1']],
        ]);

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke(new process_describe_image($provider, $action), $response);

        $this->assertEquals('A black square.', $result['generatedcontent']);
        $this->assertEquals('amazon.nova-pro-v1:0', $result['model']);
    }
}
