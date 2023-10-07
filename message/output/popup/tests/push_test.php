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

use message_popup\push;

/**
 * Test message popup API.
 *
 * @package message_popup
 * @category test
 * @copyright 2016 Ryan Wyllie <ryan@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_test extends \advanced_testcase {

    /**
     * Test generating VAPID keys.
     */
    public function test_generate_vapid_keys() {
        $keys = push::generate_vapid_keys();

        // Do some basic checks on the keys.
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('privatekey', $keys);
        $this->assertArrayHasKey('publickey', $keys);

        // Test base64 decoding and length (64 bytes for private key, 32 bytes for public key.
        $this->assertEquals(64, strlen(base64_decode($keys['privatekey'])));
        $this->assertEquals(32, strlen(base64_decode($keys['publickey'])));
    }
}
