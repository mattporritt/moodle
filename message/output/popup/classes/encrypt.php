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
 * Class used to perform encryption related tasks for push notifications.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class encrypt {
    /**
     * Encodes a string to URL-safe Base64.
     *
     * @param string $data The data to encode.
     * @return string The URL-safe Base64 encoded string.
     */
    public function base64url_encode(string $data): string {
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
    public function base64url_decode(string $data): string {
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
    private function process_key_data(string $data, int $length): string {
        return $this->base64url_encode(
                str_pad($data, $length, "\0", STR_PAD_LEFT)
        );
    }

    /**
     * Creates an elliptic curve key pair using OpenSSL.
     * Keys are made available in both the original format and PEM format.
     *
     * The public key is exported as x and y coordinates because in
     * elliptic curve cryptography (ECC), a public key is a point
     * on an elliptic curve, which is specified by these coordinates.
     *
     * @return array An array containing the private and public keys.
     * @throws \coding_exception
     */
    private function create_ec_key_pair(): array {
        $config = [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $key = openssl_pkey_new($config);
        if (!$key) {
            throw new \coding_exception('Failed to create key pair');
        }

        openssl_pkey_export($key, $privatekeypem);  // Export the private key into a PEM-formatted string.
        $resource = openssl_pkey_get_private($privatekeypem);  // Get the private key resource.
        $details = openssl_pkey_get_details($resource);  // Get the key details.

        // Extract the public key in PEM format
        $publickeypem = $details['key'];

        // Return the keys in both the original format and PEM format
        return [
                'd' => $this->process_key_data((string) $details['ec']['d'], 32),
                'x' => $this->process_key_data((string) $details['ec']['x'], 32),
                'y' => $this->process_key_data((string) $details['ec']['y'], 32),
                'privatekeypem' => $privatekeypem,
                'publickeypem' => $publickeypem
        ];
    }


    /**
     * Serializes a public key provided as elliptic curve coordinates
     * into a serialized hexadecimal string.
     *
     * @param array $coords The array containing the coordinates.
     * @return string The serialized public key.
     */
    private function serialize_public_key(array $coords): string {
        $hexString = '04';  // The prefix indicating uncompressed form.
        // Append the x and y coordinates, padded and decoded from URL-safe Base64.
        $hexString .= str_pad(bin2hex($this->base64url_decode($coords['x'])), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(bin2hex($this->base64url_decode($coords['y'])), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    /**
     * Generates VAPID keys for push notifications.
     *
     * @return array An array containing the URL-safe Base64 encoded private and public keys.
     */
    public function generate_vapid_keys(): array {
        $keyarray = $this->create_ec_key_pair();  // Create a key pair.

        // Serialize, decode, and encode the public key.
        $binaryPublicKey = hex2bin($this->serialize_public_key($keyarray));
        $publickeybase64 = $this->base64url_encode($binaryPublicKey);

        // Decode and encode the private key.
        $binaryPrivateKey = hex2bin(
                str_pad(bin2hex($this->base64url_decode($keyarray['d'])), 64, '0', STR_PAD_LEFT)
        );
        $privatekeybase64 = $this->base64url_encode($binaryPrivateKey);

        // Return the keys.
        return [
                'privatekey' => $privatekeybase64,
                'publickey' => $publickeybase64
        ];
    }

    /**
     * Encrypts a payload using the Elliptic Curve Diffie-Hellman (ECDH) scheme.
     *
     * This function generates a local private/public key pair and derives a shared
     * secret using the client's public key. It then uses this secret to encrypt
     * a payload using AES-128-GCM.
     *
     * @param string $payload The plaintext message to encrypt.
     * @param string $publickey The client's public key in base64 format.
     * @param string $authtoken The client's authentication token in base64 format.
     *
     * @return array An associative array containing the encrypted payload and the
     *               server's local public key, both in base64 format.
     *
     * @throws \coding_exception If the provided client public key length is invalid.
     */
    public function encrypt_payload(string $payload, string $publickey, string $authtoken): array {
        // Generate a local private key for the server.
        $localprivatekey = random_bytes(32);

        // Compute the corresponding public key
        $localpublickeyey = sodium_crypto_scalarmult_base($localprivatekey);

        // Decode client's public key and authToken.
        $clientpublickey = base64_decode($publickey);
        $clientauthtoken = base64_decode($authtoken);

        // Remove the first byte (0x04) from the public key and then extract the 'x' coordinate.
        $clientpublickey = substr($clientpublickey, 1, 32);

        // Check if the key now has 64 bytes.
        if (strlen($clientpublickey) !== 32) {
            throw new \coding_exception('Invalid public key length');
        }

        // Derive a shared secret.
        $sharedSecret = sodium_crypto_scalarmult($localpublickeyey, $clientpublickey);

        // Generate a random salt.
        $salt = random_bytes(16);

        // Use HKDF to derive the encryption key.
        $encryptionKey = hash_hkdf('sha256', $sharedSecret, 16, $salt . $clientauthtoken);

        // Create a nonce for the encryption.
        $nonce = random_bytes(12);

        // Encrypt the payload.
        $cipher = openssl_encrypt(
                $payload,
                'aes-128-gcm',
                $encryptionKey,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
        );

        // Assemble the final payload (nonce + auth tag + cipher text).
        $encryptedPayload = $nonce . $tag . $cipher;

        return [
                'payload' => base64_encode($encryptedPayload),
                'localpublickey' => base64_encode($localpublickeyey)
        ];
    }
}
