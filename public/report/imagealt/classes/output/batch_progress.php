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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;

/**
 * Progress summary for one bulk alternative text suggestion batch.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_progress implements renderable, templatable {
    /** @var string[] Batch statuses that mean work is still expected to happen. */
    private const ACTIVE_STATUSES = ['queued', 'processing'];

    /** @var array<string, string> Bootstrap contextual suffix for each batch status. */
    private const STATUS_VARIANTS = [
        'queued' => 'secondary',
        'processing' => 'info',
        'complete' => 'success',
        'partial' => 'warning',
        'cancelled' => 'secondary',
    ];

    /**
     * Constructor.
     *
     * @param stdClass $batch The batch record.
     */
    public function __construct(
        /** @var stdClass The batch record. */
        private readonly stdClass $batch,
        /** @var array<string, int> Count of this batch's suggestions per suggestion status. */
        private readonly array $statuscounts = [],
    ) {
    }

    /**
     * Whether this batch is still queued or processing, and so worth refreshing for.
     *
     * @return bool
     */
    public function is_active(): bool {
        return in_array($this->batch->status, self::ACTIVE_STATUSES, true);
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $total = (int) $this->batch->total;
        $completed = (int) $this->batch->completed;
        $failed = (int) $this->batch->failed;
        $cancelled = (int) $this->batch->cancelled;

        // Anything not still waiting has reached an outcome, so the bar reflects work finished rather than work
        // that succeeded. Otherwise a batch where everything failed would sit at 0% looking stuck.
        $processed = min($completed + $failed + $cancelled, $total);

        // The batch's own completed counter covers every suggestion the user no longer has to act on, whether it
        // is waiting to be reviewed, already applied, or discarded. Those need separating here: "ready to review"
        // is the number the user is actually working through, and counting applied images in it would leave the
        // summary claiming there was review work left on a batch that had just been fully accepted.
        $ready = (int) ($this->statuscounts['ready'] ?? 0);
        $applied = (int) ($this->statuscounts['accepted'] ?? 0);
        // Reported beside the applied count so a reviewer who rejects a description sees it accounted for. Left out
        // of the breakdown, discarded suggestions would appear to vanish from a batch whose total still counts them.
        $discarded = (int) ($this->statuscounts['discarded'] ?? 0);
        // A suggestion can go stale long after its batch finished, and nothing recomputes the batch counters when it
        // does, so a stale suggestion was reported in neither the ready count nor the failed one: a finished batch of
        // one image claimed to have processed it while accounting for it nowhere.
        $stale = (int) ($this->statuscounts['stale'] ?? 0);
        // Taken off the reported failure count, because the stored counter folds stale suggestions into it whenever it
        // is next recalculated, and the same suggestion must not appear under both headings. Deliberately not taken
        // off $processed above: a stale suggestion has reached an outcome either way, so the bar is right as it is.
        $reportedfailed = max(0, $failed - $stale);

        return [
            'ready' => $ready,
            'applied' => $applied,
            'hasapplied' => $applied > 0,
            'discarded' => $discarded,
            'hasdiscarded' => $discarded > 0,
            'stale' => $stale,
            'hasstale' => $stale > 0,
            'statuslabel' => get_string("batchstatus_{$this->batch->status}", 'report_imagealt'),
            'statusvariant' => self::STATUS_VARIANTS[$this->batch->status] ?? 'secondary',
            'active' => $this->is_active(),
            'total' => $total,
            'completed' => $completed,
            'failed' => $reportedfailed,
            'cancelled' => $cancelled,
            'hascancelled' => $cancelled > 0,
            'processed' => $processed,
            'percent' => $total > 0 ? (int) round($processed / $total * 100) : 0,
            'progresslabel' => get_string('batchprogress', 'report_imagealt', (object) [
                'processed' => $processed,
                'total' => $total,
            ]),
            'timemodified' => userdate($this->batch->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        ];
    }
}
