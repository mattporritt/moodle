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

namespace core_ai\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for core_ai implementing null_provider.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\subsystem\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            name: 'ai_action_generate_image',
            privacyfields: [
                'id' => 'privacy:metadata:ai_action_generate_image:id',
                'prompt' => 'privacy:metadata:ai_action_generate_image:prompt',
                'numberimages' => 'privacy:metadata:ai_action_generate_image:numberimages',
                'quality' => 'privacy:metadata:ai_action_generate_image:quality',
                'aspectratio' => 'privacy:metadata:ai_action_generate_image:aspectratio',
                'style' => 'privacy:metadata:ai_action_generate_image:style',
                'sourceurl' => 'privacy:metadata:ai_action_generate_image:sourceurl',
                'revisedprompt' => 'privacy:metadata:ai_action_generate_image:revisedprompt',
            ],
            summary: 'privacy:metadata:ai_action_generate_image'
        );

        $collection->add_database_table(
            name: 'ai_action_generate_text',
            privacyfields: [
                'id' => 'privacy:metadata:ai_action_generate_text:id',
                'prompt' => 'privacy:metadata:ai_action_generate_text:prompt',
                'responseid' => 'privacy:metadata:ai_action_generate_text:responseid',
                'fingerprint' => 'privacy:metadata:ai_action_generate_text:fingerprint',
                'generatedcontent' => 'privacy:metadata:ai_action_generate_text:generatedcontent',
                'finishreason' => 'privacy:metadata:ai_action_generate_text:finishreason',
                'prompttokens' => 'privacy:metadata:ai_action_generate_text:prompttokens',
                'completiontoken' => 'privacy:metadata:ai_action_generate_text:completiontoken',
            ],
            summary: 'privacy:metadata:ai_action_generate_text'
        );

        $collection->add_database_table(
            name: 'ai_action_register',
            privacyfields: [
                'actionname' => 'privacy:metadata:ai_action_register:actionname',
                'actionid' => 'privacy:metadata:ai_action_register:actionid',
                'success' => 'privacy:metadata:ai_action_register:success',
                'userid' => 'privacy:metadata:ai_action_register:userid',
                'contextid' => 'privacy:metadata:ai_action_register:contextid',
                'provider' => 'privacy:metadata:ai_action_register:provider',
                'errorcode' => 'privacy:metadata:ai_action_register:errorcode',
                'errormessage' => 'privacy:metadata:ai_action_register:errormessage',
                'timecreated' => 'privacy:metadata:ai_action_register:timecreated',
                'timecompleted' => 'privacy:metadata:ai_action_register:timecompleted',
            ],
            summary: 'privacy:metadata:ai_action_register'
        );

        $collection->add_database_table(
            name: 'ai_action_summarise_text',
            privacyfields: [
                'id' => 'privacy:metadata:ai_action_summarise_text:id',
                'prompt' => 'privacy:metadata:ai_action_summarise_text:prompt',
                'responseid' => 'privacy:metadata:ai_action_summarise_text:responseid',
                'fingerprint' => 'privacy:metadata:ai_action_summarise_text:fingerprint',
                'generatedcontent' => 'privacy:metadata:ai_action_summarise_text:generatedcontent',
                'finishreason' => 'privacy:metadata:ai_action_summarise_text:finishreason',
                'prompttokens' => 'privacy:metadata:ai_action_summarise_text:prompttokens',
                'completiontoken' => 'privacy:metadata:ai_action_summarise_text:completiontoken',
            ],
            summary: 'privacy:metadata:ai_action_summarise_text'
        );

        $collection->add_database_table(
            name: 'ai_policy_register',
            privacyfields: [
                'userid' => 'privacy:metadata:ai_policy_register:userid',
                'contextid' => 'privacy:metadata:ai_policy_register:contextid',
                'timeaccepted' => 'privacy:metadata:ai_policy_register:timeaccepted',
            ],
            summary: 'privacy:metadata:ai_policy_register'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = ['userid' => $userid];

        // Actions performed by a user.
        $sql = "SELECT contextid
                  FROM {ai_action_register}
                 WHERE userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_user::instance($contextlist->get_user()->id);

        $params = ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid];

        $sql = "SELECT ar.actionname, ar.contextid, ar.provider, ar.timecreated, ar.timecompleted,
                       agi.prompt, agi.sourceurl AS generatedcontent
                  FROM {ai_action_register} ar
            INNER JOIN {ai_action_generate_image} agi ON agi.id = ar.actionid
                 WHERE ar.userid = :userid1 AND ar.actionname = 'generate_image'
             UNION ALL
                SELECT ar.actionname, ar.contextid, ar.provider, ar.timecreated, ar.timecompleted,
                       agt.prompt, agt.generatedcontent
                  FROM {ai_action_register} ar
            INNER JOIN {ai_action_generate_text} agt ON agt.id = ar.actionid
                 WHERE ar.userid = :userid2 AND ar.actionname = 'generate_text'
             UNION ALL
                SELECT ar.actionname, ar.contextid, ar.provider, ar.timecreated, ar.timecompleted,
                       ast.prompt, ast.generatedcontent
                  FROM {ai_action_register} ar
                  JOIN {ai_action_summarise_text} ast ON ast.id = ar.actionid
                 WHERE ar.userid = :userid3 AND ar.actionname = 'summarise_text'";

        $actions = $DB->get_recordset_sql($sql, $params);
        foreach ($actions as $action) {
            $data[] = [
                'provider' => $action->provider,
                'actionname' => $action->actionname,
                'prompt' => $action->prompt,
                'generatedcontent' => $action->generatedcontent,
                'timecreated' => transform::datetime($action->timecreated),
                'timecompleted' => transform::datetime($action->timecompleted)
            ];
        }

        $subcontext = [
            get_string('ai', 'core_ai'),
        ];
        $finaldata = (object) $data;
        $context = \context::instance_by_id($action->contextid);
        writer::with_context($context)->export_data($subcontext, $finaldata);

    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        // None of the data from these tables should be deleted.
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        // None of the data from these tables should be deleted.
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $params = ['userid' => $userid];

        // Remove data from ai_action_generate_image.
        $DB->delete_records_subquery('ai_action_generate_image', 'id', 'actionid',
            'SELECT actionid from {ai_action_register} WHERE userid = :userid', $params);
        // Remove data from ai_action_generate_text.
        $DB->delete_records_subquery('ai_action_generate_text', 'id', 'actionid',
            'SELECT actionid from {ai_action_register} WHERE userid = :userid', $params);
        // Remove data from ai_action_summarise_text.
        $DB->delete_records_subquery('ai_action_summarise_text', 'id', 'actionid',
            'SELECT actionid from {ai_action_register} WHERE userid = :userid', $params);
        // Remove data from ai_action_register.
        $DB->delete_records_select('ai_action_register', 'userid = :userid', $params);
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $params = ['contextid' => $context->id];

        // Actions performed by a user.
        $sql = "SELECT userid
                  FROM {ai_action_register}
                 WHERE contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
    }
}
