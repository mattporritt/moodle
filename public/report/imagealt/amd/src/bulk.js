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
 * Submit Report Builder checkbox selections through the containing bulk action form.
 *
 * @module     report_imagealt/bulk
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CustomEvents from 'core/custom_interaction_events';
import Policy from 'core_ai/policy';
import PolicyModal from 'core_ai/policymodal';
import Prefetch from 'core/prefetch';
import * as tableEvents from 'core_table/local/dynamic/events';

Prefetch.prefetchTemplate(PolicyModal.TEMPLATE);

// The same toggle group core's own report bulk actions watch, so both the per-row boxes and the select-all
// toggler are covered.
const SELECTORS = {
    checkbox: 'input[type="checkbox"][data-togglegroup="report-select-all"][data-toggle="target"]',
    toggler: 'input[type="checkbox"][data-togglegroup="report-select-all"][data-toggle="toggler"]',
    checked: '[data-togglegroup="report-select-all"][data-toggle="target"]:checked',
};

/**
 * Return the occurrence IDs of the currently ticked report rows.
 *
 * @returns {string[]} The selected occurrence IDs.
 */
const getSelected = () => Array.from(document.querySelectorAll(SELECTORS.checked))
    .map(checkbox => checkbox.value)
    .filter(value => /^\d+$/.test(value));

/**
 * Initialise bulk selection submission.
 *
 * @param {string} formId Form ID.
 * @param {number} userId Current user ID.
 * @param {boolean} policyAccepted Server-known policy state.
 */
export const init = (formId, userId, policyAccepted) => {
    const form = document.getElementById(formId);
    const generate = form?.querySelector('button[name="generate"]');
    if (!form || !generate) {
        return;
    }

    Policy.preconfigurePolicyState(userId, policyAccepted);

    // Generating for nothing is not a meaningful action, so the button stays disabled until at least one row is
    // ticked. It is rendered disabled server-side, so this only ever has to enable it.
    const updateSelectionState = () => {
        generate.disabled = getSelected().length === 0;
    };
    document.addEventListener('change', event => {
        if (event.target.matches(SELECTORS.checkbox) || event.target.matches(SELECTORS.toggler)) {
            updateSelectionState();
        }
    });
    // Paging, sorting and filtering all replace the table markup, which drops any previous selection with it.
    document.addEventListener(tableEvents.tableContentRefreshed, updateSelectionState);
    updateSelectionState();

    let approvedSubmission = false;
    form.addEventListener('submit', async event => {
        const selected = getSelected();
        if (selected.length === 0) {
            event.preventDefault();
            return;
        }
        form.elements.occurrenceids.value = selected.join(',');

        if (approvedSubmission) {
            approvedSubmission = false;
            return;
        }

        event.preventDefault();
        // Read from the event now rather than inside submit(), which runs after this handler has already returned.
        const {submitter} = event;
        const submit = () => {
            approvedSubmission = true;
            // A form ignores submission requests while it is still dispatching its own submit event, and when the
            // policy is already accepted this runs in a microtask that falls inside that dispatch. Called straight
            // out it is silently dropped, so the first click only ever arms the second one. Deferring to a fresh
            // task lets the dispatch finish first.
            setTimeout(() => form.requestSubmit(submitter));
        };
        const ensurePolicyAndSubmit = async() => {
            if (await Policy.getPolicyStatus(userId)) {
                submit();
                return;
            }

            const policyModal = await PolicyModal.create();
            policyModal.getModal().on(
                CustomEvents.events.activate,
                policyModal.getActionSelector('save'),
                // Wait for acceptance to be confirmed written before submitting: submitting navigates away, and a
                // still-in-flight write to record it would be aborted by that navigation, leaving the click believed
                // accepted here while the site itself never learned that.
                async() => {
                    await Policy.acceptPolicy();
                    submit();
                },
            );
        };
        await ensurePolicyAndSubmit();
    });
};
