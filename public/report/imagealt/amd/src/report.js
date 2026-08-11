// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Launch the occurrence review modal from a report row or the bulk batch page.
 *
 * @module     report_imagealt/report
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';
import {dispatchEvent} from 'core/event_dispatcher';
import reportEvents from 'core_reportbuilder/local/events';
import reportSelectors from 'core_reportbuilder/local/selectors';
import initSuggestion from './suggestion';

const EDIT_SELECTOR = '[data-action="report-imagealt-edit"]';

// The classifications that mean the image no longer has anything wrong with it, which the report says out loud on
// saving: the report opens as a worklist, so remediating an image usually takes its row out of the table, and a row
// vanishing on save otherwise looks more like a fault than like the work being done.
const RESOLVED_STATUSES = ['present', 'decorative'];

/**
 * Launch the review modal for one occurrence.
 *
 * @param {HTMLElement} trigger The element that was clicked.
 */
const launchModal = async(trigger) => {
    const [title, saveButtonText] = await Promise.all([
        getString('editalttext', 'report_imagealt'),
        getString('save', 'moodle'),
    ]);
    const modalForm = new ModalForm({
        formClass: 'report_imagealt\\form\\review',
        args: {
            id: trigger.dataset.occurrenceid,
            contextid: trigger.dataset.contextid,
        },
        // 'large' only gets as far as Bootstrap's modal-lg; there is no built-in config for modal-xl, so it is added
        // directly once the modal exists, the same way Tiny's own ImageModal widens its image dialog.
        modalConfig: {title, large: false},
        saveButtonText,
        returnFocus: trigger,
    });

    // The form's HTML is not inserted until the modal body actually renders, which happens after LOADED fires (LOADED
    // only guarantees the modal shell itself exists). bodyRendered can fire again on a server-side validation error
    // re-render, so guard against wiring the suggestion widget's delegated listeners onto the same root twice.
    let suggestionInitialised = false;
    modalForm.addEventListener(modalForm.events.LOADED, () => {
        modalForm.modal.getModal().addClass('modal-xl');
        modalForm.modal.getRoot().on(ModalEvents.bodyRendered, () => {
            if (suggestionInitialised) {
                return;
            }
            suggestionInitialised = true;
            initSuggestion(modalForm);
        });
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const reportRegion = trigger.closest(reportSelectors.regions.report);
        if (reportRegion) {
            // Said out loud because the row is often about to disappear: the report opens filtered to the images
            // that need attention, so remediating one takes it out of the table, and a row vanishing with nothing
            // said reads as something having gone wrong. What is reported is the image's resulting classification,
            // not what the table is about to do with the row, which depends on the filters in force.
            const resolved = RESOLVED_STATUSES.includes(event.detail?.status);
            addToast(
                getString(resolved ? 'alttextsavedresolved' : 'alttextsaved', 'report_imagealt'),
                {type: 'success'},
            );
            dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportRegion);
        } else {
            // Not inside a Report Builder system report (for example, the bulk batch page): the reusable partial
            // reload mechanism does not apply there, so fall back to a full reload. No toast here, because the
            // reload would take it away again before it could be read, and that page shows each image's current
            // alternative text in its own column anyway.
            window.location.reload();
        }
    });

    await modalForm.show();
};

/**
 * Initialise the delegated click handler for the given region.
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(EDIT_SELECTOR);
        if (!trigger) {
            return;
        }
        event.preventDefault();
        launchModal(trigger);
    });
};
