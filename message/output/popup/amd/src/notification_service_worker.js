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

/**
 * Send a message to any page connected to this worker.
 * The page decides what to do with the message.
 *
 * @param {object} data The data object.
 */
const sendMessage = (data) => {
    self.console.log('Sending message to clients', data);
    const channel = new BroadcastChannel('my-channel');
        // Send a message to all clients listening on 'my-channel'.
        channel.postMessage('test message');

};

// Set up the event listener that will receive push events from the server.
self.addEventListener('push', (event) => {
    const data = event.data.json(); // Assume the payload is JSON.
    self.console.log('Payload:', event.data.text());

    // Filter what we do with the event payload based on its type field.
    if (data.push === true) {
        // Push notifications are handled exclusively by the service worker.
        sendPush(event, data);
    }
    // Everything else to do with the message is handled by the parent pages.
    sendMessage(data);
});

// Set up the activate event listener to claim clients.
self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});
