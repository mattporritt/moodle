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
 * Helper functions for asynchronous backups and restores.
 *
 * @package    core
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Helper functions for asynchronous backups and restores.
 *
 * @package     core
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class async_helper  {

    /**
     * @var string $type The type of async operation.
     */
    protected $type = 'backup';

    /**
     * @var string $backupid The id of the backup or restore.
     */
    protected $backupid;

    /**
     * @var object $user The user who created the backup record.
     */
    protected $user;

    /**
     * @var object $backuprec The backup controller record from the database.
     */
    protected $backuprec;

    public function __construct($type, $id) {
        $this->type = $type;
        $this->backupid = $id;
        $this->backuprec = $this->get_backup_record($id);
        $this->user = $this->get_user();
    }

    /**
     * Given a backup id return a the record from the database.
     * We use this method rather than 'load_controller' as the controller may
     * not exist if this backup/restore has completed.
     *
     * @param int $id The backup id to get.
     * @return object $user The limited user record.
     */
    private function get_backup_record($id) {
        global $DB;

        $backuprec = $DB->get_record('backup_controllers', array('backupid' => $id), '*', MUST_EXIST);

        return $backuprec;
    }

    /**
     * Given a user id return a user record from the database.
     *
     * @param int $userid The user id to get.
     * @return object $user The limited user record.
     */
    private function get_user() {
        global $DB;

        $userid = $this->backuprec->userid;
        $user = core_user::get_user($userid, '*', MUST_EXIST);

        return $user;
    }

    /**
     * Callback for preg_replace_callback.
     * Replaces message placeholders with real values.
     *
     * @param array $matches The match array from from preg_replace_callback.
     * @return string $match The replaced string.
     */
    public function lookup_message_variables($matches) {
        $options = array(
                'operation' => $this->type,
                'backupid' => $this->backupid,
                'user_username' => $this->user->username,
                'user_email' => $this->user->email,
                'user_firstname' => $this->user->firstname,
                'user_lastname' => $this->user->lastname,
                'link' => $this->get_resource_link(),
        );

        if (array_key_exists($matches[1], $options)) {
            $match = $options[$matches[1]];
        } else {
            $match = $match;
        }

        return $match;
    }

    public function get_resource_link() {
        if ($this->backuprec->type == 'activity') {  // Get activity context.
            $context = context_module::instance($this->backuprec->itemid);
        } else { // Course or Section which have the same context getter.
            $context = context_course::instance($this->backuprec->itemid);
        }

        // Generate link based on operation type.
        if ($this->type == 'backup') {
            // For backups simply generate link to restore file area UI.
            $url = new moodle_url('/backup/restorefile.php', array('contextid' => $context->id));
        } else {
            // For restore generate link to the item itself.
            $url = $context->get_url();
        }

        return $url;
    }

    /**
     * Sends a confirmation message for an aynchronous process.
     *
     * @return int $messageid The id of the sent message.
     */
    public function send_message() {
        global $USER;

        $subjectraw = get_config('backup', 'backup_async_message_subject');
        $subjecttext = preg_replace_callback(
                '/\{([-_A-Za-z0-9]+)\}/u',
                array('async_helper', 'lookup_message_variables'),
                $subjectraw);

        $messageraw = get_config('backup', 'backup_async_message');
        $messagehtml = preg_replace_callback(
                '/\{([-_A-Za-z0-9]+)\}/u',
                array('async_helper', 'lookup_message_variables'),
                $messageraw);
        $messagetext = html_to_text($messagehtml);

        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'asyncbackupnotification';
        $message->userfrom          = $USER;
        $message->userto            = $this->user;
        $message->subject           = $subjecttext;
        $message->fullmessage       = $messagetext;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = $messagehtml;
        $message->smallmessage      = '';
        $message->notification      = '1';

        $messageid = message_send($message);

        return $messageid;
    }
}

