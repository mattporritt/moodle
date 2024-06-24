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

use core_ai\actions\action_response;

/**
 * Test base action methods.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\actions\action_response
 */
class action_response_test extends \advanced_testcase {

    /**
     * Test get_basename.
     */
    public function test_get_success(): void {
        $body = [
            'revised_prompt' => 'This is a revised prompt',
            'url' => 'https://example.com/image.png',
        ];
        $actionresponse = new action_response(
            success: true,
            actionname: 'generate_image',
            body: $body
        );

        $this->assertTrue($actionresponse->get_success());
        $this->assertEquals('generate_image', $actionresponse->get_actionname());
    }

    /**
     * Test constructor with no body.
     */
    public function test_construct_no_body(): void {
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Body must exist in a successful response.');
        new action_response(
            success: true,
            actionname: 'generate_image'
        );
    }

    /**
     * Test constructor with error.
     */
    public function test_construct_error(): void {
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Error code and message must exist in an error response.');
        new action_response(
                success: false,
                actionname: 'generate_image'
        );
    }
}
