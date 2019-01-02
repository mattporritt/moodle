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
 * Asyncronhous helper tests.
 *
 * @package    core_backup
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Asyncronhous helper tests.
 *
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_backup_async_helper_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Tests sending message for asynchronous backup.
     */
    public function test_send_message() {
        global $DB, $CFG, $USER;
        $this->preventResetByRollback();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        set_config('backup_async_message_users', '1', 'backup');
        set_config('backup_async_message_subject', 'Moodle {operation} completed sucessfully', 'backup');
        set_config('backup_async_message',
                'Dear {user_firstname} {user_lastname}, <br/> Your {operation} (ID: {backupid}) has completed successfully!',
                'backup');
        set_config('allowedemaildomains', 'example.com');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();  // Create a course with some availability data set.
        $user2 = $generator->create_user(array('maildisplay' => 1));
        $generator->role_assign(3, $user2->id); // Make user a teacher.

        $DB->set_field_select('message_processors', 'enabled', 0, "name <> 'email'");
        set_user_preference('message_provider_moodle_asyncbackupnotification', 'email', $user2);

        // Make the backup controller for an async backup.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_YES, backup::MODE_ASYNC, $user2->id);
        $bc->finish_ui();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $sink = $this->redirectEmails();

        // Send message
        $asynchelper = new async_helper('backup', $backupid);
        $messageid = $asynchelper->send_message();

        $emails = $sink->get_messages();
        $this->assertCount(1, $emails);
        $email = reset($emails);
        $this->assertSame($USER->email, $email->from);
        $this->assertSame($user2->email, $email->to);
        $this->assertSame('Moodle backup completed sucessfully', $email->subject);
        $this->assertNotEmpty($email->header);
        $this->assertNotEmpty($email->body);
        $this->assertRegExp('/has completed successfully/s', $email->body);
        $sink->clear();
    }
}
