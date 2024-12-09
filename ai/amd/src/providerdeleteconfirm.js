// This file is part of Moodle - http://moodle.org/ //
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
 * AI provider deletion confirmation.
 * Renders a confirmation modal when deleting an AI provider.
 *
 * @module     core_ai/providerdeleteconfirm
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import DeleteCancelModal from 'core/modal_delete_cancel';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';

/**
 * Call the delete service.
 *
 * @param {String} providerid The provider id.
 * @return {Promise} The promise.
 */
const deleteProviderService = async(providerid) => Ajax.call([{
    methodname: 'core_ai_delete_provider_instance',
    args: {
        providerid: providerid,
    },
}])[0];

/**
 * Handle the delete event.
 * Calls the delete service and reloads the page.
 *
 * @param {String} providerid The provider id.
 * @returns {Promise<void>}
 */
const handleDelete = async(providerid) => {
    await deleteProviderService(providerid);
    // Refresh the page, so we get the updated list of providers, and any messages.
    window.location.reload();
};

/**
 * Show the delete confirmation modal.
 *
 * @param {Event} e The event object.
 */
const showDeleteModal = async(e) => {
    const providerid = e.target.dataset.id;
    const provider = e.target.dataset.provider;
    const name = e.target.dataset.name;
    const bodyparams = {
        provider: provider,
        name: name,
    };
    const modal = await DeleteCancelModal.create({
        title: await getString('providerinstancedelete', 'core_ai'),
        body: await getString('providerinstancedeleteconfirm', 'core_ai', bodyparams),
        show: true,
        removeOnClose: true,
    });

    // Handle delete event.
    modal.getRoot().on(ModalEvents.delete, (e) => {
        e.preventDefault();
        handleDelete(providerid);
        modal.destroy();
    });
};

/**
 * Initialise the delete listeners.
 *
 */
export const init = () => {
    document.querySelectorAll('.ai-provider-delete').forEach((button) => {
        button.addEventListener('click', (e) => {
                e.preventDefault();
                showDeleteModal(e);
        });
    });
};
