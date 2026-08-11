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

namespace report_imagealt;

use report_imagealt\local\scan_manager;

/**
 * Removes indexed cache rows when their owning Moodle content is deleted.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Queue a changed course for bounded background reconciliation.
     *
     * @param \core\event\base $event Course event.
     */
    public static function course_changed(\core\event\base $event): void {
        (new scan_manager())->queue_target(scan_manager::TARGET_COURSE, (int) $event->objectid);
    }

    /**
     * Queue a changed category description for background reconciliation.
     *
     * @param \core\event\base $event Category event.
     */
    public static function course_category_changed(\core\event\base $event): void {
        (new scan_manager())->queue_target(scan_manager::TARGET_CATEGORY, (int) $event->objectid);
    }

    /**
     * Queue the owning course after its section or activity content changes.
     *
     * @param \core\event\base $event Content event.
     */
    public static function course_content_changed(\core\event\base $event): void {
        if ($event->courseid > 0) {
            // Events only persist a small dirty-target row, so ordinary site activity never adds tasks to the shared ad hoc
            // queue. The process_queue scheduled task drains dirty rows directly on its next run.
            (new scan_manager())->queue_target(scan_manager::TARGET_COURSE, (int) $event->courseid);
        }
    }

    /**
     * Queue a changed user profile for background reconciliation.
     *
     * @param \core\event\base $event User event.
     */
    public static function user_changed(\core\event\base $event): void {
        (new scan_manager())->queue_target(scan_manager::TARGET_USER, (int) $event->objectid);
    }

    /**
     * Remove cached occurrences from a deleted user profile.
     *
     * @param \core\event\user_deleted $event Deletion event.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $DB->delete_records('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_USER,
            'targetid' => $event->objectid,
        ]);
        self::delete_occurrences([
            'providerkey' => 'core_user',
            'itemkeyhash' => hash('sha256', "user:{$event->objectid}"),
        ]);
    }

    /**
     * Remove all cached occurrences from a deleted course.
     *
     * @param \core\event\course_deleted $event Deletion event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_COURSE,
            'targetid' => $event->objectid,
        ]);
        self::delete_occurrences(['courseid' => $event->objectid]);
    }

    /**
     * Remove cached occurrences from a deleted category.
     *
     * @param \core\event\course_category_deleted $event Deletion event.
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event): void {
        global $DB;

        $DB->delete_records('report_imagealt_queue', [
            'targettype' => scan_manager::TARGET_CATEGORY,
            'targetid' => $event->objectid,
        ]);
        self::delete_occurrences(['categoryid' => $event->objectid]);
    }

    /**
     * Remove cached occurrences from a deleted activity.
     *
     * @param \core\event\course_module_deleted $event Deletion event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        self::delete_occurrences(['contextid' => $event->contextid]);
        self::course_content_changed($event);
    }

    /**
     * Remove the cached summary occurrence from a deleted section.
     *
     * @param \core\event\course_section_deleted $event Deletion event.
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event): void {
        self::delete_occurrences([
            'providerkey' => 'core_course',
            'itemkeyhash' => hash('sha256', "section:{$event->objectid}"),
        ]);
        self::course_content_changed($event);
    }

    /**
     * Delete suggestions before their indexed occurrence records.
     *
     * @param array $conditions Occurrence record conditions.
     */
    private static function delete_occurrences(array $conditions): void {
        global $DB;

        $where = [];
        $params = [];
        foreach ($conditions as $field => $value) {
            // Conditions originate only from the fixed observer methods above; values are always bound separately.
            $where[] = "{$field} = :cleanup{$field}";
            $params["cleanup{$field}"] = $value;
        }
        $wheresql = implode(' AND ', $where);

        $transaction = $DB->start_delegated_transaction();
        // Delete through a subquery so removing a large course does not first load every occurrence ID into PHP memory.
        $DB->delete_records_select(
            'report_imagealt_suggestion',
            "occurrenceid IN (
                SELECT cleanupoccurrence.id
                  FROM {report_imagealt_occurrence} cleanupoccurrence
                 WHERE {$wheresql}
            )",
            $params,
        );
        $DB->delete_records_select('report_imagealt_occurrence', $wheresql, $params);
        $transaction->allow_commit();
    }
}
