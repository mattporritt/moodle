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

    if ($oldversion < 2023100900.06) {
        global $DB;
        $dbman = $DB->get_manager();

        // Define table message_popup_subscriptions to be created.
        $table = new xmldb_table('message_popup_subscriptions');

        // Adding fields to table message_popup_subscriptions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('endpoint', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('endpointhash', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('p256dh', XMLDB_TYPE_CHAR, '90', null, XMLDB_NOTNULL, null, null);
        $table->add_field('auth', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expiration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table message_popup_subscriptions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('useridkey', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table message_popup_subscriptions.
        $table->add_index('endpointhash', XMLDB_INDEX_UNIQUE, ['endpointhash']);

        // Conditionally launch create table for message_popup_subscriptions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table message_pop_keys to be created.
        $table = new xmldb_table('message_pop_keys');

        // Adding fields to table message_pop_keys.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('keyname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('keyvalue', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table message_pop_keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for message_pop_keys.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Check if we have VAPID keys stored.
        $keys = $DB->get_records('message_pop_keys');
        if (empty($keys)) {
            // Generate and store in config the VAPID keys.
            $encrypt = new message_popup\encrypt();
            $encrypt->set_encryption_keys();
        }

        upgrade_plugin_savepoint(true, 2023100900.06, 'message', 'popup');
    }
    return true;
}
