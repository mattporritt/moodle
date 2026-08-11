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

namespace report_imagealt\output;

/**
 * Tests for the bulk batch progress summary.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(batch_progress::class)]
final class batch_progress_test extends \advanced_testcase {
    /**
     * Build a batch record for the given counts.
     *
     * @param string $status Batch status.
     * @param int $total Total images.
     * @param int $completed Images with a suggestion ready.
     * @param int $failed Images that could not be described.
     * @param int $cancelled Images cancelled before processing.
     * @return \stdClass
     */
    private function batch(string $status, int $total, int $completed, int $failed = 0, int $cancelled = 0): \stdClass {
        return (object) [
            'status' => $status,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'timemodified' => time(),
        ];
    }

    /**
     * Only queued and processing batches are still worth refreshing the page for.
     */
    public function test_is_active_covers_only_unfinished_batches(): void {
        $this->resetAfterTest();

        $this->assertTrue((new batch_progress($this->batch('queued', 1, 0)))->is_active());
        $this->assertTrue((new batch_progress($this->batch('processing', 1, 0)))->is_active());
        $this->assertFalse((new batch_progress($this->batch('complete', 1, 1)))->is_active());
        $this->assertFalse((new batch_progress($this->batch('partial', 1, 0, 1)))->is_active());
        $this->assertFalse((new batch_progress($this->batch('cancelled', 1, 0, 0, 1)))->is_active());
    }

    /**
     * Progress counts every image that reached an outcome, not just the successful ones.
     */
    public function test_export_counts_all_outcomes_as_processed(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');

        $export = (new batch_progress($this->batch('partial', 10, 6, 3, 1)))->export_for_template($output);

        $this->assertSame(10, $export['processed']);
        $this->assertSame(100, $export['percent']);
        $this->assertSame(6, $export['completed']);
        $this->assertSame(3, $export['failed']);
        $this->assertSame(1, $export['cancelled']);
        $this->assertTrue($export['hascancelled']);
        $this->assertFalse($export['active']);
        $this->assertSame('warning', $export['statusvariant']);
        $this->assertSame(get_string('batchstatus_partial', 'report_imagealt'), $export['statuslabel']);
    }

    /**
     * Suggestions already applied are reported separately from ones still waiting to be reviewed.
     */
    public function test_export_separates_applied_from_awaiting_review(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');

        // The batch counts all six as done; only two of them are actually still waiting for the user.
        $export = (new batch_progress($this->batch('complete', 6, 6), [
            'ready' => 2,
            'accepted' => 3,
            'discarded' => 1,
        ]))->export_for_template($output);

        $this->assertSame(2, $export['ready']);
        $this->assertSame(3, $export['applied']);
        $this->assertTrue($export['hasapplied']);
        // Rejected descriptions are accounted for rather than appearing to vanish from the batch.
        $this->assertSame(1, $export['discarded']);
        $this->assertTrue($export['hasdiscarded']);

        // Nothing accepted or discarded yet, so neither count is shown at all.
        $untouched = (new batch_progress($this->batch('complete', 2, 2), ['ready' => 2]))
            ->export_for_template($output);
        $this->assertSame(2, $untouched['ready']);
        $this->assertSame(0, $untouched['applied']);
        $this->assertFalse($untouched['hasapplied']);
        $this->assertSame(0, $untouched['discarded']);
        $this->assertFalse($untouched['hasdiscarded']);
    }

    /**
     * A description that went out of date after its batch finished is reported, rather than counted towards the
     * batch total while appearing under no heading at all.
     */
    public function test_export_accounts_for_descriptions_that_went_out_of_date(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');

        // The state that used to go unreported: the batch finished with its one image counted as completed, and the
        // suggestion went stale afterwards, so nothing recomputed the counters.
        $export = (new batch_progress($this->batch('complete', 1, 1), ['stale' => 1]))
            ->export_for_template($output);

        $this->assertSame(1, $export['stale']);
        $this->assertTrue($export['hasstale']);
        $this->assertSame(0, $export['ready']);
        $this->assertSame(0, $export['failed']);
        // The image still reached an outcome, so the bar is unaffected by reporting it separately.
        $this->assertSame(1, $export['processed']);
        $this->assertSame(100, $export['percent']);

        // Once the counters are recalculated they fold stale suggestions into the failure count. Only the genuine
        // failures are reported as failed, so neither suggestion is counted twice.
        $recalculated = (new batch_progress($this->batch('complete', 3, 1, 2), [
            'ready' => 1,
            'failed' => 1,
            'stale' => 1,
        ]))->export_for_template($output);
        $this->assertSame(1, $recalculated['stale']);
        $this->assertSame(1, $recalculated['failed']);

        // Nothing is out of date in the common case, so the heading is not shown at all.
        $current = (new batch_progress($this->batch('complete', 1, 1), ['ready' => 1]))
            ->export_for_template($output);
        $this->assertSame(0, $current['stale']);
        $this->assertFalse($current['hasstale']);
    }

    /**
     * A batch that has not started yet reports no progress rather than dividing by zero.
     */
    public function test_export_handles_a_batch_with_nothing_processed(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');

        $export = (new batch_progress($this->batch('queued', 4, 0)))->export_for_template($output);

        $this->assertSame(0, $export['processed']);
        $this->assertSame(0, $export['percent']);
        $this->assertFalse($export['hascancelled']);
        $this->assertTrue($export['active']);

        $empty = (new batch_progress($this->batch('complete', 0, 0)))->export_for_template($output);
        $this->assertSame(0, $empty['percent']);
    }
}
