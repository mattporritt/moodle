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
import ModalCancel from 'core/modal_cancel';
import ModalEvents from 'core/modal_events';
import {getStrings} from 'core/str';


/**
 * Converts a JS ArrayBuffer to a Base64 encoded string.
 *
 * @param {ArrayBuffer} buffer The ArrayBuffer to convert.
 * @return {string} The Base64 encoded string.
 */
const arrayBufferToBase64 = (buffer) => {
    let base64String = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        base64String += String.fromCharCode(bytes[i]);
    }
    return window.btoa(base64String);
};

/**
 * Converts a URL safe Base64 encoded string to a JS ArrayBuffer.
 *
 * @param {string} base64String The URL safe Base64 encoded string.
 * @returns {Uint8Array} outputArray The ArrayBuffer.
 */
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

const processMessage = (data) => {
    if (data.broadcast === true) {
        // We have a broadcast message, so display it in a modal.
        ModalCancel.create({
            title: data.title,
            body: data.body,
            show: true,
            removeOnClose: true,
        });
    }
    // Then update the available notifications.
};

/**
 * Set up the service worker.
 *
 * @returns {Promise<ServiceWorkerRegistration>} The service worker registration.
 */
const setupWorker = async() => {
    let registration;

    try {
        // Register the service worker.
        // As the service worker listens for push notifications,
        // the user will be prompted to allow push notifications.
        const workerUri = '/message/output/popup/amd/build/notification_service_worker.min.js';
        registration = await navigator.serviceWorker.register(workerUri);
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

/**
 * Set up a push subscription.
 *
 * @param {string} vapidpublickey The public key to use for push notifications.
 * @returns {Promise<void>} A promise that resolves when the subscription is set up.
 */
const setupSubscription = async(vapidpublickey) => {
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

/**
 * Register a push subscription with the server.
 *
 * @param {PushSubscription} subscription The push subscription object.
 * @returns {Promise<*>} The response from the server.
 */
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

/**
 * Handle the push modal close event.
 * @param {string} vapidpublickey The public key to use for push notifications.
 * @returns {Promise<void>} A promise that resolves when the modal is closed.
 */
const pushModalClose = async(vapidpublickey) => {
    // Request permission for notifications.
    const permission = await window.Notification.requestPermission();
    if (permission !== 'granted') {
        window.console.error('Notification permission denied.');
        return;
    } else {
        await setupSubscription(vapidpublickey);
    }
};

/**
 * Initialise the push notification service.
 *
 * @param {string} vapidpublickey The public key to use for push notifications.
 */
export const init = async(vapidpublickey) => {
    // Check if the user has already granted permission.
    if (window.Notification.permission === 'denied') {
        // TODO: Keep a record of this and periodically check if the user has changed their mind.
        // For now, just log an error.
        window.console.error('Notification permission denied.');
        return;
    } else if (window.Notification.permission === 'granted') {
        // If the user has already granted permission, we can skip the prompt,
        // and just continue with initialisation.
        window.console.log('Notification permission granted.');
        await setupSubscription(vapidpublickey);
    } else {
        // Otherwise, we need to ask the user for permission.
        // And due to browser security, we need to do this in response to a user action.
        window.console.log('Notification permission not granted. Requesting permission...');
        // Get the strings that will be used in the modal.
        const modalStrings = await getStrings([
            {key: 'pushmodaltitle', component: 'message_popup'},
            {key: 'pushmodalbody', component: 'message_popup'},
            {key: 'ok', component: 'message_popup'},
        ]);

        // Set up the modal.
        const modal = await ModalCancel.create({
            title: modalStrings[0],
            body: modalStrings[1],
            show: true,
            removeOnClose: true,
        });
        // Override default button text.
        modal.setButtonText('cancel', modalStrings[2]);
        // Set up the modal event listeners.
        modal.getRoot().on(ModalEvents.cancel, async() => {
            pushModalClose(vapidpublickey);
        });
        modal.getRoot().on(ModalEvents.hidden, async() => {
            pushModalClose(vapidpublickey);
        });
    }
    const channel = new BroadcastChannel('my-channel');

    channel.addEventListener('message', (event) => {
        window.console.log('Received message from service worker:', event.data);
        // Convert the json string back to an object.
        const dataObj = JSON.parse(event.data);
        processMessage(dataObj);
    });
};
