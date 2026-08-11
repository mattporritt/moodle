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

namespace report_imagealt\local\task;

/**
 * Process a bounded number of unpublished suggestions from one batch.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generate_suggestions extends \core\task\adhoc_task {
    /** Maximum number of provider requests performed by one task. */
    private const ITEM_LIMIT = 10;

    #[\Override]
    public function get_name(): string {
        return get_string('taskgenerate', 'report_imagealt');
    }

    #[\Override]
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $batch = $DB->get_record('report_imagealt_batch', ['id' => (int) $data->batchid]);
        if (!$batch || $batch->status === 'cancelled') {
            return;
        }

        $batch->status = 'processing';
        $batch->timemodified = time();
        $DB->update_record('report_imagealt_batch', $batch);
        $suggestions = $DB->get_records(
            'report_imagealt_suggestion',
            ['batchid' => $batch->id, 'status' => 'queued'],
            'id ASC',
            '*',
            0,
            self::ITEM_LIMIT,
        );
        $service = new \report_imagealt\local\suggestion_service();
        foreach ($suggestions as $suggestion) {
            $currentbatchstatus = $DB->get_field('report_imagealt_batch', 'status', ['id' => $batch->id]);
            if ($currentbatchstatus === 'cancelled') {
                break;
            }
            $service->generate((int) $suggestion->id);
        }

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batch->id], '*', MUST_EXIST);
        $counts = (new \report_imagealt\local\batch_manager())->apply_counts($batch);
        $queued = $counts['queued'] ?? 0;
        $processing = $counts['processing'] ?? 0;
        if ($batch->status !== 'cancelled') {
            $batch->status = ($queued + $processing) > 0
                ? 'processing'
                : ($batch->failed > 0 ? 'partial' : 'complete');
        }
        $batch->timemodified = time();
        $DB->update_record('report_imagealt_batch', $batch);

        if ($queued > 0 && $batch->status !== 'cancelled') {
            (new \report_imagealt\local\batch_manager())->queue_task((int) $batch->id);
        }
    }
}
