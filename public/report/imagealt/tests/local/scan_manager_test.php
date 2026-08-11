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

namespace report_imagealt\local;

/**
 * Tests for resumable discovery and durable dirty-target processing.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scan_manager::class)]
final class scan_manager_test extends \advanced_testcase {
    /**
     * Repeated wide refresh requests reuse one persistent cursor.
     */
    public function test_request_reuses_active_discovery_job(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new scan_manager();

        $first = $manager->request(\context_system::instance());
        $second = $manager->request(\context_system::instance());

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $DB->count_records('report_imagealt_scan'));
    }

    /**
     * Discovery advances through category and course phases and queues only targets in scope.
     */
    public function test_discovery_resumes_across_phases(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);
        $unrelated = $generator->create_category();
        $courseone = $generator->create_course(['category' => $parent->id]);
        $coursetwo = $generator->create_course(['category' => $child->id]);
        $generator->create_course(['category' => $unrelated->id]);

        // Generator events intentionally exercise queueing elsewhere; remove them to isolate coordinator discovery here.
        $DB->delete_records('report_imagealt_queue');
        $manager = new scan_manager();
        $job = $manager->request(\context_coursecat::instance($parent->id));
        for ($pass = 0; $pass < 4; $pass++) {
            $manager->discover($job->id);
        }

        $job = $DB->get_record('report_imagealt_scan', ['id' => $job->id], '*', MUST_EXIST);
        $this->assertSame('complete', $job->status);
        $this->assertEqualsCanonicalizing(
            [$parent->id, $child->id],
            array_map('intval', $DB->get_fieldset_select(
                'report_imagealt_queue',
                'targetid',
                'targettype = :type',
                ['type' => scan_manager::TARGET_CATEGORY],
            )),
        );
        $this->assertEqualsCanonicalizing(
            [$courseone->id, $coursetwo->id],
            array_map('intval', $DB->get_fieldset_select(
                'report_imagealt_queue',
                'targetid',
                'targettype = :type',
                ['type' => scan_manager::TARGET_COURSE],
            )),
        );
    }

    /**
     * A course worker reconciles content and removes its durable dirty marker after a successful pass.
     */
    public function test_process_target_scans_course_and_clears_queue(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="one.jpg">']);
        $manager = new scan_manager();
        $queue = $DB->get_record('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_COURSE,
            'targetid' => $course->id,
        ], '*', MUST_EXIST);

        $manager->process_target($queue->id);

        $this->assertTrue($DB->record_exists('report_imagealt_occurrence', ['courseid' => $course->id]));
        $this->assertFalse($DB->record_exists('report_imagealt_queue', ['id' => $queue->id]));
    }

    /**
     * An edit arriving during processing advances the target revision for a guaranteed follow-up pass.
     */
    public function test_queue_target_advances_processing_revision(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $queue = $DB->get_record('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_COURSE,
            'targetid' => $course->id,
        ], '*', MUST_EXIST);
        $DB->set_field('report_imagealt_queue', 'status', 'processing', ['id' => $queue->id]);

        $updated = (new scan_manager())->queue_target(scan_manager::TARGET_COURSE, (int) $course->id);

        $this->assertSame((int) $queue->revision + 1, (int) $updated->revision);
        $this->assertSame('processing', $updated->status);
    }

    /**
     * Marking a target dirty advances the durable revision and never touches the shared ad hoc queue.
     */
    public function test_queue_target_records_dirty_row_without_adhoc(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $queue = $DB->get_record('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_COURSE,
            'targetid' => $course->id,
        ], '*', MUST_EXIST);

        $updated = (new scan_manager())->queue_target(scan_manager::TARGET_COURSE, (int) $course->id);

        $this->assertSame((int) $queue->revision + 1, (int) $updated->revision);
        $this->assertSame(0, $DB->count_records_select(
            'task_adhoc',
            $DB->sql_like('classname', ':classname'),
            ['classname' => '%report_imagealt%'],
        ));
    }

    /**
     * The drain scans dirty targets in place, clears their markers, and queues no ad hoc tasks.
     */
    public function test_drain_scans_dirty_targets_directly(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="one.jpg">']);

        (new scan_manager())->drain(time() + 60);

        $this->assertTrue($DB->record_exists('report_imagealt_occurrence', ['courseid' => $course->id]));
        $this->assertSame(0, $DB->count_records('report_imagealt_queue'));
        $this->assertSame(0, $DB->count_records_select(
            'task_adhoc',
            $DB->sql_like('classname', ':classname'),
            ['classname' => '%report_imagealt%'],
        ));
    }

    /**
     * The drain walks a site discovery cursor and scans the targets it records in a single run.
     */
    public function test_drain_walks_discovery_and_scans(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="one.jpg">']);
        // Isolate the discovery cursor from the generator's own dirty markers and prior scans.
        $DB->delete_records('report_imagealt_queue');
        $DB->delete_records('report_imagealt_occurrence');
        $manager = new scan_manager();
        $manager->request(\context_system::instance());

        $manager->drain(time() + 60);

        $this->assertTrue($DB->record_exists('report_imagealt_occurrence', ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('report_imagealt_scan', ['status' => 'complete']));
    }

    /**
     * A system-scoped discovery job walks categories, courses, and then sitewide user profiles.
     */
    public function test_discovery_includes_users_only_for_system_scope(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $DB->delete_records('report_imagealt_queue');
        $manager = new scan_manager();
        $job = $manager->request(\context_system::instance());
        for ($pass = 0; $pass < 6; $pass++) {
            $manager->discover($job->id);
        }

        $job = $DB->get_record('report_imagealt_scan', ['id' => $job->id], '*', MUST_EXIST);
        $this->assertSame('complete', $job->status);
        $this->assertTrue($DB->record_exists('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_USER,
            'targetid' => $user->id,
        ]));
    }

    /**
     * A category-scoped discovery job never queues sitewide user profile targets.
     */
    public function test_discovery_excludes_users_for_category_scope(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $category = $this->getDataGenerator()->create_category();
        $DB->delete_records('report_imagealt_queue');
        $manager = new scan_manager();
        $job = $manager->request(\context_coursecat::instance($category->id));
        for ($pass = 0; $pass < 6; $pass++) {
            $manager->discover($job->id);
        }

        $job = $DB->get_record('report_imagealt_scan', ['id' => $job->id], '*', MUST_EXIST);
        $this->assertSame('complete', $job->status);
        $this->assertSame(0, $DB->count_records('report_imagealt_queue', ['targettype' => scan_manager::TARGET_USER]));
    }

    /**
     * A user profile worker reconciles content and removes its durable dirty marker after a successful pass.
     */
    public function test_process_target_scans_user_and_clears_queue(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $manager = new scan_manager();
        $queue = $manager->queue_target(scan_manager::TARGET_USER, (int) $user->id);

        $manager->process_target($queue->id);

        $this->assertTrue($DB->record_exists('report_imagealt_occurrence', [
            'providerkey' => 'core_user',
            'itemkeyhash' => hash('sha256', "user:{$user->id}"),
        ]));
        $this->assertFalse($DB->record_exists('report_imagealt_queue', ['id' => $queue->id]));
    }

    /**
     * A course nothing has ever indexed is scanned when its report is opened. This is the restored course: no event
     * marked it dirty, so without this its report would report it as holding no images at all, and course scope
     * offers no refresh control to correct that with.
     */
    public function test_a_never_indexed_course_is_scanned_on_access(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = \context_course::instance($course->id);
        // Leave neither of the two traces a scanned course has: the generator's dirty marker, and indexed rows.
        $DB->delete_records('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_COURSE,
            'targetid' => $course->id,
        ]);
        $DB->delete_records('report_imagealt_occurrence', ['courseid' => $course->id]);

        $this->assertTrue((new scan_manager())->course_needs_scan($context));
    }

    /**
     * Content changed since the last pass and the drain task has not caught up, so the report does that work itself
     * rather than showing the user a table it knows to be behind.
     */
    public function test_a_dirty_course_is_scanned_on_access(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = \context_course::instance($course->id);
        // Course creation leaves the dirty marker this asserts on.

        $this->assertTrue((new scan_manager())->course_needs_scan($context));
    }

    /**
     * An up-to-date course is not rescanned, so revisiting the report does not repeat a walk over the whole course
     * for a user who only came back to read the table.
     */
    public function test_an_up_to_date_course_is_not_rescanned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = \context_course::instance($course->id);
        $manager = new scan_manager();
        $manager->request($context);

        $this->assertFalse($manager->course_needs_scan($context));
    }
}
