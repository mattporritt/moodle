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
     * Test data for encode and decode method unit tests.
     *
     */
    public function provide_base64_test_data(): array {
        return [
            'simple text' => ['Hello, World!', 'SGVsbG8sIFdvcmxkIQ'],
            'empty string' => ['', ''],
            'key example' => [
                'clDlnYyAE_Tcj-HcZKJQyAgoGFmS1JqRMzi946djv3o',
                'Y2xEbG5ZeUFFX1Rjai1IY1pLSlF5QWdvR0ZtUzFKcVJNemk5NDZkanYzbw'
            ],
        ];
    }

    /**
     * Test base64url_encode.
     *
     * @dataProvider provide_base64_test_data
     * @param string $decoded The data encoded.
     * @param string $encoded The data decoded.
     * @covers \message_popup\push::base64url_encode
     */
    public function test_base64url_encode(string $decoded, string $encoded): void {
        $method = new \ReflectionMethod(push::class, 'base64url_encode');
        $method->setAccessible(true);

        $result = $method->invoke(null, $decoded);
        $this->assertEquals($encoded, $result);
    }

    /**
     * Test base64url_decode.
     *
     * @dataProvider provide_base64_test_data
     * @param string $decoded The data encoded.
     * @param string $encoded The data decoded.
     * @covers \message_popup\push::base64url_encode
     */
    public function test_base64url_decode(string $decoded, string $encoded): void {
        $method = new \ReflectionMethod(push::class, 'base64url_decode');
        $method->setAccessible(true);

        $result = $method->invoke(null, $encoded);
        $this->assertEquals($decoded, $result);
    }

    /**
        * Test process_key_data.
        *
        * @covers \message_popup\push::process_key_data
        */
    public function test_process_key_data(): void {
        $method = new \ReflectionMethod(push::class, 'process_key_data');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'someData', 32);

        $this->assertIsString($result);
        $this->assertEquals(43, strlen($result)); // Longer than 32 cause of Base64 encoding.
        $this->assertEquals('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAc29tZURhdGE', $result);
    }

    /**
     * Test create_ec_key_pair.
     *
     * @covers \message_popup\push::create_ec_key_pair
     */
    public function test_create_ec_key_pair() {
        $method = new \ReflectionMethod(push::class, 'create_ec_key_pair');
        $method->setAccessible(true);

        $result = $method->invoke(null);

        // Because the keys change every generation, we can't assert much apart from existence.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('d', $result);
        $this->assertArrayHasKey('x', $result);
        $this->assertArrayHasKey('y', $result);
    }

    /**
     * Data provider for test_serialize_public_key.
     */
    public function serialize_public_key_provider() {
        return [
            // Example data - replace with real examples
            'example 1' => [
                'coords' => [
                    'x' => 'xYyExagWpGX5_oORSTmdkRnQ419qaaFbS2dA7TWZRGc', // Base64url encoded x coord.
                    'y' => 'roofT6MmBZxMdsmOI5n5FohWsZzDpZDzbfYetl4Tm-g' // Base64url encoded y coord.
                ],
                'expected' => '04c58c84c5a816a465f9fe839149399d9119d0e35f6a69a15b4b6740ed35994467ae8a1f4fa326059c4c76c98e2399f9168856b19cc3a590f36df61eb65e139be8' // Expected serialized key.
            ],
            'example 2' => [
                'coords' => [
                    'x' => 'snfOzsVsQjIUZuJcnfUO1hu-oNaS-s2gNnzOVRppa1Q', // Base64url encoded x coord.
                    'y' => 'ymGHi0XsmDVSXyM5-JxTmbcgSdVohg4DY5iW-6AyWAg' // Base64url encoded y coord.
                ],
                'expected' => '04b277cecec56c42321466e25c9df50ed61bbea0d692facda0367cce551a696b54ca61878b45ec9835525f2339f89c5399b72049d568860e03639896fba0325808' // Expected serialized key.
            ],
        ];
    }

    /**
     * Test serialize_public_key.
     *
     * @dataProvider serialize_public_key_provider
     * @covers \message_popup\push::serialize_public_key
     */
    public function test_serialize_public_key($coords, $expected) {
        $pushReflection = new \ReflectionClass(push::class);
        $method = $pushReflection->getMethod('serialize_public_key');
        $method->setAccessible(true);

        $actual = $method->invoke(null, $coords);

        $this->assertStringStartsWith('04', $actual);
        $this->assertEquals(130, strlen($actual));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test generating VAPID keys.
     *
     * @covers \message_popup\push::generate_vapid_keys
     */
    public function test_generate_vapid_keys() {
        $keys = push::generate_vapid_keys();

        // Do some basic checks on the keys.
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('privatekey', $keys);
        $this->assertArrayHasKey('publickey', $keys);

        // Check if the values are strings.
        $this->assertIsString($keys['privatekey']);
        $this->assertIsString($keys['publickey']);
    }
}
