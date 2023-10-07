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
     * Generate the VAPID keys for push notifications.
     *
     * @return array
     */
    public static function generate_vapid_keys(): array {
        // Generate VAPID keys.
        $keypair = sodium_crypto_sign_keypair();
        $privatekey = sodium_crypto_sign_secretkey($keypair);
        $publickey = sodium_crypto_sign_publickey($keypair);

        // Encode keys as base64
        $privatekeybase64 = base64_encode($privatekey);
        $publickeybase64 = base64_encode($publickey);

        return [
                'privatekey' => $privatekeybase64,
                'publickey' => $publickeybase64
        ];
    }
}
