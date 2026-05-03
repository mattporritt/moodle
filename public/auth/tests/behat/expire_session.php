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
 * Behat-only helper: simulates a session timeout by terminating the server-side
 * session without revoking any remember-me token.
 *
 * After visiting this page the browser still holds a valid remember-me cookie.
 * The next call to require_login() on a protected page will silently re-authenticate
 * the user via the cookie, exercising the keep-me-logged-in re-auth flow.
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

// Terminate the server-side session without touching the remember-me token.
// require_logout() destroys the session (and prevents the shutdown handler from
// re-saving it) but does NOT call remember_me_manager::revoke_current() — that
// is only done in /login/logout.php (the user-facing logout page).
if (isloggedin() && !isguestuser()) {
    require_logout();
}

// Redirect to the login page (which does not call require_login itself), so the
// browser lands on a neutral page with both cookies still present. The test then
// navigates to a protected page to trigger the silent re-authentication.
redirect(new moodle_url('/login/index.php'));
