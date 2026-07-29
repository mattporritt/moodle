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

namespace core_ai\aiactions\responses;

/**
 * Tests for describe image responses.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\responses\response_describe_image
 */
final class response_describe_image_test extends \advanced_testcase {
    /**
     * Test response data and reasoning removal.
     */
    public function test_response_data(): void {
        $response = new response_describe_image(success: true);
        $response->set_response_data([
            'generatedcontent' => '<think>Hidden reasoning.</think>A black square.',
            'model' => 'vision-model',
        ]);

        $this->assertTrue($response->get_success());
        $this->assertEquals('describe_image', $response->get_actionname());
        $this->assertEquals('A black square.', $response->get_response_data()['generatedcontent']);
        $this->assertEquals('vision-model', $response->get_model_used());
    }

    /**
     * Test a markdown code fence wrapping the whole response is stripped.
     */
    public function test_response_data_strips_wrapping_code_fence(): void {
        $fence = str_repeat("\x60", 3);
        $response = new response_describe_image(success: true);
        $response->set_response_data([
            'generatedcontent' => "{$fence}plaintext\nA black square.\n{$fence}",
        ]);

        $this->assertEquals('A black square.', $response->get_response_data()['generatedcontent']);
    }

    /**
     * Test a response containing an inline code sample is left unchanged.
     */
    public function test_response_data_keeps_inline_code_sample(): void {
        $inlinecode = str_repeat("\x60", 1);
        $content = "A screenshot showing the code {$inlinecode}echo 'hi';{$inlinecode} on screen.";
        $response = new response_describe_image(success: true);
        $response->set_response_data([
            'generatedcontent' => $content,
        ]);

        $this->assertEquals($content, $response->get_response_data()['generatedcontent']);
    }

    /**
     * Test invalid error responses are rejected.
     */
    public function test_invalid_error_response(): void {
        $this->expectException(\core\exception\coding_exception::class);
        new response_describe_image(success: false);
    }
}
