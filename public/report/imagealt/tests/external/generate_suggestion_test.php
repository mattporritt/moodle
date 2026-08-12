<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace report_imagealt\external;

use report_imagealt\ai_availability_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../ai_availability_test_trait.php');

use context_course;
use core_ai\aiactions\responses\response_describe_image;
use core_external\external_api;
use report_imagealt\local\manager;

/**
 * Tests for the generate_suggestion external function.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_suggestion::class)]
final class generate_suggestion_test extends \advanced_testcase {
    use ai_availability_test_trait;

    /** One-pixel PNG image. */
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
            'generatedcontent' => $success ? 'A one-pixel test image.' : '',
            'finishreason' => 'stop',
        ]);

        $aimanager = $this->stub_ai_availability(true);
        $aimanager->method('process_action')->willReturn($response);
    }

    /**
     * Create a course occurrence with a resolvable image file.
     *
     * @return \stdClass Occurrence record.
     */
    private function create_occurrence(): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'summary',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lake.png',
        ], base64_decode(self::IMAGE));
        $DB->set_field('course', 'summary', '<img src="@@PLUGINFILE@@/lake.png">', ['id' => $course->id]);

        (new manager())->scan_context($context);

        return $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
    }

    /**
     * A generated suggestion is stored and returned.
     */
    public function test_execute_generates_suggestion(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $occurrence = $this->create_occurrence();
        \core_ai\manager::user_policy_accepted((int) $USER->id, context_course::instance($occurrence->courseid)->id);
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertFalse($result['error']);
        $this->assertSame('ready', $result['data']['status']);
        $this->assertStringContainsString('A one-pixel test image.', $result['data']['suggestiontext']);
        $this->assertSame(
            1,
            $DB->count_records('report_imagealt_suggestion', ['occurrenceid' => $occurrence->id, 'status' => 'ready']),
        );
    }

    /**
     * A user without the view capability cannot request a suggestion.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $occurrence = $this->create_occurrence();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertTrue($result['error']);
    }

    /**
     * A user who can view the report but cannot edit the underlying content is rejected.
     */
    public function test_execute_requires_edit_access(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $occurrence = $this->create_occurrence();

        $roleid = create_role('Image report viewer', 'imagereportviewer', '');
        $context = context_course::instance($occurrence->courseid);
        assign_capability('report/imagealt:view', CAP_ALLOW, $roleid, $context->id);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $occurrence->courseid, $roleid);
        $this->setUser($user);

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('cannotedit', $result['exception']->errorcode);
    }

    /**
     * A decorative occurrence cannot be spent on a suggestion, even by calling this endpoint directly rather than
     * through the report, which never offers the action on one.
     */
    public function test_execute_refuses_a_decorative_occurrence(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="@@PLUGINFILE@@/lake.png" alt="" role="presentation">',
        ]);
        $context = context_course::instance($course->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'summary',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lake.png',
        ], base64_decode(self::IMAGE));
        (new manager())->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertSame('decorative', $occurrence->status);
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertTrue($result['error']);
        $this->assertSame('error:imagenotavailable', $result['exception']->errorcode);
        $this->assertSame(0, $DB->count_records('report_imagealt_suggestion'));
    }

    /**
     * A broken occurrence, whose reference resolves to no file this site owns, is not AI eligible and cannot be
     * spent on a suggestion either.
     */
    public function test_execute_refuses_a_broken_occurrence(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="@@PLUGINFILE@@/gone.png">',
            'summaryformat' => FORMAT_HTML,
        ]);
        $context = context_course::instance($course->id);
        (new manager())->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertSame('broken', $occurrence->status);
        $this->assertSame(0, (int) $occurrence->aieligible);
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
        $this->configure_manager();

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertTrue($result['error']);
        $this->assertSame('error:imagenotavailable', $result['exception']->errorcode);
        $this->assertSame(0, $DB->count_records('report_imagealt_suggestion'));
    }

    /**
     * On a site with no provider for image descriptions the request is refused outright, and leaves nothing behind.
     *
     * Letting generation fail instead would record a failed suggestion for every attempt, each one becoming the
     * image's latest suggestion and reporting itself in the report as something having gone wrong.
     */
    public function test_execute_is_refused_without_a_provider(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $occurrence = $this->create_occurrence();
        \core_ai\manager::user_policy_accepted((int) get_admin()->id, context_course::instance($occurrence->courseid)->id);

        $this->stub_ai_availability(false);

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_generate_suggestion', [
            'occurrenceid' => $occurrence->id,
        ]);

        $this->assertTrue($result['error']);
        $this->assertSame('error:aiunavailable', $result['exception']->errorcode);
        $this->assertSame(0, $DB->count_records('report_imagealt_suggestion'));
    }
}
