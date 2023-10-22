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

require $CFG->dirroot . '/vendor/autoload.php';

use Base64Url\Base64Url;
use core\http_client;
use GuzzleHttp\Psr7\Request;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Utils;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;


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
     * @param http_client|null $client The HTTP client to use.
     * @return int The HTTP status code.
     */
    public static function send_push_notification($subscription, $payload, ?http_client $client = null) {
        // Allow for dependency injection of http client.
        if (!$client) {
            $client = new http_client();
        }

        $endpoint = $subscription->endpoint;
        $clientPublicKey = $subscription->p256dh;
        $clientAuthToken = $subscription->auth;

        // Encryption object used for encrypting the payload and generating the JWT.
        $encrypt = new encrypt();

        // Encrypt the payload using client's public key and auth token.
        $jsonpayload = json_encode($payload);
        $encryptedData = $encrypt->encrypt_payload($jsonpayload, $clientPublicKey, $clientAuthToken);

        $encrypted = Encryption::encrypt($jsonpayload, $clientPublicKey, $clientAuthToken, 'aes128gcm');
        $cipherText = $encrypted['cipherText'];
        $salt = $encrypted['salt'];
        $localPublicKey = $encrypted['localPublicKey'];
        $encryptionContentCodingHeader = Encryption::getContentCodingHeader($salt, $localPublicKey, 'aes128gcm');
        $content = $encryptionContentCodingHeader.$cipherText;


        // Generate the JWT.
        $jwt = $encrypt->generate_jwt($endpoint);

        // Prepare the request headers.
        $vapidkeys = $encrypt->get_encryption_keys();
        $vapidPublicKey = $vapidkeys['vapidpublickey'];
        $audience = 'https://' . parse_url($endpoint, PHP_URL_HOST);
        $vapidHeaders = VAPID::getVapidHeaders(
                $audience,
                'mailto:your-email@example.com',
                $encrypt->base64url_decode($vapidPublicKey),
                $encrypt->base64url_decode($vapidkeys['vapidprivatekey']),
                'aes128gcm');
        $contentlength = (string) mb_strlen($encryptedData['payload'], '8bit');
        $headers = [
                'TTL' => '2419200',
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'Authorization' => $vapidHeaders['Authorization'],
                'Content-Length' => $contentlength
                //'Crypto-Key' => 'dh=' . $encryptedData['localpublickey']
        ];
        error_log(print_r($headers, true));
        error_log(print_r($encryptedData['payload'], true));

        // Send the request using Guzzle
        $response = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $content,
        ]);


        return $response->getStatusCode();
    }

    public static function send_push_notification_simple($subscription, $payload) {
        // array of notifications
        $notifications = [
                [
                        'subscription' => Subscription::create([ // this is the structure for the working draft from october 2018 (https://www.w3.org/TR/2018/WD-push-api-20181026/)
                                "endpoint" => $subscription->endpoint,
                                "keys" => [
                                        'p256dh' => $subscription->p256dh,
                                        'auth' => $subscription->auth
                                ],
                                'contentEncoding' => 'aes128gcm',
                        ]),
                        'payload' => '{"msg":"Hello World!"}',
                ],
        ];

        $encrypt = new encrypt();
        $keys = $encrypt->get_encryption_keys();

        $auth = [
                'VAPID' => [
                        'subject' => 'mailto:me@website.com', // can be a mailto: or your website address
                        'publicKey' => $keys['vapidpublickey'], // (recommended) uncompressed public key P-256 encoded in Base64-URL
                        'privateKey' => $keys['vapidprivatekey']
                    // (recommended) in fact the secret multiplier of the private key encoded in Base64-URL],
                ]
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $report = $webPush->sendOneNotification(
                $notifications[0]['subscription'],
                $notifications[0]['payload'] // optional (defaults null)
        );

        $endpoint = $report->getRequest()->getUri()->__toString();

        if ($report->isSuccess()) {
            echo "[v] Message sent successfully for subscription {$endpoint}.";
        } else {
            echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";
        }
    }

    public static function prepare($payload, array $subscription, array $auth): array {
        $endpoint = $subscription['endpoint'];
        $userPublicKey = $subscription['keys']['p256dh'];
        $userAuthToken = $subscription['keys']['auth'];
        $contentEncoding = 'aes128gcm';

        $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken, $contentEncoding);
        $cipherText = $encrypted['cipherText'];
        $salt = $encrypted['salt'];
        $localPublicKey = $encrypted['localPublicKey'];
        $encryptionContentCodingHeader = Encryption::getContentCodingHeader($salt, $localPublicKey, $contentEncoding);
        $content = $encryptionContentCodingHeader.$cipherText;

        $headers = [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => $contentEncoding,
                'TTL' => 2419200,
                'Content-Length' => (string) mb_strlen($content, '8bit'),
        ];

        $audience = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
        $vapidHeaders = VAPID::getVapidHeaders($audience,  $auth['VAPID']['subject'],  $auth['VAPID']['publicKey'],  $auth['VAPID']['privateKey'], $contentEncoding);
        $headers['Authorization'] = $vapidHeaders['Authorization'];

        return [$endpoint, $headers, $content];
    }

    public static function send_push_notification_raw($subscription, $payload) {
        // array of notifications
        $notifications = [
                [
                        'subscription' => [ // this is the structure for the working draft from october 2018 (https://www.w3.org/TR/2018/WD-push-api-20181026/)
                                "endpoint" => $subscription->endpoint,
                                "keys" => [
                                        'p256dh' => $subscription->p256dh,
                                        'auth' => $subscription->auth
                                ],
                                'contentEncoding' => 'aes128gcm',
                        ],
                        'payload' => '{"msg":"Hello World!"}',
                ],
        ];

        $encrypt = new encrypt();
        $keys = $encrypt->get_encryption_keys();

        $auth = [
                'VAPID' => [
                        'subject' => 'mailto:me@website.com', // can be a mailto: or your website address
                        'publicKey' => $keys['vapidpublickey'],
                        'privateKey' => $keys['vapidprivatekey']
                ]
        ];

        // TODO: Add payload max length check.
        $payload = Encryption::padPayload($notifications[0]['payload'], Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH, 'aes128gcm');
        $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        list($endpoint, $headers, $content) = self::prepare($payload, $notifications[0]['subscription'], $auth);

        $client = new http_client();
        $response = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $content,
        ]);


        return $response->getStatusCode();
    }
}
