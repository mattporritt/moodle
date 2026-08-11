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

namespace report_imagealt\task;

use report_imagealt\ai_availability_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../ai_availability_test_trait.php');

use context_course;
use core_ai\aiactions\responses\response_describe_image;
use report_imagealt\local\batch_manager;
use report_imagealt\local\manager;
use report_imagealt\local\task\generate_suggestions;

/**
 * Tests for bounded bulk suggestion processing.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_suggestions::class)]
final class generate_suggestions_test extends \advanced_testcase {
    use ai_availability_test_trait;

    /** One-pixel PNG image. */
    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * No provider or policy records a clear failed outcome without publishing content.
     */
    public function test_execute_without_configuration(): void {
        global $DB, $USER;

        [$batch, $course] = $this->create_batch();
        $task = new generate_suggestions();
        $task->set_custom_data(['batchid' => $batch->id]);
        $task->execute();

        $suggestion = $DB->get_record('report_imagealt_suggestion', ['batchid' => $batch->id], '*', MUST_EXIST);
        $this->assertSame('failed', $suggestion->status);
        $this->assertSame('<img src="@@PLUGINFILE@@/lake.png">', $DB->get_field('course', 'summary', ['id' => $course->id]));
        $this->assertSame((int) $USER->id, (int) $suggestion->userid);
    }

    /**
     * A configured action stores a ready, unpublished suggestion.
     */
    public function test_execute_with_configured_action(): void {
        global $DB, $USER;

        [$batch, $course, $context] = $this->create_batch();
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
        $response = new response_describe_image(success: true);
        $response->set_response_data(['generatedcontent' => 'A calm lake below mountains.', 'finishreason' => 'stop']);
        $aimanager = $this->stub_ai_availability(true);
        $aimanager->method('process_action')->willReturn($response);

        $task = new generate_suggestions();
        $task->set_custom_data(['batchid' => $batch->id]);
        $task->execute();

        $suggestion = $DB->get_record('report_imagealt_suggestion', ['batchid' => $batch->id], '*', MUST_EXIST);
        $this->assertSame('ready', $suggestion->status);
        $this->assertSame(
            'A calm lake below mountains. - ' . get_string('contentwatermark', 'core_ai'),
            $suggestion->suggestion,
        );
        $this->assertSame('<img src="@@PLUGINFILE@@/lake.png">', $DB->get_field('course', 'summary', ['id' => $course->id]));
        $this->assertSame('complete', $DB->get_field('report_imagealt_batch', 'status', ['id' => $batch->id]));
    }

    /**
     * One task processes at most ten provider requests and leaves the remainder queued.
     */
    public function test_execute_is_bounded_to_ten_items(): void {
        global $DB;

        [$batch] = $this->create_batch();
        $occurrence = $DB->get_record('report_imagealt_occurrence', [], '*', MUST_EXIST);
        $suggestion = $DB->get_record('report_imagealt_suggestion', ['batchid' => $batch->id], '*', MUST_EXIST);
        for ($index = 1; $index <= 10; $index++) {
            $copy = clone $occurrence;
            unset($copy->id);
            $copy->occurrencekey = hash('sha256', 'bounded-' . $index);
            $copy->id = $DB->insert_record('report_imagealt_occurrence', $copy);

            $suggestioncopy = clone $suggestion;
            unset($suggestioncopy->id);
            $suggestioncopy->occurrenceid = $copy->id;
            $DB->insert_record('report_imagealt_suggestion', $suggestioncopy);
        }
        $DB->set_field('report_imagealt_batch', 'total', 11, ['id' => $batch->id]);

        $task = new generate_suggestions();
        $task->set_custom_data(['batchid' => $batch->id]);
        $task->execute();

        $this->assertSame(10, $DB->count_records('report_imagealt_suggestion', [
            'batchid' => $batch->id,
            'status' => 'failed',
        ]));
        $this->assertSame(1, $DB->count_records('report_imagealt_suggestion', [
            'batchid' => $batch->id,
            'status' => 'queued',
        ]));
    }

    /**
     * Cancelling a batch preserves completed work and cancels queued work.
     */
    public function test_cancel_batch(): void {
        global $DB, $USER;

        [$batch] = $this->create_batch();
        (new batch_manager())->cancel((int) $batch->id, (int) $USER->id);

        $this->assertSame('cancelled', $DB->get_field('report_imagealt_batch', 'status', ['id' => $batch->id]));
        $this->assertSame(
            'cancelled',
            $DB->get_field('report_imagealt_suggestion', 'status', ['batchid' => $batch->id]),
        );
    }

    /**
     * Retrying a partial batch queues only its failed items.
     */
    public function test_retry_failed_items(): void {
        global $DB, $USER;

        [$batch] = $this->create_batch();
        $DB->set_field('report_imagealt_suggestion', 'status', 'failed', ['batchid' => $batch->id]);
        $DB->set_field('report_imagealt_batch', 'status', 'partial', ['id' => $batch->id]);

        (new batch_manager())->retry_failed((int) $batch->id, (int) $USER->id);

        $this->assertSame('queued', $DB->get_field('report_imagealt_batch', 'status', ['id' => $batch->id]));
        $this->assertSame(
            'queued',
            $DB->get_field('report_imagealt_suggestion', 'status', ['batchid' => $batch->id]),
        );
    }

    /**
     * Create one eligible indexed occurrence and batch.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: context_course}
     */
    private function create_batch(): array {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
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
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        // Requesting a batch is refused without a provider, so one is reported here purely to get a batch for the
        // task to run against. Tests that care what the provider then does rebind the manager with their own.
        $aimanager = $this->stub_ai_availability(true);
        $batch = (new batch_manager())->create($context, [$occurrence->id], (int) $USER->id);
        return [$batch, $course, $context];
    }
}
