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

namespace tool_recyclebin;

/**
 * Tests for the interaction between asynchronous course deletion and the category recycle bin.
 *
 * @package    tool_recyclebin
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_recyclebin\category_bin
 */
final class async_course_deletion_test extends \advanced_testcase {
    /**
     * A course deleted asynchronously must leave exactly one restorable copy in the category bin.
     */
    public function test_async_deletion_stores_a_single_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('categorybinenable', 1, 'tool_recyclebin');
        set_config('enablecourseasyncdeletion', 1, 'moodlecourse');

        $course = $this->getDataGenerator()->create_course();
        $this->assertEquals(0, $DB->count_records('tool_recyclebin_category'));

        // A manager deletes the course. Async is preferred, so this only queues the ad hoc task.
        delete_course($course, false);
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));

        // Nothing has been backed up to the recycle bin yet: the queued task has not run.
        $this->assertEquals(0, $DB->count_records('tool_recyclebin_category'));

        // Cron runs the queued \core_course\task\course_async_deletion task. The task calls
        // delete_course() with $showfeedback = true, so swallow the progress output it prints.
        ob_start();
        $this->run_all_adhoc_tasks();
        ob_end_clean();

        // The course row is now really gone.
        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));

        // There must be exactly one restorable copy, whenever the backup was actually taken.
        $this->assertEquals(1, $DB->count_records('tool_recyclebin_category'));

        // And that copy must really restore.
        $recyclebin = new \tool_recyclebin\category_bin($course->category);
        $this->assertCount(1, $recyclebin->get_items());
    }

    /**
     * A course deleted synchronously (async deletion disabled) must still leave exactly one copy.
     */
    public function test_sync_deletion_stores_a_single_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('categorybinenable', 1, 'tool_recyclebin');
        set_config('enablecourseasyncdeletion', 0, 'moodlecourse');

        $course = $this->getDataGenerator()->create_course();
        $this->assertEquals(0, $DB->count_records('tool_recyclebin_category'));

        ob_start();
        delete_course($course, true);
        ob_end_clean();

        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));
        $this->assertEquals(1, $DB->count_records('tool_recyclebin_category'));
    }
}
