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

export const setupWorker = async() => {
    window.console.log('push lib loaded');
    let registration;

    try {
        // Register the service worker.
        // As the service worker listens for push notifications,
        // the user will be prompted to allow push notifications.
        const workeruri = '/message/output/popup/amd/build/notification_service_worker.min.js';
        registration = await navigator.serviceWorker.register(workeruri);
    } catch (error) {
        if (error.name === 'NotAllowedError') {
            // Handle the specific case where permission was denied.
            window.console.error('Permission for Push API has been denied');
            // TODO: Show a message to the user to explain why they need to enable Push.
            // Maybe save this as a preference so we don't show it again?
        } else {
            // We have a non permission error, re-throw.
            throw error;
        }
    }
    return registration;
};

export const init = async() => {
    // Set up the service worker.
    const registration = await setupWorker();

    // Attempt to retrieve existing push subscription.
    let subscription = await registration.pushManager.getSubscription();

    // If no existing subscription, subscribe
    if (!subscription) {
        await registration.pushManager.subscribe({userVisibleOnly: true});
    }
};
