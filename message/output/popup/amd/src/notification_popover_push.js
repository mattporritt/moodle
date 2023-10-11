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

import Ajax from 'core/ajax';

/**
 * Converts a JS ArrayBuffer to a Base64 encoded string.
 *
 * @param {ArrayBuffer} arrayBuffer The ArrayBuffer to convert.
 * @return {string} The Base64 encoded string.
 */
const arrayBufferToBase64 = (arrayBuffer) => {
    // Create a Uint8Array from the ArrayBuffer.
    const uint8Array = new Uint8Array(arrayBuffer);

    // Convert the Uint8Array to a character array.
    const charArray = Array.from(uint8Array).map(byte => String.fromCharCode(byte));

    // Join the character array to a string.
    const stringArray = charArray.join('');

    // Encode the string to Base64 and return.
   return btoa(stringArray);
};

const setupWorker = async() => {
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

const registerPushSubscription = async(subscription) => {
    const request = {
        methodname: 'message_popup_register_push_subscription',
        args: {
            endpoint: subscription.endpoint,
            expirationtime: subscription.expirationTime,
            auth: arrayBufferToBase64(subscription.getKey('auth')),
            p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
        }
    };

    return Ajax.call([request])[0];
};

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
};

/**
 * Initialise the push notification service.
 *
 * @param {string} vapidpublickey The public key to use for push notifications.
 */
export const init = async(vapidpublickey) => {
    // Set up the service worker.
    const workerRegistration = await setupWorker();

    // Attempt to retrieve existing push subscription.
    let subscription = await workerRegistration.pushManager.getSubscription();

    // If no existing subscription, subscribe.
    if (!subscription) {
        const convertedVapidKey = urlBase64ToUint8Array(vapidpublickey);
        const options = {
            userVisibleOnly: true, //
            applicationServerKey: convertedVapidKey
        };

        // Get the push subscription object.
        subscription = await workerRegistration.pushManager.subscribe(options);

        // Register the subscription with the server.
        await registerPushSubscription(subscription).catch((error) => {
            // If the registration fails, unsubscribe.
            subscription.unsubscribe();
            throw error;
        });
    }
};
