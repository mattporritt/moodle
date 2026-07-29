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

namespace aiprovider_azureai;

use GuzzleHttp\Psr7\Response;

/**
 * Tests for Azure AI image description processing.
 *
 * @package    aiprovider_azureai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_azureai\process_describe_image
 */
final class process_describe_image_test extends \advanced_testcase {
    /**
     * Test the multimodal request conversion.
     */
    public function test_create_request_object(): void {
        global $CFG;
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: ['apikey' => 'key', 'endpoint' => 'https://example.test'],
            actionconfig: [
                $actionclass => [
                    'settings' => [
                        'deployment' => 'vision-deployment',
                        'apiversion' => '2024-06-01',
                        'systeminstruction' => 'Describe the image.',
                    ],
                ],
            ],
        );
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'create_request_object');
        $request = $method->invoke(new process_describe_image($provider, $action), 'user-hash');
        $body = json_decode($request->getBody()->getContents());

        $this->assertStringContainsString('Be concise', $body->messages[1]->content[0]->text);
        $this->assertStringStartsWith('data:image/png;base64,', $body->messages[1]->content[1]->image_url->url);
    }

    /**
     * Test Azure image limits are checked before an upstream request.
     */
    public function test_image_size_limit(): void {
        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: ['apikey' => 'key', 'endpoint' => 'https://example.test'],
            actionconfig: [
                $actionclass => [
                    'settings' => [
                        'deployment' => 'vision-deployment',
                        'apiversion' => '2024-06-01',
                        'systeminstruction' => 'Describe the image.',
                    ],
                ],
            ],
        );
        $image = $this->createMock(\stored_file::class);
        $image->method('get_mimetype')->willReturn('image/png');
        $image->method('get_imageinfo')->willReturn(['width' => 100, 'height' => 100]);
        $image->method('get_filesize')->willReturn((20 * 1024 * 1024) + 1);
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        $method = new \ReflectionMethod(process_describe_image::class, 'query_ai_api');
        $result = $method->invoke(new process_describe_image($provider, $action));

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['errorcode']);
        $this->assertStringContainsString('no larger than', $result['errormessage']);
    }

    /**
     * Test successful response conversion.
     */
    public function test_handle_api_success(): void {
        global $CFG;

        $this->resetAfterTest();
        $actionclass = \core_ai\aiactions\describe_image::class;
        $provider = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: ['apikey' => 'key', 'endpoint' => 'https://example.test'],
            actionconfig: [$actionclass => ['settings' => [
                'deployment' => 'vision-deployment',
                'apiversion' => '2024-06-01',
                'systeminstruction' => 'Describe the image.',
            ]]],
        );
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');
        $response = new Response(200, [], json_encode([
            'id' => 'response-1',
            'choices' => [['message' => ['content' => 'A black square.'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
            'model' => 'gpt-4o',
        ]));

        $method = new \ReflectionMethod(process_describe_image::class, 'handle_api_success');
        $result = $method->invoke(new process_describe_image($provider, $action), $response);

        $this->assertEquals('A black square.', $result['generatedcontent']);
        $this->assertEquals('gpt-4o', $result['model']);
    }

    /**
     * Test unusable successful HTTP responses become provider failures.
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
     * Provide unusable successful HTTP responses.
     *
     * @return array
     */
    public static function invalid_response_provider(): array {
        return [
            'refusal' => [[
                'choices' => [['message' => ['content' => null, 'refusal' => 'Request refused.']]],
            ]],
            'missing choices' => [[]],
            'empty content' => [['choices' => [['message' => ['content' => '   ']]]]],
        ];
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
        $provider = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: ['apikey' => 'key', 'endpoint' => 'https://example.test'],
            actionconfig: [$actionclass => ['settings' => [
                'deployment' => 'vision-deployment',
                'apiversion' => '2024-06-01',
                'systeminstruction' => 'Describe the image.',
            ]]],
        );
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => 1, 'component' => 'core_ai', 'filearea' => 'unittest',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'black.png',
        ], $CFG->dirroot . '/ai/tests/fixtures/black.png');
        $action = new $actionclass(1, 2, $image, 'Be concise', 'A colour sample', 'English');

        return new process_describe_image($provider, $action);
    }
}
