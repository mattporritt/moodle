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
 * Lock factory which never grants a lock.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2026 - present, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */

namespace mod_bigbluebuttonbn;

use core\lock\lock;
use core\lock\lock_factory;

/**
 * Lock factory which never grants a lock.
 *
 * Used to simulate a concurrent request already holding the meeting creation lock, without
 * relying on the locking semantics of any particular database backend.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2026 - present, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class unavailable_lock_factory implements lock_factory {
    /**
     * Create this lock factory.
     *
     * @param string $type The type, e.g. cron, cache, session
     */
    public function __construct($type) {
    }

    /**
     * Is this lock factory available.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * This lock factory supports timeouts.
     *
     * @return bool
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * This lock factory does not support auto release.
     *
     * @return bool
     */
    public function supports_auto_release() {
        return false;
    }

    /**
     * Never grant a lock.
     *
     * @param string $resource The identifier for the lock
     * @param int $timeout The number of seconds to wait for a lock before giving up
     * @param int $maxlifetime The number of seconds to wait before reclaiming a stale lock
     * @return bool Always false
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        return false;
    }

    /**
     * Release a lock that was previously obtained with get_lock.
     *
     * @param lock $lock A lock obtained from this factory
     * @return bool
     */
    public function release_lock(lock $lock) {
        return true;
    }
}
