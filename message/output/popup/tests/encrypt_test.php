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

use message_popup\encrypt;

/**
 * Test message popup encryption class API.
 *
 * @package message_popup
 * @category test
 * @copyright 2023 Matt Porritt <matt.porritt@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class encrypt_test extends \advanced_testcase {

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
     * @covers \message_popup\encrypt::base64url_encode
     */
    public function test_base64url_encode(string $decoded, string $encoded): void {
        $pushEncrypt = new encrypt();

        $result = $pushEncrypt->base64url_encode($decoded);
        $this->assertEquals($encoded, $result);
    }

    /**
     * Test base64url_decode.
     *
     * @dataProvider provide_base64_test_data
     * @param string $decoded The data encoded.
     * @param string $encoded The data decoded.
     * @covers \message_popup\encrypt::base64url_encode
     */
    public function test_base64url_decode(string $decoded, string $encoded): void {
        $pushEncrypt = new encrypt();

        $result = $pushEncrypt->base64url_decode($encoded);
        $this->assertEquals($decoded, $result);
    }

    /**
        * Test process_key_data.
        *
        * @covers \message_popup\encrypt::process_key_data
        */
    public function test_process_key_data(): void {
        $pushEncrypt = new encrypt();
        $method = new \ReflectionMethod($pushEncrypt, 'process_key_data');
        $method->setAccessible(true);

        $result = $method->invoke($pushEncrypt, 'someData', 32);

        $this->assertIsString($result);
        $this->assertEquals(43, strlen($result)); // Longer than 32 cause of Base64 encoding.
        $this->assertEquals('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAc29tZURhdGE', $result);
    }

    /**
     * Test create_ec_key_pair.
     *
     * @covers \message_popup\encrypt::create_ec_key_pair
     */
    public function test_create_ec_key_pair() {
        $pushEncrypt = new encrypt();
        $method = new \ReflectionMethod($pushEncrypt, 'create_ec_key_pair');
        $method->setAccessible(true);

        $result = $method->invoke($pushEncrypt);

        // Assert basic existence of vapid components.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('d', $result);
        $this->assertArrayHasKey('x', $result);
        $this->assertArrayHasKey('y', $result);

        // Validate VAPID components.
        $d = $pushEncrypt->base64url_decode($result['d']);
        $x = $pushEncrypt->base64url_decode($result['x']);
        $y = $pushEncrypt->base64url_decode($result['y']);

        $this->assertEquals(32, strlen($d));
        $this->assertEquals(32, strlen($x));
        $this->assertEquals(32, strlen($y));

        // Check PEM keys.
        $this->assertNotFalse(openssl_pkey_get_private($result['privatekeypem']));
        $this->assertNotFalse(openssl_pkey_get_public($result['publickeypem']));
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
     * @covers \message_popup\encrypt::serialize_public_key
     */
    public function test_serialize_public_key($coords, $expected) {
        $pushEncrypt = new encrypt();
        $method = new \ReflectionMethod($pushEncrypt, 'serialize_public_key');
        $method->setAccessible(true);

        $actual = $method->invoke($pushEncrypt, $coords);

        $this->assertStringStartsWith('04', $actual);
        $this->assertEquals(130, strlen($actual));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test generating VAPID keys.
     *
     * @covers \message_popup\encrypt::create_vapid_keys
     */
    public function test_create_vapid_keys() {
        $pushEncrypt = new encrypt();
        $method = new \ReflectionMethod($pushEncrypt, 'create_vapid_keys');
        $method->setAccessible(true);

        $eckeys = [
            'd' => '-VLCH8Z_Am1MxH4mrtrrJtEt8Xdzzyk1fNcayp1XEs4',
            'x' => 'nalTmdmox6zBL3G-I-3ezay2dzf-LRc0ldGG1mTJx_8',
            'y' => 'JeBsJp__zdSltNj4yHjCob9e-ebON7ZLJLA5nWceXkM'
        ];

        $keys = $method->invoke($pushEncrypt, $eckeys);

        // Do some basic checks on the keys.
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('privatekey', $keys);
        $this->assertArrayHasKey('publickey', $keys);

        // Check if the values are strings.
        $this->assertIsString($keys['privatekey']);
        $this->assertIsString($keys['publickey']);
    }

    /**
     * Test encrypt_payload.
     *
     * @covers \message_popup\encrypt::encrypt_payload
     */
    public function test_encrypt_payload() {
        $pushEncrypt = new encrypt();
        $payload = 'test data';
        $publicKey = 'BO/i04xnGunSB8JYf0CXZBYaFORQLQP0vYeakt8uc8i8EopQS751ADQDqaQpMDEbdLyc5DxCyd99rEuGeIU5Lxk=';
        $authtoken = 'FT1EyTLqMDslk1amm61utA==';

        $result = $pushEncrypt->encrypt_payload($payload, $publicKey, $authtoken);
        $this->assertEquals(32, strlen(base64_decode($result['localpublickey'])));
    }

    /**
     * Test decrypt_payload.
     *
     * @covers \message_popup\encrypt::decrypt_payload
     */
    public function test_set_encryption_keys() {
        $this->resetAfterTest();
        global $DB;

        $encrypt = new encrypt();
        $keys = $encrypt->set_encryption_keys();

        $this->assertIsArray($keys);
        $this->assertArrayHasKey('pemprivatekey', $keys);
        $this->assertArrayHasKey('pempublickey', $keys);
        $this->assertArrayHasKey('vapidprivatekey', $keys);
        $this->assertArrayHasKey('vapidpublickey', $keys);

        // Check the database for the keys.
        $dbkeys = $DB->get_records('message_pop_keys');
        $this->assertIsArray($dbkeys);
        $this->assertCount(4, $dbkeys);
    }

    /**
     * Test get_encryption_keys.
     *
     * @covers \message_popup\encrypt::get_encryption_keys
     */
    public function test_get_encryption_keys() {
        $this->resetAfterTest();

        $encrypt = new encrypt();

        // Generate keys to make sure we have them.
        $setkeys = $encrypt->set_encryption_keys();

        // Check the cache to confirm it is empty.
        $cache = \cache::make('message_popup', 'encryption_keys');
        foreach ($setkeys as $key => $value) {
            $this->assertFalse($cache->get($key));
        }

        // Get the keys and check they are as expected.
        $getkeys = $encrypt->get_encryption_keys();
        $this->assertEquals($setkeys, $getkeys);

        // Check the cache to confirm it is populated.
        foreach ($setkeys as $key => $value) {
            $this->assertEquals($value, $cache->get($key));
        }
    }

    /**
     * Test get_encryption_keys when no keys are available.
     *
     * @covers \message_popup\encrypt::get_encryption_keys
     */
    public function test_get_encryption_keys_no_keys() {
        $this->resetAfterTest();
        global $DB;

        // Delete any existing keys.
        $DB->delete_records('message_pop_keys');

        $this->expectException(\moodle_exception::class);

        $encrypt = new encrypt();
        $encrypt->get_encryption_keys();
    }

    /**
     * Test generate_signature.
     *
     * @covers \message_popup\encrypt::generate_signature
     */
    public function test_generate_signature() {
        $this->resetAfterTest();

        // Mock the dependencies.
        $encryptMock = $this->getMockBuilder(encrypt::class)
                ->onlyMethods(['get_encryption_keys'])
                ->getMock();

        // Assume the method get_encryption_keys returns a dummy pem private key
        $encryptMock->method('get_encryption_keys')
                ->willReturn(['pemprivatekey' => '<<<EOD  -----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgz31N4ApJ1NdoJotz
Z+kYkE/E4dL4gOFQVZCd7MM4zkChRANCAASzjZya4yzvIHsNNL7PFb4Wbw4RQEJI
xhYZtHo0BNlsL212ZGVLPv89H7yLqg5rxXZmdHduI/kLmKU1CBo9wTWs
-----END PRIVATE KEY-----
EOD']);

        $header = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9';
        $payload = 'eyJhdWQiOiJodHRwczpcL1wvdXBkYXRlcy5wd' .
                'XNoLnNlcnZpY2VzLm1vemlsbGEuY29tIiwiZXhwIj' .
                'oxNjk3NzUyNDgyLCJzdWIiOiJtYWlsdG86eW91ci1lbWFpbEBleGFtcGxlLmNvbSJ9';

        $encrypt = new encrypt();
        $signature = $encrypt->generate_signature($header, $payload);

        $this->assertIsString($signature);
        $this->assertGreaterThan(0, strlen($signature));
    }

    public function test_generate_jwt() {
        // Mock the dependencies.
        $encryptMock = $this->getMockBuilder(encrypt::class)
                ->onlyMethods(['generate_signature', 'get_encryption_keys'])
                ->getMock();

        // Assume the method generate_signature returns a dummy signature
        $encryptMock->method('generate_signature')
                ->willReturn('MEYCIQCP+rC45qb662zaUIomAfFPhniDgzTlWouUR4QBeolQOAIhAPAo1muWIx2+ClyVhHiyReD14Ok6Q/hV+WnrlEZk12zM');

        // Assume the method get_encryption_keys returns a dummy pem private key
        $encryptMock->method('get_encryption_keys')
                ->willReturn(['pemprivatekey' => '<<<EOD  -----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgz31N4ApJ1NdoJotz
Z+kYkE/E4dL4gOFQVZCd7MM4zkChRANCAASzjZya4yzvIHsNNL7PFb4Wbw4RQEJI
xhYZtHo0BNlsL212ZGVLPv89H7yLqg5rxXZmdHduI/kLmKU1CBo9wTWs
-----END PRIVATE KEY-----
EOD']);

        // Call the method.
        $jwt = $encryptMock->generate_jwt('https://example.com/endpoint');

        // Split the JWT into its parts.
        $jwtParts = explode('.', $jwt);

        // Assert that the JWT has three parts.
        $this->assertCount(3, $jwtParts);

        // Decode the header and payload
        $header = json_decode(base64_decode($jwtParts[0]), true);
        $payload = json_decode(base64_decode($jwtParts[1]), true);

        // Assert the header and payload contain what you expect
        $this->assertEquals('JWT', $header['typ']);
        $this->assertEquals('ES256', $header['alg']);
        $this->assertEquals('https://example.com', $payload['aud']);
    }
}
