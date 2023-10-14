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

use core\http_client;
use Jose\Component\Core\Util\ECKey;

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

    public static function convert_private_key_to_pem(string $privatekey, $publickey): string {
        $d = $privatekey;
        $d = unpack('H*', str_pad(self::base64url_decode($d), 32, "\0", STR_PAD_LEFT));

        $der = pack(
                'H*',
                '3077' // SEQUENCE, length 87+length($d)=32
                . '020101' // INTEGER, 1
                . '0420'   // OCTET STRING, length($d) = 32
                . $d[1]
                . 'a00a' // TAGGED OBJECT #0, length 10
                . '0608' // OID, length 8
                . '2a8648ce3d030107' // 1.3.132.0.34 = P-256 Curve
                . 'a144' //  TAGGED OBJECT #1, length 68
                . '0342' // BIT STRING, length 66
                . '00' // prepend with NUL - pubkey will follow
        );

        $der .= self::base64url_decode($publickey);
        $pem = '-----BEGIN EC PRIVATE KEY-----' . PHP_EOL;
        $pem .= chunk_split(base64_encode($der), 64, PHP_EOL);

        return $pem . ('-----END EC PRIVATE KEY-----' . PHP_EOL);
    }

    public static function convert_to_pem($privatekey, $publickey): array
    {
        // Decode the base64url-encoded private and public keys to binary
        $privateKeyBinary = self::base64url_decode($privatekey);
        $publicKeyBinary = self::base64url_decode($publickey);

        //$publicKeyPEM = ECKey::convertPublicKeyToPEM($publickey);
        //$privateKeyPEM = ECKey::convertPrivateKeyToPEM($privatekey);


        return [
                'pemprivatekey' => $privateKeyPEM,
                'pempublickey' => $publicKeyPEM
        ];
    }


    /**
     * Register a push subscription for a user.
     *
     * @param int $userid The user id.
     * @param string $endpoint Subscription endpoint URL.
     * @param string $auth Subscription auth secret.
     * @param string $p256dh Subscription public key.
     * @param int|null $expirationtime Subscription expiration time.
     * @return bool True if successful, false otherwise.
     * @throws \dml_exception
     */
    public static function register_push_subscription(
        int $userid,
        #[\SensitiveParameter] string $endpoint,
        #[\SensitiveParameter] string $auth,
        string $p256dh,
        ?int $expirationtime = 0
    ): bool {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->endpoint = $endpoint;
        $record->p256dh = $p256dh;
        $record->auth = $auth;
        $record->expiration = $expirationtime > 0 ? floor($expirationtime / 1000) : 0;;
        $record->timecreated = time();

        return $DB->insert_record('message_popup_subscriptions', $record, false);
    }

    public static function encryptPayload($payload, $publicKey, $authToken) {
        // Generate a local private key for the server
        $localPrivateKey = random_bytes(32);

        // Compute the corresponding public key
        $localPublicKey = sodium_crypto_scalarmult_base($localPrivateKey);

        // Derive a shared secret from the client's public key and the server's private key
        error_log(SODIUM_CRYPTO_SCALARMULT_SCALARBYTES);
        error_log(strlen(base64_decode($publicKey)));
        $sharedSecret = sodium_crypto_scalarmult($localPrivateKey, $publicKey);

        // Generate a random salt
        $salt = random_bytes(16);

        // Compute the server's public key from the shared secret - Though this isn't used, included for completeness
        $serverPublicKey = sodium_crypto_scalarmult_base($sharedSecret);


        // Create a nonce for the encryption
        $nonce = hash('sha256', $salt . $sharedSecret, true);
        $nonce = substr($nonce, 0, 12); // Truncate to first 12 bytes for AES GCM

        // Encrypt the payload using AES-128-GCM
        $cipher = openssl_encrypt(
                $payload,
                'aes-128-gcm',
                $sharedSecret,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag // This will be filled with the authentication tag by openssl_encrypt
        );

        // Assemble the final payload (nonce + auth tag + cipher text)
        $encryptedPayload = $nonce . $tag . $cipher;

        // Return the encrypted payload and the local public key
        return [
                'payload' => base64_encode($encryptedPayload),
                'localPublicKey' => base64_encode($localPublicKey)
        ];
    }

    // $encryptedData['payload'] contains the encrypted payload
    // $encryptedData['localPublicKey'] contains the server's public key


    /**
     * Get all valid push subscriptions.
     * Users can have multiple active subscriptions.
     * There could be a lot of records in this table, so we return a recordset.
     *
     * @return \moodle_recordset
     */
    public static function get_push_subscriptions(): \moodle_recordset {
        global $DB;

        // For now lets just return all subscriptions.
        // Later we'll handle expired subscriptions.
        return $DB->get_recordset('message_popup_subscriptions');
    }

    /**
     * Send a push notification to a user.
     *
     * @param \stdClass $subscription The subscription data.
     * @param array $payload The payload to send.
     * @param string $vapidPrivateKey The VAPID private key.
     * @param string $vapidPublicKey The VAPID public key.
     * @param http_client|null $client The HTTP client to use.
     * @return int The HTTP status code.
     */
    public static function send_push_notification($subscription, $payload, $vapidPrivateKey, $vapidPublicKey, ?http_client $client = null) {
        // Allow for dependency injection of http client.
        if (!$client) {
            $client = new http_client();
        }

        // Decode subscription data
        $endpoint = $subscription->endpoint;
        $clientPublicKey = $subscription->p256dh;
        $clientAuthToken = $subscription->auth;

        // Encrypt the payload using client's public key and auth token
        $encryptedData = self::encryptPayload($payload, $clientPublicKey, $clientAuthToken);

        // Generate the JWT header
        $header = [
                'typ' => 'JWT',
                'alg' => 'ES256'
        ];

        $header = base64_encode(json_encode($header));
        $header = rtrim($header, '=');

        // Generate the JWT payload
        $payloadInfo = [
                'aud' => 'https://' . parse_url($endpoint, PHP_URL_HOST),
                'exp' => time() + (12 * 60 * 60),
                'sub' => 'mailto:your-email@example.com'
        ];

        $payloadInfo = base64_encode(json_encode($payloadInfo));
        $payloadInfo = rtrim($payloadInfo, '=');

        // Create the signature input string
        $signatureInput = $header . '.' . $payloadInfo;

        // Sign the input string using libsodium.
        $signature = sodium_crypto_sign_detached($signatureInput, sodium_hex2bin($vapidPrivateKey));

        $signature = base64_encode($signature);
        $signature = rtrim($signature, '=');

        // Generate the JWT
        $jwt = $header . '.' . $payloadInfo . '.' . $signature;

        // Prepare the request headers
        $headers = [
                'TTL' => '30',
                'Content-Encoding' => 'aes128gcm',
                'Authorization' => 'vapid t=' . $jwt . ', k=' . $vapidPublicKey,
                'Crypto-Key' => 'dh=' . $encryptedData['localPublicKey']
        ];

        // Send the request using Guzzle
        $response = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $encryptedData['payload'],
        ]);

        return $response->getStatusCode();
    }

}
