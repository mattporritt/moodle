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

use report_imagealt\local\task\reconcile_site;

/**
 * Tests for the scheduled index reconciliation safety net.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reconcile_site::class)]
final class reconcile_site_test extends \advanced_testcase {
    /**
     * The default run creates one site discovery job and removes expired completed bookkeeping.
     */
    public function test_execute_creates_site_job_and_cleans_old_jobs(): void {
        global $DB;

        $this->resetAfterTest();
        $DB->insert_record('report_imagealt_scan', (object) [
            'contextid' => \context_system::instance()->id,
            'status' => 'complete',
            'phase' => 'courses',
            'lastid' => 1,
            'queued' => 1,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);

        (new reconcile_site())->execute();

        $this->assertFalse($DB->record_exists('report_imagealt_scan', ['status' => 'complete']));
        $this->assertTrue($DB->record_exists('report_imagealt_scan', [
            'contextid' => \context_system::instance()->id,
            'status' => 'queued',
        ]));
    }

    /**
     * A subsequent configured run reuses active work instead of creating a competing site crawl.
     */
    public function test_execute_reuses_existing_active_job(): void {
        global $DB;

        $this->resetAfterTest();
        $task = new reconcile_site();
        $task->execute();
        $task->execute();

        $this->assertSame(1, $DB->count_records_select(
            'report_imagealt_scan',
            'contextid = :contextid AND status <> :complete',
            ['contextid' => \context_system::instance()->id, 'complete' => 'complete'],
        ));
    }
}
