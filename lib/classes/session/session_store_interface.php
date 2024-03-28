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
 * Session handler mocking interface.
 *
 * @package    core
 * @author     Darren Cocco <moodle@darren.cocco.id.au>
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2022 Monash University (http://www.monash.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session;

interface session_store_interface {

    /**
     * Returns all session records.
     *
     * @return \Iterator
     */
    public function get_all_sessions(): \Iterator;

    /**
     * Returns a single session record for this session id.
     *
     * @param string $sid
     * @return \stdClass
     */
    public function get_session_by_sid(string $sid): \stdClass;

    /**
     * Returns all the session records for this user id.
     *
     * @param int $userid
     * @return array
     */
    public function get_sessions_by_userid(int $userid): array;

    /**
     * Insert new empty session record.
     *
     * @param int $userid
     * @return \stdClass
     */
    public function add_session(int $userid): \stdClass;

    /**
     * Update a session record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public function update_session(\stdClass $record): bool;

    /**
     * Destroy all sessions, and delete all the session data.
     *
     * @return bool
     */
    public function destroy_all(): bool;

    /**
     * Destroy a specific session and delete this session record for this session id.
     *
     * @param string $id
     * @return bool
     */
    public function destroy(string $id): bool;

    /**
     * Destroy sessions of users with disabled plugins
     *
     * @param string $pluginname
     * @return void
     */
    public function destroy_for_auth_plugin(string $pluginname): void;

    /**
     * Periodic timed-out session cleanup.
     *
     * @param int $max_lifetime Sessions that have not updated for the last max_lifetime seconds will be removed.
     * @return int|false Number of deleted sessions or false if an error occurred.
     */
    // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
    public function gc(int $max_lifetime): int|false;
}
