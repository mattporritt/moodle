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

namespace aiplacement_editor\external;

use core_ai\aiactions\responses\response_describe_image;

/**
 * Tests for the describe image external function.
 *
 * @package    aiplacement_editor
 * @covers     \aiplacement_editor\external\describe_image
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class describe_image_test extends \advanced_testcase {
    /** A valid 1x1 transparent PNG. */
    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * Configure an available action and manager response.
     *
     * @param bool $success Whether the response succeeds.
     */
    private function configure_manager(bool $success = true): void {
        $response = new response_describe_image(
            success: $success,
            errorcode: $success ? 0 : 500,
            error: $success ? '' : 'Generation failed',
            errormessage: $success ? '' : 'Try again later',
        );
        $response->set_response_data([
            'generatedcontent' => $success ? 'A small transparent image.' : '',
            'finishreason' => 'stop',
        ]);

        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('is_action_available')->willReturn(true);
        $manager->method('is_action_enabled')->willReturn(true);
        $manager->method('is_action_enabled_in_context')->willReturn(true);
        $manager->method('process_action')->willReturn($response);
        \core\di::set(\core_ai\manager::class, fn() => $manager);
    }

    /**
     * The action is unavailable with the default placement configuration.
     */
    public function test_execute_action_unavailable_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \core\context\system::instance();

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function('aiplacement_editor_describe_image', [
            'contextid' => $context->id,
            'imagedata' => self::IMAGE,
            'mimetype' => 'image/png',
            'descriptivecontext' => '',
        ]);

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Text editor placement is not available', $result['exception']->message);
    }

    /**
     * The declared MIME type must match the decoded image.
     */
    public function test_execute_rejects_mismatched_mimetype(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'aiplacement_editor');
        set_config('describe_image', 1, 'aiplacement_editor');
        $context = \core\context\system::instance();
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function('aiplacement_editor_describe_image', [
            'contextid' => $context->id,
            'imagedata' => self::IMAGE,
            'mimetype' => 'image/jpeg',
            'descriptivecontext' => '',
        ]);

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid parameter value', $result['exception']->message);
    }

    /**
     * The endpoint creates a stored file for the action and deletes it afterwards.
     */
    public function test_execute_success_and_temporary_file_cleanup(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'aiplacement_editor');
        set_config('describe_image', 1, 'aiplacement_editor');
        $context = \core\context\system::instance();
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function('aiplacement_editor_describe_image', [
            'contextid' => $context->id,
            'imagedata' => self::IMAGE,
            'mimetype' => 'image/png',
            'descriptivecontext' => 'A lesson about transparent images.',
        ]);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame('A small transparent image.', $result['data']['generatedcontent']);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'aiplacement_editor',
            'describe_image',
            $USER->id,
            includedirs: false,
        );
        $this->assertEmpty($files);
    }

    /**
     * A request is rejected until the user accepts the AI policy.
     */
    public function test_execute_requires_policy_acceptance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'aiplacement_editor');
        set_config('describe_image', 1, 'aiplacement_editor');
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function('aiplacement_editor_describe_image', [
            'contextid' => \core\context\system::instance()->id,
            'imagedata' => self::IMAGE,
            'mimetype' => 'image/png',
            'descriptivecontext' => '',
        ]);

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('accept the AI usage policy', $result['exception']->message);
    }
}
