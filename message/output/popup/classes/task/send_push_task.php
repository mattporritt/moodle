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

namespace message_popup\task;

use core\task\scheduled_task;
use message_popup\encrypt;

/**
 * Contains the class responsible for sending push notifications to users.
 *
 * @package    message_popup
 * @copyright  2023 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_push_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksendpush', 'message_popup');
    }

    /**
     * Send out emails.
     */
    public function execute() {
        mtrace('Started: Sending push notifications.');
        $payload = [
            'title' => 'Your Custom Title', // Notification Title.
            'body' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. In lectus ligula, tristique sit amet turpis sed, molestie consequat felis.  Etiam m', // Notification Message.
            'push' => true, // If true raise a OS push notification.
            'broadcast' => true, // If true raise a high priority modal notification.
        ];

        // Get all the active push notification subscriptions.
        // While we are still in the R&D phase the message will be hardcoded,
        // so all push notifications will always get the same hardcoded message
        // every run.
        // Eventually we should do a check, so we only send push notifications
        // to users who have a push subscription and pending messages.
        $subscriptions = \message_popup\push::get_push_subscriptions();
        foreach ($subscriptions as $subscription) {
            \message_popup\push::send_push_notification($subscription, $payload);
        }
        $subscriptions->close();

        mtrace('Completed: Sending push notifications.');
    }
}
