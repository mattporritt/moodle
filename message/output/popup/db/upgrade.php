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

/**
 * Upgrade code for popup message processor
 *
 * @package   message_popup
 * @copyright 2008 Luis Rodrigues
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for the popup message processor
 *
 * @param int $oldversion The version that we are upgrading from
 */
function xmldb_message_popup_upgrade($oldversion) {
    global $DB;

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023100900.01) {
        // First check if we have VAPID keys stored.
        $vapidprivatekey = get_config('message_popup', 'vapidprivatekey');

        // If we don't have a VAPID private key stored, then we need to generate them.
        if (empty($vapidprivatekey)) {
            // Generate and store in config the VAPID keys.
            $keys = \message_popup\push::generate_vapid_keys();
            set_config('vapidprivatekey', $keys['privatekey'], 'message_popup');
            set_config('vapidpublickey', $keys['publickey'], 'message_popup');
        }

        upgrade_plugin_savepoint(true, 2023100900.01, 'message', 'popup');
    }
    return true;
}
