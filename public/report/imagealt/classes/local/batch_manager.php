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

use report_imagealt\local\task\generate_suggestions;

/**
 * Creates and controls persistent bulk suggestion batches.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_manager {
    /**
     * Suggestion statuses that still represent work the requesting user has not finished with.
     *
     * A suggestion in any of these states is either being generated or waiting to be reviewed, so the image it
     * describes must not be queued again: doing so pays a second AI provider call for a description the user
     * already has, and leaves two competing suggestions for one image.
     *
     * @var string[]
     */
    public const OUTSTANDING_STATUSES = ['queued', 'processing', 'ready'];

    /**
     * Queue eligible occurrences for bounded ad hoc processing.
     *
     * @param \context $context Requested report scope.
     * @param int[] $occurrenceids Selected occurrence IDs.
     * @param int $userid Requesting user ID.
     * @return \stdClass Batch record.
     */
    public function create(\context $context, array $occurrenceids, int $userid): \stdClass {
        global $DB;

        // Refused outright rather than queued, because a batch created without a provider to serve it would fail
        // every suggestion in it and report that as a batch of errors, which reads as something having gone wrong
        // rather than as a feature the site does not have switched on.
        if (!suggestion_service::is_available($context, $userid)) {
            throw new \moodle_exception('error:aiunavailable', 'report_imagealt');
        }

        $contentmanager = new manager();
        $eligible = [];
        foreach (array_unique(array_map('intval', $occurrenceids)) as $id) {
            $occurrence = $DB->get_record('report_imagealt_occurrence', ['id' => $id]);
            if (!$occurrence || !$occurrence->aieligible || $occurrence->analysisstate !== 'ready') {
                continue;
            }
            // Describing an image marked decorative contradicts the decision to hide it from screen readers, so it is
            // refused here as well as withheld in the report, for the same reasons as the outstanding-work check
            // below: a stale page, and this being the only guard on the endpoint.
            if ($occurrence->status === 'decorative') {
                continue;
            }
            if (!manager::is_in_scope($occurrence, $context) || !$contentmanager->can_edit_occurrence($occurrence, $userid)) {
                continue;
            }
            // Checked as well as hidden in the UI, because the report page a selection was made on can be minutes
            // old by the time it is submitted, and because it is the only guard on the ad hoc bulk endpoint.
            if (self::has_outstanding_suggestion((int) $occurrence->id, $userid)) {
                continue;
            }
            $eligible[] = $occurrence;
        }
        if (!$eligible) {
            throw new \moodle_exception('error:imagenotavailable', 'report_imagealt');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $batch = (object) [
            'contextid' => $context->id,
            'userid' => $userid,
            'status' => 'queued',
            'total' => count($eligible),
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $batch->id = $DB->insert_record('report_imagealt_batch', $batch);
        foreach ($eligible as $occurrence) {
            $DB->insert_record('report_imagealt_suggestion', (object) [
                'occurrenceid' => $occurrence->id,
                'batchid' => $batch->id,
                'userid' => $userid,
                'status' => 'queued',
                'originalhash' => $occurrence->contenthash,
                'suggestion' => null,
                'errormessage' => null,
                'attempts' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();
        $this->queue_task((int) $batch->id);
        return $batch;
    }

    /**
     * Whether a user already has a suggestion for an image that is generating or waiting to be reviewed.
     *
     * @param int $occurrenceid Occurrence ID.
     * @param int $userid User ID.
     * @return bool
     */
    public static function has_outstanding_suggestion(int $occurrenceid, int $userid): bool {
        global $DB;

        [$statussql, $statusparams] = $DB->get_in_or_equal(self::OUTSTANDING_STATUSES, SQL_PARAMS_NAMED, 'status');
        return $DB->record_exists_select(
            'report_imagealt_suggestion',
            "occurrenceid = :occurrenceid AND userid = :userid AND status {$statussql}",
            $statusparams + ['occurrenceid' => $occurrenceid, 'userid' => $userid],
        );
    }

    /**
     * Summarise the user's own bulk suggestion work that is still outstanding in a report scope.
     *
     * Scoped by the images the suggestions point at rather than by the batch's own context, so a site or category
     * report surfaces work requested further down instead of only work requested at exactly that level.
     *
     * @param \context $context The report scope being viewed.
     * @param int $userid Requesting user ID.
     * @return array{ready: int, generating: int, batches: int, latestbatchid: int}|null Null when nothing is
     *      outstanding, so callers can skip the summary entirely.
     */
    public function get_outstanding_summary(\context $context, int $userid): ?array {
        global $DB;

        [$scopesql, $scopeparams] = manager::get_occurrence_scope_condition($context, 'occurrence');
        $record = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT batch.id) AS batches,
                    MAX(batch.id) AS latestbatchid,
                    SUM(CASE WHEN suggestion.status = :ready THEN 1 ELSE 0 END) AS ready,
                    SUM(CASE WHEN suggestion.status IN (:queued, :processing) THEN 1 ELSE 0 END) AS generating
               FROM {report_imagealt_suggestion} suggestion
               JOIN {report_imagealt_batch} batch ON batch.id = suggestion.batchid
               JOIN {report_imagealt_occurrence} occurrence ON occurrence.id = suggestion.occurrenceid
              WHERE batch.userid = :userid
                    AND suggestion.status IN (:readyagain, :queuedagain, :processingagain)
                    AND {$scopesql}",
            $scopeparams + [
                'userid' => $userid,
                'ready' => 'ready',
                'queued' => 'queued',
                'processing' => 'processing',
                'readyagain' => 'ready',
                'queuedagain' => 'queued',
                'processingagain' => 'processing',
            ],
        );
        if (!$record || !$record->batches) {
            return null;
        }

        return [
            'ready' => (int) $record->ready,
            'generating' => (int) $record->generating,
            'batches' => (int) $record->batches,
            'latestbatchid' => (int) $record->latestbatchid,
        ];
    }

    /**
     * Cancel work which has not started.
     *
     * @param int $batchid Batch ID.
     * @param int $userid Requesting user ID.
     */
    public function cancel(int $batchid, int $userid): void {
        global $DB;

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        if ((int) $batch->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        $DB->set_field_select(
            'report_imagealt_suggestion',
            'status',
            'cancelled',
            'batchid = :batchid AND status = :queued',
            ['batchid' => $batchid, 'queued' => 'queued']
        );
        // Recounted after the items move, so the batch totals still describe the items. Skipping this left the
        // summary claiming work was ready to review that had in fact just been cancelled.
        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        $this->apply_counts($batch);
        $batch->status = 'cancelled';
        $batch->timemodified = time();
        $DB->update_record('report_imagealt_batch', $batch);
    }

    /**
     * Apply generated suggestions to their images as-is.
     *
     * Used by the review table's per row and bulk "Accept" actions, for suggestions the user is happy with
     * unedited. Anything the user wants to change goes through the review modal instead.
     *
     * @param int $batchid Batch the suggestions must belong to.
     * @param int[] $suggestionids Suggestion IDs to accept.
     * @param int $userid Requesting user ID.
     * @return array{accepted: int, skipped: int} How many were applied, and how many could not be.
     */
    public function accept(int $batchid, array $suggestionids, int $userid): array {
        global $DB;

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        if ((int) $batch->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        $contentmanager = new manager();
        $accepted = 0;
        $skipped = 0;
        foreach (array_unique(array_map('intval', $suggestionids)) as $suggestionid) {
            $suggestion = $DB->get_record('report_imagealt_suggestion', [
                'id' => $suggestionid,
                'batchid' => $batchid,
                'userid' => $userid,
                'status' => 'ready',
            ]);
            if (!$suggestion) {
                $skipped++;
                continue;
            }
            try {
                $contentmanager->update_occurrence(
                    (int) $suggestion->occurrenceid,
                    (string) $suggestion->suggestion,
                    false,
                    $userid,
                    (int) $suggestion->id,
                );
            } catch (\Throwable $e) {
                // The content behind the image changed after the suggestion was generated, or it is no longer
                // editable. The suggestion no longer describes what is there, so it is marked stale rather than
                // being written over content the user has not seen.
                $suggestion->status = 'stale';
                $suggestion->timemodified = time();
                $DB->update_record('report_imagealt_suggestion', $suggestion);
                $skipped++;
                continue;
            }
            $DB->set_field('report_imagealt_suggestion', 'status', 'accepted', ['id' => $suggestionid]);
            $accepted++;
        }

        // Accepting does not change how many items are done, but a suggestion that went stale above moves from the
        // completed count to the failed one, which can also make a finished batch a partly failed one.
        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        $this->apply_counts($batch);
        if (in_array($batch->status, ['complete', 'partial'], true)) {
            $batch->status = $batch->failed > 0 ? 'partial' : 'complete';
        }
        $batch->timemodified = time();
        $DB->update_record('report_imagealt_batch', $batch);

        return ['accepted' => $accepted, 'skipped' => $skipped];
    }

    /**
     * Reject generated suggestions without applying them to their images.
     *
     * The counterpart to {@see accept()}, and the only way a suggestion the reviewer judges wrong can leave the
     * outstanding work the report page counts. Without it the only route to clearing a bad suggestion would be
     * accepting it, which is the last thing that should be easier than rejecting it.
     *
     * @param int $batchid Batch the suggestions must belong to.
     * @param int[] $suggestionids Suggestion IDs to discard.
     * @param int $userid Requesting user ID.
     * @return array{discarded: int, skipped: int} How many were rejected, and how many were not there to reject.
     */
    public function discard(int $batchid, array $suggestionids, int $userid): array {
        global $DB;

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        if ((int) $batch->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        $now = time();
        $discarded = 0;
        $skipped = 0;
        foreach (array_unique(array_map('intval', $suggestionids)) as $suggestionid) {
            $suggestion = $DB->get_record('report_imagealt_suggestion', [
                'id' => $suggestionid,
                'batchid' => $batchid,
                'userid' => $userid,
                'status' => 'ready',
            ]);
            if (!$suggestion) {
                $skipped++;
                continue;
            }
            // Kept rather than deleted, so the image is not silently offered for generation again and the user can
            // see they have already dealt with it.
            $suggestion->status = 'discarded';
            $suggestion->timemodified = $now;
            $DB->update_record('report_imagealt_suggestion', $suggestion);
            $discarded++;
        }

        // Discarded and ready suggestions both count as processed, so the outcome counters do not move here. The
        // batch is still touched, because its last modified time is shown and its review breakdown has changed.
        $batch->timemodified = $now;
        $DB->update_record('report_imagealt_batch', $batch);

        return ['discarded' => $discarded, 'skipped' => $skipped];
    }

    /**
     * Set a batch record's outcome counters from the current state of its suggestions.
     *
     * Does not save the record, so callers can set the status in the same update.
     *
     * @param \stdClass $batch Batch record, updated in place.
     * @return array<string, int> The suggestion status histogram, for callers that also need to inspect it.
     */
    public function apply_counts(\stdClass $batch): array {
        global $DB;

        $counts = $DB->get_records_sql_menu(
            'SELECT status, COUNT(1) FROM {report_imagealt_suggestion} WHERE batchid = :batchid GROUP BY status',
            ['batchid' => $batch->id],
        );
        $counts = array_map('intval', $counts);
        $batch->completed = array_sum(array_map(
            static fn(string $status): int => $counts[$status] ?? 0,
            ['ready', 'accepted', 'discarded'],
        ));
        $batch->failed = ($counts['failed'] ?? 0) + ($counts['stale'] ?? 0);
        $batch->cancelled = $counts['cancelled'] ?? 0;

        return $counts;
    }

    /**
     * Retry only failed items.
     *
     * @param int $batchid Batch ID.
     * @param int $userid Requesting user ID.
     */
    public function retry_failed(int $batchid, int $userid): void {
        global $DB;

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        if ((int) $batch->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        // Retrying without a provider would only reproduce the same failures, so it is refused for the same reason
        // requesting a batch is.
        if (!suggestion_service::is_available(\context::instance_by_id($batch->contextid, MUST_EXIST), $userid)) {
            throw new \moodle_exception('error:aiunavailable', 'report_imagealt');
        }
        $DB->set_field_select(
            'report_imagealt_suggestion',
            'status',
            'queued',
            'batchid = :batchid AND status = :failed',
            ['batchid' => $batchid, 'failed' => 'failed']
        );
        $batch->status = 'queued';
        $batch->timemodified = time();
        $DB->update_record('report_imagealt_batch', $batch);
        $this->queue_task($batchid);
    }

    /**
     * Queue a processor whose custom data contains only a batch reference.
     *
     * @param int $batchid Batch ID.
     */
    public function queue_task(int $batchid): void {
        $task = new generate_suggestions();
        $task->set_custom_data(['batchid' => $batchid]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
