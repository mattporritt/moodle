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

namespace core\task;

use core_message\tests\helper as testhelper;

/**
 * Unit tests for the message_cleanup_task scheduled task.
 *
 * @package   core
 * @category  test
 * @copyright 2026 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(message_cleanup_task::class)]
final class message_cleanup_task_test extends \advanced_testcase {
    /** @var int Number of seconds in a day, used to backdate test messages. */
    private const DAYSECS = 86400;

    /**
     * With the setting left at its default (disabled), no messages are deleted regardless of age.
     */
    public function test_execute_default_no_config(): void {
        global $DB;

        $this->resetAfterTest();

        $userfrom = $this->getDataGenerator()->create_user();
        $userto = $this->getDataGenerator()->create_user();

        $old = testhelper::send_fake_message($userfrom, $userto, 'Old message', 0, time() - (400 * self::DAYSECS));
        $message = $DB->get_record('messages', ['id' => $old]);
        \core_message\api::mark_message_as_read($userto->id, $message);

        $task = new message_cleanup_task();
        $task->execute();

        $this->assertTrue($DB->record_exists('messages', ['id' => $old]));
    }

    /**
     * Only messages older than the configured lifetime, and read by every other conversation
     * member, are deleted. Unread and recent messages are always retained.
     */
    public function test_execute_deletes_only_old_fully_read_messages(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->messaginglifetime = 30;

        $userfrom = $this->getDataGenerator()->create_user();
        $userread = $this->getDataGenerator()->create_user();
        $userunread = $this->getDataGenerator()->create_user();

        // Old message, read by the recipient: must be deleted.
        $oldreadid = testhelper::send_fake_message($userfrom, $userread, 'Old read message', 0, time() - (40 * self::DAYSECS));
        $oldread = $DB->get_record('messages', ['id' => $oldreadid]);
        \core_message\api::mark_message_as_read($userread->id, $oldread);

        // Old message, never read by the recipient: must be retained.
        $oldunreadid = testhelper::send_fake_message(
            $userfrom,
            $userunread,
            'Old unread message',
            0,
            time() - (40 * self::DAYSECS)
        );

        // Recent message, already read: must be retained because it is not old enough yet.
        $recentreadid = testhelper::send_fake_message(
            $userfrom,
            $userread,
            'Recent read message',
            0,
            time() - (5 * self::DAYSECS)
        );
        $recentread = $DB->get_record('messages', ['id' => $recentreadid]);
        \core_message\api::mark_message_as_read($userread->id, $recentread);

        $task = new message_cleanup_task();
        $this->expectOutputRegex('/Deleted 1 old message/');
        $task->execute();

        $this->assertFalse($DB->record_exists('messages', ['id' => $oldreadid]));
        $this->assertFalse($DB->record_exists('message_user_actions', ['messageid' => $oldreadid]));
        $this->assertTrue($DB->record_exists('messages', ['id' => $oldunreadid]));
        $this->assertTrue($DB->record_exists('messages', ['id' => $recentreadid]));
        $this->assertTrue($DB->record_exists('message_user_actions', ['messageid' => $recentreadid]));
    }

    /**
     * A group conversation message is only deleted once every other member has read it.
     */
    public function test_execute_group_conversation_requires_all_recipients_to_have_read(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->messaginglifetime = 30;

        $sender = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $nonreader = $this->getDataGenerator()->create_user();

        $conversation = \core_message\api::create_conversation(
            \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP,
            [$sender->id, $reader->id, $nonreader->id]
        );

        $messageid = testhelper::send_fake_message_to_conversation(
            $sender,
            $conversation->id,
            'Group message',
            time() - (40 * self::DAYSECS)
        );
        $message = $DB->get_record('messages', ['id' => $messageid]);

        // Only one of the two recipients has read the message.
        \core_message\api::mark_message_as_read($reader->id, $message);

        $task = new message_cleanup_task();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertTrue($DB->record_exists('messages', ['id' => $messageid]));

        // Once the remaining recipient also reads it, it becomes eligible for deletion.
        \core_message\api::mark_message_as_read($nonreader->id, $message);
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertFalse($DB->record_exists('messages', ['id' => $messageid]));
    }

    /**
     * A self-conversation ("message yourself") message must never be treated as read-by-default.
     * The sender is the conversation's only member, so they are also its sole recipient and must
     * have their own "read" action recorded before the message becomes eligible for deletion.
     */
    public function test_execute_self_conversation_message_requires_own_read_action(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->messaginglifetime = 30;

        $user = $this->getDataGenerator()->create_user();
        $conversation = \core_message\api::get_self_conversation($user->id);

        $unreadid = testhelper::send_fake_message_to_conversation(
            $user,
            $conversation->id,
            'Old self message, never read',
            time() - (40 * self::DAYSECS)
        );

        $readid = testhelper::send_fake_message_to_conversation(
            $user,
            $conversation->id,
            'Old self message, read',
            time() - (40 * self::DAYSECS)
        );
        $read = $DB->get_record('messages', ['id' => $readid]);
        \core_message\api::mark_message_as_read($user->id, $read);

        $task = new message_cleanup_task();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertTrue(
            $DB->record_exists('messages', ['id' => $unreadid]),
            'An unread self-conversation message must never be automatically deleted.'
        );
        $this->assertFalse($DB->record_exists('messages', ['id' => $readid]));
    }
}
