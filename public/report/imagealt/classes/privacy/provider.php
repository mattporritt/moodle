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

namespace report_imagealt\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for indexed occurrences and unpublished suggestions.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('report_imagealt_occurrence', [
            'contextid' => 'privacy:metadata:occurrence:contextid',
            'itemname' => 'privacy:metadata:occurrence:itemname',
            'src' => 'privacy:metadata:occurrence:src',
            'alttext' => 'privacy:metadata:occurrence:alttext',
        ], 'privacy:metadata:occurrence');
        $collection->add_database_table('report_imagealt_batch', [
            'contextid' => 'privacy:metadata:batch:contextid',
            'userid' => 'privacy:metadata:batch:userid',
            'status' => 'privacy:metadata:batch:status',
            'timecreated' => 'privacy:metadata:batch:timecreated',
        ], 'privacy:metadata:batch');
        $collection->add_database_table('report_imagealt_suggestion', [
            'userid' => 'privacy:metadata:suggestion:userid',
            'status' => 'privacy:metadata:suggestion:status',
            'suggestion' => 'privacy:metadata:suggestion:suggestion',
            'errormessage' => 'privacy:metadata:suggestion:errormessage',
            'timecreated' => 'privacy:metadata:suggestion:timecreated',
        ], 'privacy:metadata:suggestion');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT contextid FROM {report_imagealt_batch} WHERE userid = :batchuserid
             UNION
             SELECT occurrence.contextid
               FROM {report_imagealt_suggestion} suggestion
               JOIN {report_imagealt_occurrence} occurrence ON occurrence.id = suggestion.occurrenceid
              WHERE suggestion.userid = :suggestionuserid
             UNION
             SELECT contextid
               FROM {report_imagealt_occurrence}
              WHERE providerkey = :ownerprovider AND itemkeyhash = :owneritemhash',
            [
                'batchuserid' => $userid,
                'suggestionuserid' => $userid,
                'ownerprovider' => 'core_user',
                'owneritemhash' => hash('sha256', "user:{$userid}"),
            ],
        );
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {report_imagealt_batch} WHERE contextid = :batchcontextid
             UNION
             SELECT suggestion.userid
               FROM {report_imagealt_suggestion} suggestion
               JOIN {report_imagealt_occurrence} occurrence ON occurrence.id = suggestion.occurrenceid
              WHERE occurrence.contextid = :suggestioncontextid',
            [
                'batchcontextid' => $userlist->get_context()->id,
                'suggestioncontextid' => $userlist->get_context()->id,
            ],
        );

        // A user profile occurrence's owner is the context's own instance ID, not a stored column, so it is added directly
        // rather than through the SQL union above.
        $context = $userlist->get_context();
        if (
            $context->contextlevel === CONTEXT_USER
                && $DB->record_exists('report_imagealt_occurrence', [
                    'contextid' => $context->id,
                    'providerkey' => 'core_user',
                ])
        ) {
            $userlist->add_user((int) $context->instanceid);
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $batches = $DB->get_records('report_imagealt_batch', [
                'contextid' => $context->id,
                'userid' => $userid,
            ]);
            $suggestions = $DB->get_records_sql(
                'SELECT suggestion.*
                   FROM {report_imagealt_suggestion} suggestion
                   JOIN {report_imagealt_occurrence} occurrence ON occurrence.id = suggestion.occurrenceid
                  WHERE occurrence.contextid = :contextid AND suggestion.userid = :userid',
                ['contextid' => $context->id, 'userid' => $userid],
            );
            $ownoccurrences = [];
            if ((int) $context->contextlevel === CONTEXT_USER && (int) $context->instanceid === $userid) {
                $ownoccurrences = $DB->get_records('report_imagealt_occurrence', [
                    'contextid' => $context->id,
                    'providerkey' => 'core_user',
                ]);
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_imagealt')],
                (object) [
                    'batches' => array_values($batches),
                    'suggestions' => array_values($suggestions),
                    'occurrences' => array_values($ownoccurrences),
                ],
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $batchids = $DB->get_fieldset_select('report_imagealt_batch', 'id', 'contextid = :contextid', [
            'contextid' => $context->id,
        ]);
        self::delete_suggestions_for_batches($batchids);
        $DB->delete_records('report_imagealt_batch', ['contextid' => $context->id]);
        $DB->delete_records_select(
            'report_imagealt_suggestion',
            'occurrenceid IN (SELECT id FROM {report_imagealt_occurrence} WHERE contextid = :contextid)',
            ['contextid' => $context->id]
        );

        // Course, category, and activity occurrence rows describe shared content rather than one person's data, so they
        // are retained above. A user profile context is different: the occurrence row there only ever describes that
        // context's own owner, so it is personal data and must be erased with the rest of this context.
        if ($context->contextlevel === CONTEXT_USER) {
            $DB->delete_records('report_imagealt_occurrence', [
                'contextid' => $context->id,
                'providerkey' => 'core_user',
            ]);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::delete_for_userids($contextlist->get_contextids(), [$contextlist->get_user()->id]);
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_for_userids([$userlist->get_context()->id], $userlist->get_userids());
    }

    /**
     * Delete user-owned records within approved contexts.
     *
     * @param int[] $contextids Context IDs.
     * @param int[] $userids User IDs.
     */
    private static function delete_for_userids(array $contextids, array $userids): void {
        global $DB;

        if (!$contextids || !$userids) {
            return;
        }
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'context');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $params = $contextparams + $userparams;
        $batchids = $DB->get_fieldset_select(
            'report_imagealt_batch',
            'id',
            "contextid {$contextsql} AND userid {$usersql}",
            $params
        );
        self::delete_suggestions_for_batches($batchids);
        $DB->delete_records_select('report_imagealt_batch', "contextid {$contextsql} AND userid {$usersql}", $params);
        $DB->delete_records_select(
            'report_imagealt_suggestion',
            "userid {$usersql} AND occurrenceid IN
                (SELECT id FROM {report_imagealt_occurrence} WHERE contextid {$contextsql})",
            $params
        );

        // A user profile occurrence is only personal data of the profile owner, so erase it directly for each requested
        // user rather than by context membership, which the earlier deletes above are keyed on instead.
        foreach ($userids as $userid) {
            [$itemhashsql, $itemhashparams] = $DB->get_in_or_equal(
                [hash('sha256', "user:{$userid}")],
                SQL_PARAMS_NAMED,
                'ownerhash',
            );
            $ownerparams = $itemhashparams + ['ownerprovider' => 'core_user'];
            $DB->delete_records_select(
                'report_imagealt_suggestion',
                "occurrenceid IN (
                    SELECT id FROM {report_imagealt_occurrence}
                     WHERE providerkey = :ownerprovider AND itemkeyhash {$itemhashsql}
                )",
                $ownerparams,
            );
            $DB->delete_records_select(
                'report_imagealt_occurrence',
                "providerkey = :ownerprovider AND itemkeyhash {$itemhashsql}",
                $ownerparams,
            );
        }
    }

    /**
     * Delete suggestions before their parent batches.
     *
     * @param int[] $batchids Batch IDs.
     */
    private static function delete_suggestions_for_batches(array $batchids): void {
        global $DB;

        if ($batchids) {
            $DB->delete_records_list('report_imagealt_suggestion', 'batchid', $batchids);
        }
    }
}
