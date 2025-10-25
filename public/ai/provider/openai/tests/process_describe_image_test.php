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

namespace aiprovider_openai;

use aiprovider_openai\test\testcase_helper_trait;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;

/**
 * Test process_describe_image OpenAI provider methods.
 *
 * @package    aiprovider_openai
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_openai\provider
 * @covers     \aiprovider_openai\process_describe_image
 * @covers     \aiprovider_openai\abstract_processor
 */
final class process_describe_image_test extends \advanced_testcase {

    use testcase_helper_trait;

    /** @var string A successful response in JSON format. */
    protected string $responsebodyjson;

    /** @var \core_ai\manager */
    private $manager;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->responsebodyjson = json_encode([
            'id' => 'testid',
            'system_fingerprint' => 'testfingerprint',
            'choices' => [[
                'message' => (object)['content' => 'A description of the image.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
            ],
            'model' => 'gpt-4o',
        ]);
        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\describe_image::class,
            actionconfig: [
                'model' => 'gpt-4o',
                'systeminstruction' => 'Describe the image in detail.',
            ],
        );
        $this->create_action();
    }

    /**
     * Create the action object.
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $filepath = self::get_fixture_path('aiprovider_openai', 'test.jpg');
        $fs = get_file_storage();
        $context = \context_system::instance();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'test',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.jpg',
        ];
        $image = $fs->create_file_from_pathname($filerecord, $filepath);
        $this->action = new \core_ai\aiactions\describe_image(
            contextid: 1,
            userid: $userid,
            image: $image,
        );
    }

    /**
     * Test stored_file_to_data_uri
     */
    public function test_stored_file_to_data_uri(): void {
        $processor = new process_describe_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'stored_file_to_data_uri');
        $image = (new \ReflectionProperty($this->action, 'image'))->getValue($this->action);

        $datauri = $method->invoke($processor, $image);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $datauri);
        $this->assertStringContainsString(base64_encode("\xFF\xD8\xFF"), $datauri); // JPEG magic bytes.
    }

    /**
     * Test create_request_object
     */
    public function test_create_request_object(): void {
        $processor = new process_describe_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = (object) $method->invoke($processor, 1);


        $body = json_decode($request->getBody()->getContents());
        $this->assertEquals('gpt-4o', $body->model);
        $this->assertEquals(1, $body->user);
    }

    public function test_handle_api_success(): void {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->responsebodyjson,
        );
        $processor = new process_describe_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $result = $method->invoke($processor, $response);
        $this->assertTrue($result['success']);
        $this->assertEquals('testid', $result['id']);
        $this->assertEquals('testfingerprint', $result['fingerprint']);
        $this->assertEquals('A description of the image.', $result['generatedcontent']);
        $this->assertEquals('stop', $result['finishreason']);
        $this->assertEquals(10, $result['prompttokens']);
        $this->assertEquals(20, $result['completiontokens']);
        $this->assertEquals('gpt-4o', $result['model']);
    }
}
