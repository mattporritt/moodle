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
 * Remember-me token manager.
 *
 * Implements a split-token scheme: the browser cookie stores a selector and a
 * plaintext validator; only the SHA-256 hash of the validator is persisted in
 * the database. This limits exposure when the tokens table is read by an
 * attacker, because the hash alone cannot be used to authenticate.
 *
 * @package    core_auth
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_auth;

/**
 * Remember-me token manager.
 *
 * @package    core_auth
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remember_me_manager {
    /** Cookie name prefix; suffixed with the site's session cookie identifier. */
    private const COOKIE_PREFIX = 'MoodleRememberMe_';

    /** Selector and validator byte length before hex-encoding. */
    private const TOKEN_BYTES = 32;

    /**
     * Create a new remember-me token for a user and set the browser cookie.
     *
     * @param int $userid
     * @param int $expiryseconds Duration in seconds.
     * @param string $useragent Raw User-Agent string.
     */
    public static function create_token(int $userid, int $expiryseconds, string $useragent): void {
        global $CFG, $DB;

        $selector  = bin2hex(random_bytes(self::TOKEN_BYTES));
        $validator = bin2hex(random_bytes(self::TOKEN_BYTES));

        $record = new \stdClass();
        $record->userid      = $userid;
        $record->selector    = $selector;
        $record->token       = hash('sha256', $validator);
        $record->expiry      = time() + $expiryseconds;
        $record->useragent   = substr($useragent, 0, 1333);
        $record->lastused    = time();
        $record->timecreated = time();

        $DB->insert_record('auth_remember_me', $record);

        self::set_cookie($selector . ':' . $validator, $expiryseconds);
    }

    /**
     * Validate and rotate a remember-me token.
     *
     * Returns the user ID on success and false if the token is missing,
     * expired, or does not match. On success the old token is deleted and a
     * new one is written (both DB and cookie), so the caller must complete the
     * login before returning a response.
     *
     * @return int|false User ID on success, false otherwise.
     */
    public static function validate_and_rotate() {
        global $CFG, $DB;

        [$selector, $validator] = self::get_cookie_parts();
        if ($selector === null) {
            return false;
        }

        $record = $DB->get_record('auth_remember_me', ['selector' => $selector]);
        if (!$record) {
            self::delete_cookie();
            return false;
        }

        // Expired token — clean up.
        if ($record->expiry < time()) {
            $DB->delete_records('auth_remember_me', ['id' => $record->id]);
            self::delete_cookie();
            return false;
        }

        // Constant-time comparison to prevent timing attacks.
        if (!hash_equals($record->token, hash('sha256', $validator))) {
            // Token mismatch: potential replay of an already-rotated token.
            // Revoke all tokens for this user as a precaution.
            self::revoke_all($record->userid);
            self::delete_cookie();
            return false;
        }

        $userid = (int) $record->userid;
        $remainingseconds = $record->expiry - time();

        // Delete the consumed token before issuing the replacement.
        $DB->delete_records('auth_remember_me', ['id' => $record->id]);

        // Issue a new token with the same remaining lifetime.
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        self::create_token($userid, $remainingseconds, $useragent);

        return $userid;
    }

    /**
     * Revoke the token identified by the current browser cookie.
     */
    public static function revoke_current(): void {
        global $DB;

        [$selector] = self::get_cookie_parts();
        if ($selector !== null) {
            $DB->delete_records('auth_remember_me', ['selector' => $selector]);
        }
        self::delete_cookie();
    }

    /**
     * Revoke all remember-me tokens for a given user.
     *
     * @param int $userid
     */
    public static function revoke_all(int $userid): void {
        global $DB;
        $DB->delete_records('auth_remember_me', ['userid' => $userid]);
    }

    /**
     * Parse selector and validator from the browser cookie.
     *
     * Reads directly from $_COOKIE, which is always populated by set_cookie()
     * within the same request (including CLI/test contexts where NO_MOODLE_COOKIES
     * prevents HTTP setcookie() calls but $_COOKIE is still writeable).
     *
     * @return array Two-element array [selector|null, validator|null].
     */
    private static function get_cookie_parts(): array {
        global $CFG;

        $cookiename = self::COOKIE_PREFIX . $CFG->sessioncookie;
        if (empty($_COOKIE[$cookiename])) {
            return [null, null];
        }

        $parts = explode(':', $_COOKIE[$cookiename], 2);
        if (count($parts) !== 2 || strlen($parts[0]) === 0 || strlen($parts[1]) === 0) {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Set the remember-me cookie.
     *
     * Always updates $_COOKIE so the current request can read the new value.
     * The actual setcookie() call is skipped when NO_MOODLE_COOKIES is true
     * (e.g. in PHPUnit or CLI contexts).
     *
     * @param string $value Cookie value (selector:validator).
     * @param int $expiryseconds Seconds from now until the cookie expires.
     */
    private static function set_cookie(string $value, int $expiryseconds): void {
        global $CFG;

        $cookiename = self::COOKIE_PREFIX . $CFG->sessioncookie;

        // Always update the in-request superglobal so validate_and_rotate() can
        // read the new token in the same request (e.g. during testing or CLI).
        $_COOKIE[$cookiename] = $value;

        if (NO_MOODLE_COOKIES) {
            return;
        }

        $secure = !empty($CFG->cookiesecure);

        setcookie(
            $cookiename,
            $value,
            [
                'expires'  => time() + $expiryseconds,
                'path'     => $CFG->sessioncookiepath,
                'domain'   => $CFG->sessioncookiedomain,
                'secure'   => $secure,
                'httponly' => (bool) $CFG->cookiehttponly,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Delete the remember-me cookie from the browser.
     *
     * Always clears $_COOKIE immediately. The setcookie() expiry call is
     * skipped when NO_MOODLE_COOKIES is true.
     */
    private static function delete_cookie(): void {
        global $CFG;

        $cookiename = self::COOKIE_PREFIX . $CFG->sessioncookie;
        unset($_COOKIE[$cookiename]);

        if (NO_MOODLE_COOKIES) {
            return;
        }

        $secure = !empty($CFG->cookiesecure);

        setcookie(
            $cookiename,
            '',
            [
                'expires'  => time() - HOURSECS,
                'path'     => $CFG->sessioncookiepath,
                'domain'   => $CFG->sessioncookiedomain,
                'secure'   => $secure,
                'httponly' => (bool) $CFG->cookiehttponly,
                'samesite' => 'Lax',
            ]
        );
    }
}
