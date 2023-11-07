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

/* eslint-env serviceworker */

/**
 * Send a push notification to the users browser.
 *
 * @param {Event} event The push event.
 * @param {object} data The data object.
 */
const sendPush = (event, data) => {
    const options = {
        body: data.body, // The body of the notification.
        icon: '/Moodle_Circle_M.jpg', // A URL representing an image to be displayed as the icon of the notification.
        image: '/Moodle_Circle_M.jpg', // A URL to an image to be displayed in the notification.
        badge: '/Moodle_Circle_M.jpg', // A URL to a small image that represents the application for the status bar and notification area.
        vibrate: [200, 100, 200], // An array representing a vibration pattern for the device's vibration hardware to emit when the notification fires.
        lang: 'en-AU', // Specifies the language of the notification (e.g., 'en-US').
        //tag: 'user-notification', // An identifier for a notification that allows you to update and remove related notifications rather than post a new one.
        //data: {primaryKey: 1}, // Arbitrary data that you want to be associated with the notification.
        requireInteraction: true, // A boolean specifying that the notification should remain active until the user clicks or dismisses it, rather than closing automatically.
        //direction: 'auto', // The direction in which to display the notification's text. It can be 'auto' (default), 'ltr' (left to right), or 'rtl' (right to left).
        //silent: false, // A boolean indicating whether the notification should be silent, i.e., no sounds or vibrations should be issued, regardless of the device settings.
        timestamp: Date.now(), // A DOMHighResTimeStamp denoting the time the notification is displayed to the user.
        actions: [ // Actions do not work on Mac OS.
            //{action: 'like', title: 'Like', icon: '/img/like.png'},
            //{action: 'reply', title: 'Reply', icon: '/img/reply.png'}
            {action: 'view', title: 'Viewzzz'}
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
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
        // Convert the data to a json string, required for broadcasting.
        const json = JSON.stringify(data);
        channel.postMessage(json);

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

self.addEventListener('notificationclick', function(event) {
    self.console.log('Notification click: tag ', event.notification.tag);
    event.notification.close(); // Close the notification when clicked

    // Perform some action in response to the notification click
    event.waitUntil(
        // Assuming you want to open a URL in a new tab/window
        clients.matchAll({
            type: 'window'
        }).then(function(windowClients) {
            // Check if there is already a window/tab open with the target URL
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                // If so, just focus it.
                if (client.url === '/' && 'focus' in client) {
                    return client.focus();
                }
            }
            // If not, then open the new URL
            if (clients.openWindow) {
                return clients.openWindow('/');
            }
        })
    );
});

