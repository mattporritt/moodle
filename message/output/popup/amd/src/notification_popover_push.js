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

export const init = async() => {
    window.console.log('push lib loaded');

    try {
        // Register the service worker
        const workeruri = '/message/output/popup/amd/build/notification_service_worker.min.js';
        const registration = await navigator.serviceWorker.register(workeruri);

        // Attempt to retrieve existing push subscription
        let subscription = await registration.pushManager.getSubscription();

        // If no existing subscription, subscribe
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({userVisibleOnly: true});
        }

    } catch (error) {
        if (error.name === 'NotAllowedError') {
            // Handle the specific case where permission was denied.
            window.console.error('Permission for Push API has been denied');

            // TODO: Show a message to the user to explain why they need to enable Push.
            // Maybe save this as a preference so we don't show it again?
        } else {
            // General error handling
            window.console.error('Service Worker registration or push subscription failed:', error);
        }
    }
};
