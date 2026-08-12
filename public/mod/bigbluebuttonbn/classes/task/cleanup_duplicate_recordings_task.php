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

use core\task\adhoc_task;
use core\task\manager;

/**
 * Adhoc task to clean up duplicate rows in bigbluebuttonbn_recordings.
 *
 * A race condition in meeting::create_meeting() could previously allow two near-simultaneous
 * join requests to both insert a bigbluebuttonbn_recordings row for the same underlying
 * BigBlueButton recording, i.e. two rows sharing the same bigbluebuttonbnid/groupid/recordingid
 * but with a different id. This has since been fixed at the application level (see
 * meeting::join_meeting()), but existing sites may still carry duplicate rows created before
 * the fix. This task removes them in bounded batches so that upgrades on large sites are not
 * blocked by a single long-running query.
 *
 * For each duplicate group, the row with the lowest id (the original) is kept and the
 * remaining rows are removed. Removal is a plain delete of the local reference row: it does
 * not call the BigBlueButton server, so the underlying recording is left untouched.
 *
 * This task is queued once from the db/upgrade.php step that introduces this fix. A future
 * release is expected to add a unique index on (bigbluebuttonbnid, groupid, recordingid) once
 * this cleanup has had a chance to run via cron on all sites (see MDL-89119).
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class cleanup_duplicate_recordings_task extends adhoc_task {
    /** @var int Maximum number of duplicate rows to remove per run */
    protected static $chunksize = 500;

    /**
     * Run the cleanup task, re-queueing itself if more duplicates remain.
     */
    public function execute() {
        if ($this->process_duplicate_recordings()) {
            manager::queue_adhoc_task(new static());
        }
    }

    /**
     * Find and remove a bounded batch of duplicate recording rows.
     *
     * @return bool Whether more duplicates are waiting to be processed
     */
    protected function process_duplicate_recordings(): bool {
        global $DB;

        $classname = static::class;
        mtrace("Executing {$classname}...");

        $duplicateids = $this->get_duplicate_ids_to_remove();

        if (empty($duplicateids)) {
            mtrace("No duplicate recordings were found.");
            return false;
        }

        mtrace("Removing " . count($duplicateids) . " duplicate recording row(s)...");
        [$insql, $inparams] = $DB->get_in_or_equal($duplicateids);
        $DB->delete_records_select('bigbluebuttonbn_recordings', "id {$insql}", $inparams);

        // There may be more duplicates than the chunk size; report back so we get re-queued.
        return count($duplicateids) >= self::$chunksize;
    }

    /**
     * Get a bounded batch of ids to remove, keeping the lowest id per duplicate group.
     *
     * @return array
     */
    protected function get_duplicate_ids_to_remove(): array {
        global $DB;

        $sql = "SELECT bbr.id
                  FROM {bigbluebuttonbn_recordings} bbr
                  JOIN {bigbluebuttonbn_recordings} bbr2
                    ON bbr2.bigbluebuttonbnid = bbr.bigbluebuttonbnid
                   AND bbr2.groupid = bbr.groupid
                   AND bbr2.recordingid = bbr.recordingid
                   AND bbr2.id < bbr.id";

        return array_keys($DB->get_records_sql($sql, [], 0, self::$chunksize));
    }
}
