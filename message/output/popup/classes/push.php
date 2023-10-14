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

/**
 * Class used to return information to display for the message popup.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push {

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
        $jsonpayload = json_encode($payload);
        $encrypt = new encrypt();
        $encryptedData = $encrypt->encrypt_payload($jsonpayload, $clientPublicKey, $clientAuthToken);

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

        // Generate the JWT.
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
