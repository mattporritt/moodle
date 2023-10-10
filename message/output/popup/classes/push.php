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

use Base64Url\Base64Url;
use Minishlink\WebPush\WebPush;

/**
 * Class used to return information to display for the message popup.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push {

    // Helper function to perform Base64URL encoding
    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function createECKeyUsingOpenSSL(): array {
        $curve = "prime256v1";
        $nistCurveSize = 256;

        $key = openssl_pkey_new([
                'curve_name' => $curve,
                'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        openssl_pkey_export($key, $out);

        $res = openssl_pkey_get_private($out);

        $details = openssl_pkey_get_details($res);

        return [
                'kty' => 'EC',
                'crv' => $curve,
                'd' => self::base64url_encode(
                        str_pad((string) $details['ec']['d'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
                ),
                'x' => self::base64url_encode(
                        str_pad((string) $details['ec']['x'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
                ),
                'y' => self::base64url_encode(
                        str_pad((string) $details['ec']['y'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
                ),
        ];
    }

    public static function serializePublicKeyFromJWK(array $jwk): string
    {
        $hexString = '04';
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk['x'])), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk['y'])), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    /**
     * Generate the VAPID keys for push notifications.
     *
     * @return array
     */
    public static function generate_vapid_keys(): array {
        $config = [
                "curve_name" => "prime256v1",
                "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];

        $privatekeyresource = openssl_pkey_new($config);
        openssl_pkey_export($privatekeyresource, $out);


        $details = openssl_pkey_get_details($privatekeyresource);
        $privatekeyraw = $details['ec']['d'];
        $publickeyraw = $details['ec']['x'] . $details['ec']['y'];

        // URL-safe Base64 encode
        $publickeybase64 = "04" . self::base64url_encode($publickeyraw);
        $privatekeybase64 = self::base64url_encode($privatekeyraw);

        //error_log("privatekeybase64: " . $privatekeybase64);
        //error_log("publickeybase64: " . $publickeybase64);

        $keyarray = self::createECKeyUsingOpenSSL();

        $binaryPublicKey = hex2bin(self::serializePublicKeyFromJWK($keyarray));
        $publickeybase64 = Base64Url::encode($binaryPublicKey);

        $binaryPrivateKey = hex2bin(str_pad(bin2hex(Base64Url::decode($keyarray['d'])), 2 * 32, '0', STR_PAD_LEFT));
        $privatekeybase64 = Base64Url::encode($binaryPrivateKey);

        error_log(print_r(\Minishlink\WebPush\VAPID::createVapidKeys(), true));

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
