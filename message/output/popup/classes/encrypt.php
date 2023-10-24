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

    public const CONTENT_ENCODING = 'aes128gcm';

    private const ASN1_LENGTH_2BYTES = '81';
    private const ASN1_SEQUENCE = '30';
    private const ASN1_INTEGER = '02';
    private const ASN1_MAX_SINGLE_BYTE = 128;
    private const ASN1_BIG_INTEGER_LIMIT = '7f';
    private const ASN1_NEGATIVE_INTEGER = '00';
    private const BYTE_SIZE = 2;

    /**
     * Encodes a string to URL-safe Base64.
     *
     * @param string $data The data to encode.
     * @return string The URL-safe Base64 encoded string.
     */
    public function base64url_encode(string $data, bool $trimpadding = true): string {
        // Convert to Base64 and then replace '+' with '-' and '/' with '_'.
        $encoded = strtr(base64_encode($data), '+/', '-_');

        // Trim any padding characters and return.
        if ($trimpadding) {
            return rtrim($encoded, '=');
        } else {
            return $encoded;
        }
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

    public static function payload_pad(string $payload, int $maxpadlength): string {
        $payloadlength = mb_strlen($payload, '8bit');
        $padlength = $maxpadlength ? $maxpadlength - $payloadlength : 0;

        return str_pad($payload.chr(2), $padlength + $payloadlength, chr(0), STR_PAD_RIGHT);
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
     * Creates VAPID keys for push notifications.
     *
     * @param array $eckeys The elliptic curve key pair.
     * @return array An array containing the URL-safe Base64 encoded private and public keys.
     */
    private function create_vapid_keys(array $eckeys): array {
        // Serialize, decode, and encode the public key.
        $binaryPublicKey = hex2bin($this->serialize_public_key($eckeys));
        $publickeybase64 = $this->base64url_encode($binaryPublicKey);

        // Decode and encode the private key.
        $binaryPrivateKey = hex2bin(
                str_pad(bin2hex($this->base64url_decode($eckeys['d'])), 64, '0', STR_PAD_LEFT)
        );
        $privatekeybase64 = $this->base64url_encode($binaryPrivateKey);

        // Return the keys.
        return [
            'privatekey' => $privatekeybase64,
            'publickey' => $publickeybase64,
        ];
    }

    /**
     * Get the EC and VAPID Keys.
     * First try to get the values from cache, if not then get from database.
     *
     * Cache is updated if empty.
     *
     * @return array $keys An array containing the EC and VAPID keys.
     */
    public function get_encryption_keys():array {
        global $DB;

        // First try to get keys from cache.
        $keys = [
            'pemprivatekey' => '',
            'pempublickey' => '',
            'vapidprivatekey' => '',
            'vapidpublickey' => ''
        ];
        $dbfetch = false;

        $cache = \cache::make('message_popup', 'encryption_keys');

        foreach ($keys as $key) {
            $value = $cache->get($key);
            if ($value) {
                $keys[$key] = $value;
            } else {
                $dbfetch = true;
                break;
            }
        }

        // If cache is empty, then get keys from database and update cache.
        if ($dbfetch) {
            $records = $DB->get_records('message_pop_keys');
            if (empty($records)) {
                throw new \moodle_exception(
                     'No encryption keys found set keys before getting',
                     'message_popup'
                );
            }

            foreach ($records as $record) {
                $keys[$record->keyname] = $record->keyvalue;
                $cache->set($record->keyname, $record->keyvalue);
            }
        }

        return $keys;
    }

    /**
     * Generate the EC and VAPID keys and store them in the database.
     * Keys are returned for reference.
     *
     * @return array An array containing the EC and VAPID keys.
     */
    public function set_encryption_keys():array {
        global $DB;

        // First create the EC keypair.
        $eckeys = $this->create_ec_key_pair();

        // Next create the VAPID keys.
        $vapidkeys = $this->create_vapid_keys($eckeys);

        // Store the keys in the database.
        $now = time();

        $pemprivatekeyrecord = new \stdClass();
        $pemprivatekeyrecord->keyname = 'pemprivatekey';
        $pemprivatekeyrecord->keyvalue = $eckeys['privatekeypem'];
        $pemprivatekeyrecord->timecreated = $now;

        $pempublickeyrecord = new \stdClass();
        $pempublickeyrecord->keyname = 'pempublickey';
        $pempublickeyrecord->keyvalue = $eckeys['publickeypem'];
        $pempublickeyrecord->timecreated = $now;

        $vapidprivatekeyrecord = new \stdClass();
        $vapidprivatekeyrecord->keyname = 'vapidprivatekey';
        $vapidprivatekeyrecord->keyvalue = $vapidkeys['privatekey'];
        $vapidprivatekeyrecord->timecreated = $now;

        $vapidpublickeyrecord = new \stdClass();
        $vapidpublickeyrecord->keyname = 'vapidpublickey';
        $vapidpublickeyrecord->keyvalue = $vapidkeys['publickey'];
        $vapidpublickeyrecord->timecreated = $now;

        $keytransaction = $DB->start_delegated_transaction();
        $DB->delete_records('message_pop_keys');
        $DB->insert_records('message_pop_keys',
                [$pemprivatekeyrecord, $pempublickeyrecord, $vapidprivatekeyrecord, $vapidpublickeyrecord]
        );
        $DB->commit_delegated_transaction($keytransaction);

        return [
            'pemprivatekey' => $eckeys['privatekeypem'],
            'pempublickey' => $eckeys['publickeypem'],
            'vapidprivatekey' => $vapidkeys['privatekey'],
            'vapidpublickey' => $vapidkeys['publickey']
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
                'payload' => $this->base64url_encode($encryptedPayload),
                'localpublickey' => $this->base64url_encode($localpublickeyey)
        ];
    }

    /**
     * Generate the signature for the JWT.
     *
     * @param string $header The JWT header.
     * @param string $payloadinfo The JWT payload.
     * @return string The signature.
     */
    public function generate_signature(string $header, string $payloadinfo): string {
        // Create the signature input string
        $signatureInput = $header . '.' . $payloadinfo;

        // Generate the signature with OpenSSL.
        $signature = '';
        $privatekeypem = $this->get_encryption_keys()['pemprivatekey'];
        openssl_sign($signatureInput, $signature, $privatekeypem, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature and remove padding.
        return $this->base64url_encode($signature);
    }

    /**
     * Generate the JWT.
     *
     * @param string $endpoint The endpoint.
     * @return string The JWT.
     */
    public function generate_jwt(array $header, array $payload): string {
        // Generate the JWT header.
        $encodedheader = $this->base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));

        // Generate the JWT payload.
        $encodedpayload = $this->base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));

        // Create the signature input string.
        $signature = $this->generate_signature($encodedheader, $encodedpayload);

        // Generate the JWT.
        return $encodedheader . '.' . $encodedpayload . '.' . $signature;
    }

    public static function fromAsn1(string $signature, int $length): string
    {
        $message = bin2hex($signature);
        $position = 4;

        $pointR = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));
        $pointS = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));

        $bin = hex2bin(str_pad($pointR, $length, '0', STR_PAD_LEFT).str_pad($pointS, $length, '0', STR_PAD_LEFT));

        return $bin;
    }

    public static function readAsn1Content(string $message, int &$position, int $length): string
    {
        $content = mb_substr($message, $position, $length, '8bit');
        $position += $length;

        return $content;
    }


    public static function readAsn1Integer(string $message, int &$position): string
    {
        $position += 2;

        $length = (int) hexdec(self::readAsn1Content($message, $position, 2));

        return self::readAsn1Content($message, $position, $length * 2);
    }

    public static function retrievePositiveInteger(string $data): string
    {
        while (0 === mb_strpos($data, '00', 0, '8bit')
                && mb_substr($data, 2, 2, '8bit') > '7f') {
            $data = mb_substr($data, 2, null, '8bit');
        }

        return $data;
    }

    public function get_vapid_header(string $audience, string $subject, array $keys) {
        $expiration = time() + 43200; // equal margin of error between 0 and 24h
        $header = [
                'typ' => 'JWT',
                'alg' => 'ES256',
        ];
        $encodedheader = $this->base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));

        $payload = [
                'aud' => $audience,
                'exp' => $expiration,
                'sub' => $subject,
        ];

        $jwtPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        $encodedpayload = $this->base64url_encode($jwtPayload);

        // Get the private key resource from the PEM string
        $private_key_res = openssl_pkey_get_private($keys['pemprivatekey']);
        if ($private_key_res === false) {
            throw new \RuntimeException('Failed to load the private key.');
        }

        // Generate the signature
        $to_sign = $encodedheader . '.' . $encodedpayload;
        if (!openssl_sign($to_sign, $signature, $private_key_res, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to create the signature.');
        }

        $rawsignature = self::fromAsn1($signature, 64);
        $encodedsignature = $this->base64url_encode($rawsignature);
        $jwt =  $encodedheader . '.' . $encodedpayload . '.' . $encodedsignature;

        return ['Authorization' => 'vapid t='.$jwt.', k='.$keys['vapidpublickey']];

    }
}
