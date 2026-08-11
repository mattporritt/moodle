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
use moodle_url;

/**
 * Outstanding bulk suggestion work, surfaced on the report itself.
 *
 * Without this the report gives no sign that generated suggestions are waiting: the batch page is only reachable
 * immediately after requesting generation, so a user who navigates away has no route back to their own results.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class suggestion_summary implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param int $ready Suggestions generated and waiting to be reviewed.
     * @param int $generating Suggestions still queued or being generated.
     * @param int $batches How many of the user's batches the outstanding work spans.
     * @param int $latestbatchid The most recent batch holding outstanding work.
     */
    public function __construct(
        /** @var int Suggestions generated and waiting to be reviewed. */
        private readonly int $ready,
        /** @var int Suggestions still queued or being generated. */
        private readonly int $generating,
        /** @var int How many of the user's batches the outstanding work spans. */
        private readonly int $batches,
        /** @var int The most recent batch holding outstanding work. */
        private readonly int $latestbatchid,
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        return [
            'ready' => $this->ready,
            'hasready' => $this->ready > 0,
            'generating' => $this->generating,
            'hasgenerating' => $this->generating > 0,
            // A user can have several batches outstanding at once, and there is no page listing them, so the
            // button opens the most recent and the text says when there is more behind it.
            'hasmorebatches' => $this->batches > 1,
            'batches' => $this->batches,
            'batchurl' => (new moodle_url('/report/imagealt/batch.php', ['id' => $this->latestbatchid]))->out(false),
        ];
    }
}
