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

/**
 * A scheduled task.
 *
 * @package    core
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

/**
 * Simple task to delete old, fully-read private messages.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_cleanup_task extends scheduled_task {
    /** @var int Maximum number of eligible message ids fetched and deleted per batch. */
    public const BATCH_SIZE = 1000;

    /** @var int Maximum number of seconds this task will spend deleting messages in a single run. */
    public const MAX_RUNTIME = 300;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskmessagecleanup', 'admin');
    }

    /**
     * Delete messages older than the configured message lifetime, but only once they have
     * been read by every recipient of the conversation. Unread messages are never deleted.
     */
    public function execute() {
        global $CFG, $DB;

        if (empty($CFG->messaginglifetime)) {
            return;
        }

        $lifetime = time() - ($CFG->messaginglifetime * DAYSECS);
        $starttime = time();
        $totaldeleted = 0;

        // A message's recipients are every conversation member other than the sender, except in
        // a self-conversation, where the sender is also the sole recipient of their own message
        // and must have a "read" action recorded against it like any other recipient. A message
        // is eligible for deletion once every recipient has read it; unread messages are always
        // retained. Expressed as a join/aggregate anti-join rather than a correlated NOT EXISTS
        // subquery, so the database can evaluate it as a single set-based operation instead of
        // once per candidate row, which matters on the large historical backlogs this task
        // targets.
        $sql = "SELECT m.id
                  FROM {messages} m
                  JOIN {message_conversations} mc ON mc.id = m.conversationid
                  JOIN {message_conversation_members} mcm
                    ON mcm.conversationid = m.conversationid
                   AND (mc.type = :selftype OR mcm.userid <> m.useridfrom)
             LEFT JOIN {message_user_actions} mua
                    ON mua.messageid = m.id
                   AND mua.userid = mcm.userid
                   AND mua.action = :readaction
                 WHERE m.timecreated < :lifetime
              GROUP BY m.id
                HAVING SUM(CASE WHEN mua.id IS NULL THEN 1 ELSE 0 END) = 0
              ORDER BY m.id ASC";
        $params = [
            'lifetime' => $lifetime,
            'readaction' => \core_message\api::MESSAGE_ACTION_READ,
            'selftype' => \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF,
        ];

        do {
            $messageids = array_keys($DB->get_records_sql($sql, $params, 0, self::BATCH_SIZE));
            if (empty($messageids)) {
                break;
            }

            [$insql, $inparams] = $DB->get_in_or_equal($messageids);
            $DB->delete_records_select('message_user_actions', "messageid $insql", $inparams);
            $DB->delete_records_select('messages', "id $insql", $inparams);

            $totaldeleted += count($messageids);
        } while (time() < $starttime + self::MAX_RUNTIME);

        mtrace("    Deleted $totaldeleted old message(s) that had been read by all recipients.");
    }
}
