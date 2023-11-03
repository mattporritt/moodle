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
 * Send a push notification to the users browser.
 *
 * @param {Event} event The push event.
 * @param {object} data The data object.
 */
const sendPush = (event, data) => {
    const title = data.title || 'Default Title';
    const body = data.body || 'Default Body';

    const options = {
        body: body
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
};

// Set up the event listener that will receive push events from the server.
self.addEventListener('push', (event) => {

    let data = {}; // Default data object.
    if (event.data) {
        data = event.data.json(); // Assume the payload is JSON.
        self.console.log('Payload:', event.data.text());
    } else {
        self.console.log('No payload');
    }

    // Filter what we do with the event payload based on its type field.
    switch (data.type) {
        case 'notification':
            // Regular notification.
            // Display a push notification to the user.
            sendPush(event, data);
            // Update the notification count in the popover.
            // TODO: Update the notification count in the popover.
            break;
        case 'broadcast':
            // High priority notification.
            // Display a push notification to the user.
            // Update the notification count in the popover.
            // Show a notification modal to the user.
            self.console.log('broadcast payload');
            break;
        default:
            self.console.error('Payload does not contain a type field.');
            break;
    }
});
