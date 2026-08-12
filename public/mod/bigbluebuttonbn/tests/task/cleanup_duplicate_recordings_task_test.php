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

namespace mod_bigbluebuttonbn\task;

use advanced_testcase;
use mod_bigbluebuttonbn\recording;
use mod_bigbluebuttonbn\test\testcase_helper_trait;

/**
 * Test class for the cleanup_duplicate_recordings_task.
 *
 * @package    mod_bigbluebuttonbn
 * @copyright  2026 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @covers \mod_bigbluebuttonbn\task\cleanup_duplicate_recordings_task
 */
final class cleanup_duplicate_recordings_task_test extends advanced_testcase {
    use testcase_helper_trait;

    /**
     * Insert a bigbluebuttonbn_recordings row directly, bypassing the application logic, so we can
     * simulate rows created before the MDL-89119 fix was in place.
     *
     * @param int $bigbluebuttonbnid
     * @param string $recordingid
     * @param int $groupid
     * @param int $timecreated
     * @return int the id of the inserted row
     */
    protected function insert_recording_row(
        int $bigbluebuttonbnid,
        string $recordingid,
        int $groupid = 0,
        int $timecreated = 0
    ): int {
        global $DB;

        $timecreated = $timecreated ?: time();
        return $DB->insert_record('bigbluebuttonbn_recordings', [
            'courseid' => $this->get_course()->id,
            'bigbluebuttonbnid' => $bigbluebuttonbnid,
            'groupid' => $groupid,
            'recordingid' => $recordingid,
            'headless' => 0,
            'imported' => 0,
            'status' => recording::RECORDING_STATUS_PROCESSED,
            'timecreated' => $timecreated,
            'usermodified' => 0,
            'timemodified' => $timecreated,
        ]);
    }

    /**
     * Test that duplicate recording rows are removed, keeping the earliest row of each group.
     */
    public function test_cleanup_removes_duplicates_keeping_earliest(): void {
        $this->resetAfterTest();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance(['course' => $this->get_course()->id]);

        // A duplicated pair: the earliest (lowest id) row should survive.
        $originalid = $this->insert_recording_row($activity->id, 'recording-1', 0, 1000);
        $duplicateid = $this->insert_recording_row($activity->id, 'recording-1', 0, 2000);

        // A distinct, non-duplicated recording that must be left untouched.
        $uniqueid = $this->insert_recording_row($activity->id, 'recording-2', 0, 1000);

        $this->expectOutputRegex('/Removing 1 duplicate recording row\(s\)/');
        $task = new cleanup_duplicate_recordings_task();
        $task->execute();

        $this->assertTrue(recording::record_exists($originalid));
        $this->assertFalse(recording::record_exists($duplicateid));
        $this->assertTrue(recording::record_exists($uniqueid));
        $this->assertEquals(2, recording::count_records(['bigbluebuttonbnid' => $activity->id]));
    }

    /**
     * Test that rows with different groupid are not treated as duplicates of one another, since
     * they refer to different group recordings.
     */
    public function test_cleanup_does_not_remove_rows_with_different_groupid(): void {
        $this->resetAfterTest();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance(['course' => $this->get_course()->id]);

        $groupzeroid = $this->insert_recording_row($activity->id, 'recording-1', 0);
        $grouponeid = $this->insert_recording_row($activity->id, 'recording-1', 1);

        $this->expectOutputRegex('/No duplicate recordings were found/');
        $task = new cleanup_duplicate_recordings_task();
        $task->execute();

        $this->assertTrue(recording::record_exists($groupzeroid));
        $this->assertTrue(recording::record_exists($grouponeid));
    }

    /**
     * Test that the task is a no-op, and queues no further task, when there are no duplicates.
     */
    public function test_cleanup_noop_when_no_duplicates(): void {
        $this->resetAfterTest();
        global $DB;
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance(['course' => $this->get_course()->id]);
        $this->insert_recording_row($activity->id, 'recording-1');

        $this->expectOutputRegex('/No duplicate recordings were found/');
        $task = new cleanup_duplicate_recordings_task();
        $task->execute();

        $this->assertEquals(1, $DB->count_records('bigbluebuttonbn_recordings'));
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(cleanup_duplicate_recordings_task::class));
    }

    /**
     * Test that when the number of duplicates exceeds the batch size, the task re-queues itself
     * so that all duplicates are eventually removed without a single run scanning everything.
     */
    public function test_cleanup_requeues_when_more_duplicates_remain(): void {
        $this->resetAfterTest();
        global $DB;
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance(['course' => $this->get_course()->id]);

        // Force a tiny chunk size so we can exercise the re-queue path without inserting
        // hundreds of rows.
        $chunksizeprop = new \ReflectionProperty(cleanup_duplicate_recordings_task::class, 'chunksize');
        $chunksizeprop->setAccessible(true);
        $originalchunksize = $chunksizeprop->getValue();
        $chunksizeprop->setValue(null, 2);

        try {
            // Three duplicate pairs: with a chunk size of 2, one run will not remove them all.
            for ($i = 0; $i < 3; $i++) {
                $this->insert_recording_row($activity->id, "recording-{$i}", 0, 1000);
                $this->insert_recording_row($activity->id, "recording-{$i}", 0, 2000);
            }

            $this->expectOutputRegex('/Removing 2 duplicate recording row\(s\).*Removing 1 duplicate recording row\(s\)/s');
            $task = new cleanup_duplicate_recordings_task();
            $task->execute();

            // Not all duplicates were removed in a single run, so a follow-up task must have
            // been queued to finish the job.
            $this->assertNotEmpty(\core\task\manager::get_adhoc_tasks(cleanup_duplicate_recordings_task::class));

            // Draining the queue should eventually remove every duplicate.
            $this->runAdhocTasks(cleanup_duplicate_recordings_task::class);
            $this->assertEquals(3, $DB->count_records('bigbluebuttonbn_recordings'));
        } finally {
            $chunksizeprop->setValue(null, $originalchunksize);
        }
    }
}
