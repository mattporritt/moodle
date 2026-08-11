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

use core\lock\lock_config;

/**
 * Coordinates resumable discovery and persistent dirty-target processing.
 *
 * Content changes only persist a dirty-target row; work is never queued on the shared ad hoc task queue. The process_queue
 * scheduled task drains dirty rows directly within a time budget and advances the discovery cursor, mirroring how core_search
 * records index requests and drains them from a single scheduled task rather than one ad hoc task per change.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scan_manager {
    /** Number of category or course targets discovered by one coordinator execution. */
    public const DISCOVERY_LIMIT = 100;

    /** Seconds a single background drain run may spend before yielding to the next scheduled run. */
    public const DRAIN_BUDGET_SECONDS = 60;

    /** Target containing a course summary, sections, and supported activities. */
    public const TARGET_COURSE = 'course';

    /** Target containing one course-category description. */
    public const TARGET_CATEGORY = 'category';

    /** Target containing one user profile description, discovered sitewide rather than through a course. */
    public const TARGET_USER = 'user';

    /**
     * Whether a course's indexed data should be rebuilt before its report is shown.
     *
     * A course is bounded, so its report scans on access rather than making the user wait for the drain task or ask
     * for a refresh. Scanning on every load would repeat that walk for a visitor who only came back to read the
     * table, so this answers the two cases where the stored data cannot be trusted.
     *
     * @param \context_course $context Course context.
     * @return bool
     */
    public function course_needs_scan(\context_course $context): bool {
        global $DB;

        // Content changed and the drain task has not caught up. This is the same work that task would do, without
        // the wait, and processing it here clears the dirty marker for it.
        if (
            $DB->record_exists('report_imagealt_queue', [
                'targettype' => self::TARGET_COURSE,
                'targetid' => $context->instanceid,
            ])
        ) {
            return true;
        }

        // Nothing indexed for this course. Either it has never been scanned - restored, upgraded into, or changed by
        // something that fires none of the observed events - or it genuinely holds no images, which is
        // indistinguishable without storing a per-course scan time. Scanning is the safe reading of the two: a course
        // wrongly reported as having no images is a silent wrong answer, where the cost of being wrong the other way
        // is repeating a walk that finds nothing.
        [$scopesql, $scopeparams] = manager::get_occurrence_scope_condition($context);
        return !$DB->record_exists_select('report_imagealt_occurrence', $scopesql, $scopeparams);
    }

    /**
     * Request analysis for a course inline or create a resumable job for a larger context.
     *
     * A single course is bounded, so it is scanned in the request like the gradebook recomputes on access, giving the user
     * immediate results. System and category scopes only persist a small discovery cursor here so the request stays fast; the
     * process_queue scheduled task walks the cursor and drains the resulting dirty targets.
     *
     * @param \context $context Requested report context.
     * @return \stdClass|null Discovery job, or null for a course scanned inline.
     */
    public function request(\context $context): ?\stdClass {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            $queue = $this->queue_target(self::TARGET_COURSE, (int) $context->instanceid);
            $this->process_target((int) $queue->id);
            return null;
        }
        if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)) {
            throw new \coding_exception('Image alternative text analysis supports system, category, and course contexts.');
        }

        // Serialise job creation so two refresh clicks reuse one cursor instead of independently walking the same site.
        $lockfactory = lock_config::get_lock_factory('report_imagealt');
        $lock = $lockfactory->get_lock("request-context-{$context->id}", 2);
        if (!$lock) {
            return $this->get_active_job($context);
        }
        try {
            if ($activejob = $this->get_active_job($context)) {
                return $activejob;
            }

            $now = time();
            $job = (object) [
                'contextid' => $context->id,
                'status' => 'queued',
                'phase' => 'categories',
                'lastid' => 0,
                'queued' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $job->id = $DB->insert_record('report_imagealt_scan', $job);
            return $job;
        } finally {
            $lock->release();
        }
    }

    /**
     * Drain dirty targets and advance discovery cursors directly, within a time budget.
     *
     * This is the single entry point the process_queue scheduled task calls. It mirrors core_search's
     * search_index_task: process outstanding work in the task itself, resumably, rather than fanning out one ad hoc
     * task per target. Remaining work is picked up by the next scheduled run.
     *
     * @param int|null $deadline Unix time to stop by. Defaults to now plus the drain budget.
     */
    public function drain(?int $deadline = null): void {
        global $DB;

        $deadline ??= time() + self::DRAIN_BUDGET_SECONDS;
        while (time() < $deadline) {
            $progressed = false;

            // Snapshotting the queued IDs avoids re-selecting a row that another worker currently holds locked.
            $queueids = $DB->get_fieldset_select('report_imagealt_queue', 'id', 'status = :status', ['status' => 'queued']);
            foreach ($queueids as $queueid) {
                if (time() >= $deadline) {
                    return;
                }
                $progressed = $this->process_target((int) $queueid) || $progressed;
            }

            // Advancing discovery marks more targets dirty; the next loop iteration drains them.
            foreach ($this->get_active_job_ids() as $jobid) {
                if (time() >= $deadline) {
                    return;
                }
                $progressed = $this->discover((int) $jobid) || $progressed;
            }

            if (!$progressed) {
                // Queue is empty and discovery is complete, or the only remaining work is locked by another run.
                return;
            }
        }
    }

    /**
     * Return the IDs of discovery jobs that still have pages to walk.
     *
     * @return int[]
     */
    private function get_active_job_ids(): array {
        global $DB;

        return $DB->get_fieldset_select('report_imagealt_scan', 'id', 'status <> :complete', ['complete' => 'complete']);
    }

    /**
     * Return the active discovery job for a context.
     *
     * @param \context $context Requested report context.
     * @return \stdClass|null
     */
    public function get_active_job(\context $context): ?\stdClass {
        global $DB;

        $records = $DB->get_records_select(
            'report_imagealt_scan',
            'contextid = :contextid AND status <> :complete',
            ['contextid' => $context->id, 'complete' => 'complete'],
            'id DESC',
            '*',
            0,
            1,
        );
        return reset($records) ?: null;
    }

    /**
     * Discover one page of targets and persist the next cursor.
     *
     * @param int $jobid Discovery job ID.
     * @return bool True if the cursor advanced and the caller should continue; false when complete, missing, or locked.
     */
    public function discover(int $jobid): bool {
        global $DB;

        $lockfactory = lock_config::get_lock_factory('report_imagealt');
        $lock = $lockfactory->get_lock("discovery-job-{$jobid}", 0);
        if (!$lock) {
            return false;
        }
        try {
            $job = $DB->get_record('report_imagealt_scan', ['id' => $jobid]);
            if (!$job || $job->status === 'complete') {
                return false;
            }
            $context = \context::instance_by_id((int) $job->contextid, IGNORE_MISSING);
            if (!$context) {
                // Contexts may be deleted while a long-running discovery cursor is waiting. Complete the bookkeeping rather
                // than walking a scope that no longer exists.
                $job->status = 'complete';
                $job->timemodified = time();
                $DB->update_record('report_imagealt_scan', $job);
                return false;
            }
            $job->status = 'processing';

            if ($job->phase === 'categories') {
                $ids = scope::get_category_page($context, (int) $job->lastid, self::DISCOVERY_LIMIT);
                $targettype = self::TARGET_CATEGORY;
            } else if ($job->phase === 'courses') {
                $ids = scope::get_course_page($context, (int) $job->lastid, self::DISCOVERY_LIMIT);
                $targettype = self::TARGET_COURSE;
            } else {
                // Profile descriptions are not owned by a course or category, so this phase only ever queues targets for a
                // system-scoped job; get_user_page() itself returns nothing for a narrower category scope.
                $ids = scope::get_user_page($context, (int) $job->lastid, self::DISCOVERY_LIMIT);
                $targettype = self::TARGET_USER;
            }

            foreach ($ids as $id) {
                $this->queue_target($targettype, $id);
            }
            if ($ids) {
                // A keyset cursor avoids increasingly expensive OFFSET queries as a site grows.
                $job->lastid = end($ids);
                $job->queued += count($ids);
            } else if ($job->phase === 'categories') {
                // Categories, courses, and users use independent primary-key ranges, so reset the cursor at each phase
                // boundary.
                $job->phase = 'courses';
                $job->lastid = 0;
            } else if ($job->phase === 'courses' && $context->contextlevel === CONTEXT_SYSTEM) {
                $job->phase = 'users';
                $job->lastid = 0;
            } else {
                $job->status = 'complete';
            }
            $job->timemodified = time();
            $DB->update_record('report_imagealt_scan', $job);

            return $job->status !== 'complete';
        } finally {
            $lock->release();
        }
    }

    /**
     * Mark one course or category dirty and optionally ensure a worker exists.
     *
     * Every event advances the durable revision, but multiple events before work starts still collapse into one worker. If
     * content commits while a worker is scanning, the revision mismatch guarantees one follow-up pass over the latest state.
     *
     * @param string $targettype Target type constant.
     * @param int $targetid Course or category ID.
     * @return \stdClass Queue record.
     */
    public function queue_target(string $targettype, int $targetid): \stdClass {
        global $DB;

        $this->validate_target($targettype, $targetid);
        $lockfactory = lock_config::get_lock_factory('report_imagealt');
        $lock = $lockfactory->get_lock("queue-{$targettype}-{$targetid}", 2);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $now = time();
            $queue = $DB->get_record('report_imagealt_queue', [
                'targettype' => $targettype,
                'targetid' => $targetid,
            ]);
            if (!$queue) {
                $queue = (object) [
                    'targettype' => $targettype,
                    'targetid' => $targetid,
                    'revision' => 1,
                    'status' => 'queued',
                    'attempts' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $queue->id = $DB->insert_record('report_imagealt_queue', $queue);
            } else {
                // Increment even while queued. An event observer can run before its content transaction commits; a scan
                // may otherwise start against the old snapshot and incorrectly assume that it handled that change.
                $queue->revision++;
                $queue->timemodified = $now;
                $DB->update_record('report_imagealt_queue', $queue);
            }

            return $queue;
        } finally {
            $lock->release();
        }
    }

    /**
     * Process one persistent target revision.
     *
     * @param int $queueid Queue record ID.
     * @return bool True if a scan pass ran; false if the row was gone or its locks were unavailable.
     */
    public function process_target(int $queueid): bool {
        global $DB;

        // Hold a target-specific execution lock for the full scan. Queue updates use a different short-lived lock and remain fast.
        $lockfactory = lock_config::get_lock_factory('report_imagealt');
        $scanlock = $lockfactory->get_lock("scan-target-{$queueid}", 0);
        if (!$scanlock) {
            return false;
        }
        try {
            $queue = $DB->get_record('report_imagealt_queue', ['id' => $queueid]);
            if (!$queue) {
                return false;
            }
            $queuelock = $lockfactory->get_lock("queue-{$queue->targettype}-{$queue->targetid}", 2);
            if (!$queuelock) {
                return false;
            }
            try {
                $queue = $DB->get_record('report_imagealt_queue', ['id' => $queueid]);
                if (!$queue) {
                    return false;
                }
                $startrevision = (int) $queue->revision;
                $queue->status = 'processing';
                $queue->attempts++;
                $queue->timemodified = time();
                $DB->update_record('report_imagealt_queue', $queue);
            } finally {
                $queuelock->release();
            }

            $manager = new manager();
            if ($queue->targettype === self::TARGET_COURSE) {
                if ($DB->record_exists('course', ['id' => $queue->targetid])) {
                    $manager->scan_course(\context_course::instance($queue->targetid));
                }
            } else if ($queue->targettype === self::TARGET_CATEGORY) {
                if ($DB->record_exists('course_categories', ['id' => $queue->targetid])) {
                    $manager->scan_category(\context_coursecat::instance($queue->targetid));
                }
            } else if ($DB->record_exists('user', ['id' => $queue->targetid, 'deleted' => 0])) {
                $manager->scan_user((int) $queue->targetid);
            }

            $queuelock = $lockfactory->get_lock("queue-{$queue->targettype}-{$queue->targetid}", 2);
            if (!$queuelock) {
                return true;
            }
            try {
                // Compare and delete while holding the same short lock used by event queueing. Without this lock, an event can
                // increment the revision after the read but immediately before deletion, silently losing the dirty marker.
                $current = $DB->get_record('report_imagealt_queue', ['id' => $queueid]);
                if (!$current) {
                    return true;
                }
                if ((int) $current->revision === $startrevision) {
                    $DB->delete_records('report_imagealt_queue', ['id' => $queueid]);
                } else {
                    // Content changed mid-scan. Leave the row queued so the next drain pass reprocesses the latest revision.
                    $current->status = 'queued';
                    $current->timemodified = time();
                    $DB->update_record('report_imagealt_queue', $current);
                }
            } finally {
                $queuelock->release();
            }

            return true;
        } finally {
            $scanlock->release();
        }
    }

    /**
     * Remove old completed discovery bookkeeping while retaining active cursor state.
     */
    public function cleanup(): void {
        global $DB;

        $DB->delete_records_select(
            'report_imagealt_scan',
            'status = :complete AND timemodified < :cutoff',
            ['complete' => 'complete', 'cutoff' => time() - (30 * DAYSECS)],
        );
    }

    /**
     * Reject malformed polymorphic targets before they reach persistent task data.
     *
     * @param string $targettype Target type.
     * @param int $targetid Target ID.
     */
    private function validate_target(string $targettype, int $targetid): void {
        if (
            !in_array($targettype, [self::TARGET_COURSE, self::TARGET_CATEGORY, self::TARGET_USER], true)
                || $targetid <= 0
        ) {
            throw new \coding_exception('Invalid image alternative text scan target.');
        }
    }
}
