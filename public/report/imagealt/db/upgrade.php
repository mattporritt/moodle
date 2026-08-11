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

/**
 * Upgrade steps for the image alternative text report.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade report_imagealt.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_report_imagealt_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072002) {
        $scantable = new xmldb_table('report_imagealt_scan');
        $scantable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $scantable->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $scantable->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'queued');
        $scantable->add_field('phase', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'categories');
        $scantable->add_field('lastid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $scantable->add_field('queued', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $scantable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $scantable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $scantable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $scantable->add_index('context-status', XMLDB_INDEX_NOTUNIQUE, ['contextid', 'status']);
        if (!$dbman->table_exists($scantable)) {
            $dbman->create_table($scantable);
        }

        $queuetable = new xmldb_table('report_imagealt_queue');
        $queuetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $queuetable->add_field('targettype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $queuetable->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $queuetable->add_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $queuetable->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'queued');
        $queuetable->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $queuetable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $queuetable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $queuetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $queuetable->add_index('target', XMLDB_INDEX_UNIQUE, ['targettype', 'targetid']);
        $queuetable->add_index('status-time', XMLDB_INDEX_NOTUNIQUE, ['status', 'timemodified']);
        if (!$dbman->table_exists($queuetable)) {
            $dbman->create_table($queuetable);
        }

        // These indexes cover the two expensive site-report paths without indexing every selectable display field.
        $occurrencetable = new xmldb_table('report_imagealt_occurrence');
        $statusindex = new xmldb_index('status-reason', XMLDB_INDEX_NOTUNIQUE, ['status', 'reason']);
        if (!$dbman->index_exists($occurrencetable, $statusindex)) {
            $dbman->add_index($occurrencetable, $statusindex);
        }
        $analysisindex = new xmldb_index('analysis-time', XMLDB_INDEX_NOTUNIQUE, ['analysisstate', 'timeanalysed']);
        if (!$dbman->index_exists($occurrencetable, $analysisindex)) {
            $dbman->add_index($occurrencetable, $analysisindex);
        }

        // Report pages ask for the latest suggestion belonging to one user and occurrence.
        $suggestiontable = new xmldb_table('report_imagealt_suggestion');
        $latestindex = new xmldb_index(
            'occurrence-user-id',
            XMLDB_INDEX_NOTUNIQUE,
            ['occurrenceid', 'userid', 'id'],
        );
        if (!$dbman->index_exists($suggestiontable, $latestindex)) {
            $dbman->add_index($suggestiontable, $latestindex);
        }

        upgrade_plugin_savepoint(true, 2026072002, 'report', 'imagealt');
    }

    if ($oldversion < 2026072900) {
        // Previews are now served by this plugin rather than linked to a pluginfile address assembled from the
        // file's own fields, because such an address is not valid for every component the report indexes. The
        // stored addresses are of no further use, so the column holding them is replaced by the file's content
        // hash, which records that a file resolved and changes when the image itself does.
        $table = new xmldb_table('report_imagealt_occurrence');

        $previewurl = new xmldb_field('previewurl');
        if ($dbman->field_exists($table, $previewurl)) {
            $dbman->drop_field($table, $previewurl);
        }

        $previewhash = new xmldb_field('previewhash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'src');
        if (!$dbman->field_exists($table, $previewhash)) {
            $dbman->add_field($table, $previewhash);
        }

        // Ask for a site scan so existing rows fill the new column in on the next pass of the queue task, rather
        // than showing no preview until each one's content happens to change. Deliberately not done by marking
        // rows stale: the analysis state also gates the report's own selection and edit actions, so writing to it
        // here would take those away from every row until the scan caught up.
        (new \report_imagealt\local\scan_manager())->request(\context_system::instance());

        upgrade_plugin_savepoint(true, 2026072900, 'report', 'imagealt');
    }

    if ($oldversion < 2026073101) {
        // Broken images are a new classification, so every image that is one was classified as something else until
        // now and has to be looked at again.
        (new \report_imagealt\local\scan_manager())->request(\context_system::instance());

        // Anyone who has already used the report has a saved filter listing the statuses that existed when they set
        // it, which cannot include the new one. Left alone, the images this release exists to surface would be
        // filtered out for exactly the people already using the report. The saved filters are dropped so the report
        // applies its default once more, which now covers broken images; a narrower choice can be made again.
        $reportids = $DB->get_fieldset_select(
            'reportbuilder_report',
            'id',
            'source = ?',
            [\report_imagealt\reportbuilder\local\systemreports\image_alt_text::class],
        );
        if ($reportids) {
            [$insql, $inparams] = $DB->get_in_or_equal($reportids);
            $DB->delete_records_select('reportbuilder_user_filter', "reportid {$insql}", $inparams);
        }

        upgrade_plugin_savepoint(true, 2026073101, 'report', 'imagealt');
    }

    return true;
}
