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
 * Form confirmation module.
 *
 * @module     core_form/confirm
 * @copyright  2022 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Str from 'core/str';
import Templates from 'core/templates';
import LoadingIcon from 'core/loadingicon';

/**
 * Module level variables.
 */
let modalObj;
let submitId;

const spinner = LoadingIcon.getIcon();

/**
 * Creates the confirmation modal.
 *
 * @private
 */
const createModal = () => {
// Get the Title String.
    Str.get_string('confirm', 'core_form').then((title) => {
        // Create the Modal.
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: title,
            body: spinner,
            large: true
        }).done(function(modal) {
            modalObj = modal;
            const root = modalObj.getRoot();

            modal.setButtonText('save', Str.get_string('yes'));
            modal.setButtonText('cancel', Str.get_string('no'));

            // Submit form on the save event of the modal.
            root.on(ModalEvents.save, () => {
                const form = document.getElementById(submitId);
                form.submit();
            });
        });
        return;
    }).catch(() => {
        Notification.exception(new Error('Failed to load string: loading'));
    });
};

/**
 * Updates the modal with content.
 *
 * @param {Array} confirmNotices The notice information.
 * @private
 */
function updateModal(confirmNotices) {
    const context = {'notices': confirmNotices};

    modalObj.setBody(spinner);
    modalObj.show();

    // Load the modal body with the relevant confirmation messages.
    Templates.renderForPromise('core_form/modal_confirm', context).then(({html}) => {
        modalObj.setBody(html);
        return;
    }).catch(() => {
        Notification.exception(new Error('Failed to load template: core_form/modal_confirm'));
    });
}

/**
 * Handle the form submission event and gather the confirmation conditions.
 *
 * @param {event} event The form submission event.
 */
const formSubmit = (event) => {
    if (event.submitter.name === 'cancel') {
        return;
    }
    event.preventDefault();
    const form = event.target;
    let confirmNotices = [];

    // Get all form elements that have data confirm attributes.
    const confirmElements = form.querySelectorAll('[data-confirm]');

    // Build array of confirmation item labels and descriptions.
    confirmElements.forEach((element) => {
        if ((element.type === 'checkbox' && element.checked != Boolean(Number(element.dataset.confirm)))
            || ((typeof element.value !== 'undefined') && element.value != element.dataset.confirm)) {

            let noticeData = {'label': element.labels[0].textContent.trim()};
            if (element.dataset.confirmdesc !== undefined) {
                noticeData.description = element.dataset.confirmdesc;
            }

            confirmNotices.push(noticeData);
        }
    });

    if (confirmNotices.length) {
        // Call the modal to display the fields with confirmation messages.
        updateModal(confirmNotices);
        return;
    }

    // No confirmation messages apply, just submit the form.
    document.getElementById(submitId).submit();
};

/**
 * Initialise method for confirmation display.
 *
 * @param {String} formId The id of the form the confirmation applies to.
 */
export const init = (formId) => {
    submitId = formId;

    // Set up the modal to be used later.
    createModal();

    // Add submit event listener to the form.
    const form = document.getElementById(formId);
    form.addEventListener('submit', formSubmit);
};
