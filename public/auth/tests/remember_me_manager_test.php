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
 * Tests for core_auth\remember_me_manager.
 *
 * @package    core_auth
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_auth\remember_me_manager
 */
namespace core_auth;

use advanced_testcase;

/**
 * Tests for core_auth\remember_me_manager.
 *
 * @package    core_auth
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remember_me_manager_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Ensure the feature is enabled.
        set_config('keeploggedin', 1);
        set_config('keeploggedinexpire', WEEKSECS);

        // Ensure the session cookie identifier is set so the cookie name can be built.
        global $CFG;
        if (empty($CFG->sessioncookie)) {
            $CFG->sessioncookie = 'test';
        }
        if (empty($CFG->sessioncookiepath)) {
            $CFG->sessioncookiepath = '/';
        }
        if (empty($CFG->sessioncookiedomain)) {
            $CFG->sessioncookiedomain = '';
        }
        if (!isset($CFG->cookiehttponly)) {
            $CFG->cookiehttponly = true;
        }
        if (!isset($CFG->cookiesecure)) {
            $CFG->cookiesecure = false;
        }
    }

    /**
     * Creating a token inserts a record in auth_remember_me.
     *
     * @covers ::create_token
     */
    public function test_create_token_inserts_record(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        remember_me_manager::create_token($user->id, WEEKSECS, 'TestAgent/1.0');

        $records = $DB->get_records('auth_remember_me', ['userid' => $user->id]);
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertEquals($user->id, $record->userid);
        $this->assertNotEmpty($record->selector);
        $this->assertNotEmpty($record->token);
        $this->assertGreaterThan(time(), $record->expiry);
        $this->assertEquals('TestAgent/1.0', $record->useragent);
    }

    /**
     * Validate_and_rotate returns the correct user ID and rotates the token.
     *
     * @covers ::validate_and_rotate
     */
    public function test_validate_and_rotate_returns_userid_and_rotates(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        remember_me_manager::create_token($user->id, WEEKSECS, 'TestAgent/1.0');

        // Grab the cookie value that was set in $_COOKIE by create_token.
        $cookiename = remember_me_manager::COOKIE_PREFIX . $GLOBALS['CFG']->sessioncookie;
        $this->assertArrayHasKey($cookiename, $_COOKIE);

        $oldselector = explode(':', $_COOKIE[$cookiename])[0];
        $oldtoken = $DB->get_record('auth_remember_me', ['selector' => $oldselector])->token;

        $userid = remember_me_manager::validate_and_rotate();

        $this->assertEquals($user->id, $userid);

        // Old record must be gone.
        $this->assertFalse($DB->get_record('auth_remember_me', ['selector' => $oldselector]));

        // A new record must exist for the same user with a different selector.
        $newcookieval = $_COOKIE[$cookiename];
        $newselector  = explode(':', $newcookieval)[0];
        $this->assertNotEquals($oldselector, $newselector);
        $newrecord = $DB->get_record('auth_remember_me', ['selector' => $newselector]);
        $this->assertNotFalse($newrecord);
        $this->assertEquals($user->id, $newrecord->userid);
    }

    /**
     * Validate_and_rotate returns false when no cookie is present.
     *
     * @covers ::validate_and_rotate
     */
    public function test_validate_and_rotate_returns_false_without_cookie(): void {
        $result = remember_me_manager::validate_and_rotate();
        $this->assertFalse($result);
    }

    /**
     * Validate_and_rotate returns false and revokes all tokens when a mismatched validator is presented.
     *
     * @covers ::validate_and_rotate
     */
    public function test_validate_and_rotate_revokes_all_on_mismatch(): void {
        global $DB, $CFG;

        $user = $this->getDataGenerator()->create_user();

        remember_me_manager::create_token($user->id, WEEKSECS, 'TestAgent/1.0');

        // Tamper with the validator portion of the cookie.
        $cookiename = remember_me_manager::COOKIE_PREFIX . $CFG->sessioncookie;
        [$selector] = explode(':', $_COOKIE[$cookiename]);
        $_COOKIE[$cookiename] = $selector . ':' . str_repeat('0', 64);

        $result = remember_me_manager::validate_and_rotate();

        $this->assertFalse($result);
        $this->assertEquals(0, $DB->count_records('auth_remember_me', ['userid' => $user->id]));
    }

    /**
     * Validate_and_rotate returns false and removes expired tokens.
     *
     * @covers ::validate_and_rotate
     */
    public function test_validate_and_rotate_rejects_expired_token(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        // Create a token that is already expired.
        remember_me_manager::create_token($user->id, -HOURSECS, 'TestAgent/1.0');

        $result = remember_me_manager::validate_and_rotate();

        $this->assertFalse($result);
        // Expired record should be cleaned up.
        $this->assertEquals(0, $DB->count_records('auth_remember_me', ['userid' => $user->id]));
    }

    /**
     * Revoke_current removes the token matching the current cookie and clears the cookie.
     *
     * @covers ::revoke_current
     */
    public function test_revoke_current_removes_token(): void {
        global $DB, $CFG;

        $user = $this->getDataGenerator()->create_user();

        remember_me_manager::create_token($user->id, WEEKSECS, 'TestAgent/1.0');

        $this->assertEquals(1, $DB->count_records('auth_remember_me', ['userid' => $user->id]));

        remember_me_manager::revoke_current();

        $this->assertEquals(0, $DB->count_records('auth_remember_me', ['userid' => $user->id]));

        $cookiename = remember_me_manager::COOKIE_PREFIX . $CFG->sessioncookie;
        $this->assertArrayNotHasKey($cookiename, $_COOKIE);
    }

    /**
     * Revoke_all removes all tokens for the given user.
     *
     * @covers ::revoke_all
     */
    public function test_revoke_all_removes_all_user_tokens(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        // Create multiple tokens for the same user.
        remember_me_manager::create_token($user->id, WEEKSECS, 'Agent/1.0');
        // Unset cookie so next create_token call doesn't read the previous cookie.
        $cookiename = remember_me_manager::COOKIE_PREFIX . $GLOBALS['CFG']->sessioncookie;
        unset($_COOKIE[$cookiename]);
        remember_me_manager::create_token($user->id, WEEKSECS, 'Agent/2.0');

        $this->assertEquals(2, $DB->count_records('auth_remember_me', ['userid' => $user->id]));

        remember_me_manager::revoke_all($user->id);

        $this->assertEquals(0, $DB->count_records('auth_remember_me', ['userid' => $user->id]));
    }

    /**
     * Revoke_all only removes tokens for the specified user, not others.
     *
     * @covers ::revoke_all
     */
    public function test_revoke_all_does_not_affect_other_users(): void {
        global $DB, $CFG;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        remember_me_manager::create_token($user1->id, WEEKSECS, 'Agent/1.0');
        $cookiename = remember_me_manager::COOKIE_PREFIX . $CFG->sessioncookie;
        unset($_COOKIE[$cookiename]);
        remember_me_manager::create_token($user2->id, WEEKSECS, 'Agent/2.0');

        remember_me_manager::revoke_all($user1->id);

        $this->assertEquals(0, $DB->count_records('auth_remember_me', ['userid' => $user1->id]));
        $this->assertEquals(1, $DB->count_records('auth_remember_me', ['userid' => $user2->id]));
    }
}
