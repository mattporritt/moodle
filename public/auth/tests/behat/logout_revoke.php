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

// phpcs:disable moodle.Files.RequireLogin.Missing

/**
 * Behat-only helper: performs a full logout including revocation of any active
 * remember-me token, mirroring the behaviour of /login/logout.php.
 *
 * Use this helper (via the "I log out with remember me revoked" step) in tests
 * that verify the keep-me-logged-in token is correctly invalidated on logout.
 *
 * @package    core_auth
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

if (!defined('BEHAT_SITE_RUNNING') || !BEHAT_SITE_RUNNING) {
    http_response_code(403);
    die;
}

if (isloggedin() && !isguestuser()) {
    // Revoke the active remember-me token before destroying the session.
    // This mirrors /login/logout.php: the token is invalidated so the user
    // cannot be silently re-authenticated on the next page load.
    if (!empty(get_config('core', 'keeploggedin'))) {
        \core_auth\remember_me_manager::revoke_current();
    }
    require_logout();
}

redirect(new moodle_url('/'));
