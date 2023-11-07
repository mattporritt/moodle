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

use core\http_client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class used to return information to display for the message popup.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push {

    public const MAX_PAYLOAD_LENGTH = 3052;

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
        $record->endpointhash = hash('sha512', $endpoint); // Makes matching and indexing on endpoint easier.
        $record->p256dh = $p256dh;
        $record->auth = $auth;
        $record->expiration = $expirationtime > 0 ? floor($expirationtime / 1000) : 0;;
        $record->timecreated = time();

        return $DB->insert_record('message_popup_subscriptions', $record, false);
    }

    /**
     * Delete a subscription.
     * Removes the subscription record from the database.
     *
     * @param string $endpoint The subscription endpoint.
     * @return bool
     * @throws \dml_exception
     */
    public static function delete_subscription(#[\SensitiveParameter] string $endpointhash): bool {
        global $DB;

        // Endpoints are unique, so we can use them to delete subscriptions.
        return $DB->delete_records('message_popup_subscriptions', ['endpointhash' => $endpointhash]);
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
     * The push relay server can return several error conditions.
     * Handle them appropriately, based on error code. See: https://mozilla-services.github.io/autopush-rs/errors.html
     *
     * @param GuzzleException $error The error object returned from the Guzzle request.
     * @param \stdClass $subscription The subscription data.
     * @return void
     */
    public static function handle_push_error(GuzzleException $error, \stdClass $subscription): void {
        if ($error->getCode() === 410) {
            // The subscription is no longer valid, so delete it.
                self::delete_subscription(endpointhash: $subscription->endpointhash);
        } else if ($error->getCode() >= 500) {
            // The push server is having issues, so we should retry later.
            return;
        } else {
            // Unhandled error code, explode.
            error_log('Unhandled push error: '.$error->getMessage());
            throw $error;
        }
    }

    /**
     * Send a push notification to a user.
     *
     * @param \stdClass $subscription The subscription data.
     * @param array $payload The payload to send.
     * @param http_client|null $client The HTTP client to use.
     * @return void
     * @throws GuzzleException
     */
    public static function send_push_notification($subscription, $payload): void {
        $encrypt = new encrypt();
        $keys = $encrypt->get_encryption_keys();
        $vapidsubject = 'mailto:me@website.com'; // Mailto link or url.

        // TODO: Add payload max length check after json encoding.
        $payloadjson = json_encode($payload);
        $paddedpayload = $encrypt->payload_pad($payloadjson, self::MAX_PAYLOAD_LENGTH);

        $salt = random_bytes(16);
        $encrypted = $encrypt->deterministicEncrypt(
                $paddedpayload,
                $subscription->p256dh,
                $subscription->auth,
                $encrypt->createLocalKeyObjectUsingOpenSSL(),
                $salt
        );

        $encryptioncontentcodingheader = $encrypt->getContentCodingHeader($salt, $encrypted['localPublicKey']);
        $content = $encryptioncontentcodingheader.$encrypted['cipherText'];

        $endpoint = $subscription->endpoint;
        $audience = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
        $vapidHeaders = $encrypt->get_vapid_header($audience,  $vapidsubject,  $keys);

        // Construct the headers for the request.
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'TTL' => 2419200,
            'Content-Length' => (string) mb_strlen($content, '8bit'),
            'Authorization' => $vapidHeaders['Authorization'],
        ];

        $client = new http_client();
        try {
            $client->post($endpoint, [
                    'headers' => $headers,
                    'body' => $content,
            ]);
        } catch (GuzzleException $error) {
            self::handle_push_error($error, $subscription);
        }
    }
}
