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

namespace message_popup;

/**
 * Class used to return information to display for the message popup.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push {

    /**
     * Encodes a string to URL-safe Base64.
     *
     * @param string $data The data to encode.
     * @return string The URL-safe Base64 encoded string.
     */
    private static function base64url_encode(string $data): string {
        // Convert to Base64 and then replace '+' with '-' and '/' with '_'.
        $encoded = strtr(base64_encode($data), '+/', '-_');

        // Trim any padding characters and return.
        return rtrim($encoded, '=');
    }

    /**
     * Decodes a URL-safe Base64 string.
     *
     * @param string $data The data to decode.
     * @return string The decoded string.
     */
    private static function base64url_decode(string $data): string {
        // Replace '-' with '+' and '_' with '/' then decode from Base64.
        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    /**
     * Pads, encodes, and ensures the length of a string.
     *
     * @param string $data The data to process.
     * @param int $length The desired length of the string before base64 encoding.
     * @return string The processed string.
     */
    private static function process_key_data(string $data, int $length): string {
        return self::base64url_encode(
                str_pad($data, $length, "\0", STR_PAD_LEFT)
        );
    }

    /**
     * Creates an elliptic curve key pair using OpenSSL.
     *
     *  The public key is exported as x and y coordinates because in
     *  elliptic curve cryptography (ECC), a public key is a point
     *  on an elliptic curve, which is specified by these coordinates.
     *
     * @return array An array containing the private and public keys.
     * @throws \coding_exception
     */
    private static function create_ec_key_pair(): array {
        $config = [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $key = openssl_pkey_new($config);
        if (!$key) {
            throw new \coding_exception('Failed to create key pair');
        }

        openssl_pkey_export($key, $out);  // Export the key into a string.
        $resource = openssl_pkey_get_private($out);  // Get the private key resource.
        $details = openssl_pkey_get_details($resource);  // Get the key details.

        // Return the private and public keys, ensuring they are padded correctly.
        return [
                'd' => self::process_key_data((string) $details['ec']['d'], 32),
                'x' => self::process_key_data((string) $details['ec']['x'], 32),
                'y' => self::process_key_data((string) $details['ec']['y'], 32),
        ];
    }

    /**
     * Serializes a public key provided as elliptic curve coordinates
     * into a serialized hexadecimal string.
     *
     * @param array $coords The array containing the coordinates.
     * @return string The serialized public key.
     */
    private static function serialize_public_key(array $coords): string {
        $hexString = '04';  // The prefix indicating uncompressed form.
        // Append the x and y coordinates, padded and decoded from URL-safe Base64.
        $hexString .= str_pad(bin2hex(self::base64url_decode($coords['x'])), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(bin2hex(self::base64url_decode($coords['y'])), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    /**
     * Generates VAPID keys for push notifications.
     *
     * @return array An array containing the URL-safe Base64 encoded private and public keys.
     */
    public static function generate_vapid_keys(): array {
        $keyarray = self::create_ec_key_pair();  // Create a key pair.

        // Serialize, decode, and encode the public key.
        $binaryPublicKey = hex2bin(self::serialize_public_key($keyarray));
        $publickeybase64 = self::base64url_encode($binaryPublicKey);

        // Decode and encode the private key.
        $binaryPrivateKey = hex2bin(
                str_pad(bin2hex(self::base64url_decode($keyarray['d'])), 64, '0', STR_PAD_LEFT)
        );
        $privatekeybase64 = self::base64url_encode($binaryPrivateKey);

        // Return the keys.
        return [
                'privatekey' => $privatekeybase64,
                'publickey' => $publickeybase64
        ];
    }

    /**
     * Register a push subscription for a user.
     *
     * @param int $userid The user id.
     * @param array $subscription The subscription details.
     * @return bool True if successful, false otherwise.
     */
    public static function register_push_subscription(int $userid, array $subscription): bool {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->endpoint = $subscription['endpoint'];
        $record->p256dh = $subscription['keys']['p256dh'];
        $record->auth = $subscription['keys']['auth'];
        $record->timecreated = time();

        return $DB->insert_record('message_popup_subscriptions', $record, false);
    }
}
