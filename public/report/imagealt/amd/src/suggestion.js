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
 * AI alternative text suggestion controls for the occurrence review modal.
 *
 * Every state of the workflow renders the same report_imagealt/suggestion template into the same region the server
 * first rendered it into, so the idle, confirm, generating, ready and failed states are identical to the ones
 * tiny_aiplacement renders in the editor's own image details dialog.
 *
 * Generate/regenerate/discard are deliberately plain AJAX actions kept outside the dynamic form's own submit
 * lifecycle: registering them as no-submit form buttons would cause core_form_dynamic_form to construct (and run
 * definition() on) the form twice per click, which would call the AI provider and write suggestion records twice.
 *
 * @module     report_imagealt/suggestion
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import CustomEvents from 'core/custom_interaction_events';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Policy from 'core_ai/policy';
import PolicyModal from 'core_ai/policymodal';
import Prefetch from 'core/prefetch';
import Templates from 'core/templates';
import {getString} from 'core/str';

const TEMPLATE_SUGGESTION = 'report_imagealt/suggestion';

Prefetch.prefetchTemplate(PolicyModal.TEMPLATE);
Prefetch.prefetchTemplate(TEMPLATE_SUGGESTION);

const SELECTORS = {
    alttext: '[data-region="report-imagealt-alttext"]',
    count: '[data-region="report-imagealt-count"]',
    currentcount: '[data-region="report-imagealt-currentcount"]',
    maxlengthfeedback: '[data-region="report-imagealt-maxlength-feedback"]',
    region: '[data-region="report-imagealt-suggestion"]',
    decorative: 'input[type="checkbox"][name="decorative"]',
    occurrenceid: 'input[name="id"]',
};

/**
 * Wire up AI suggestion generation/discard for one review modal instance.
 *
 * @param {ModalForm} modalForm The modal form instance hosting the review form.
 */
export default async(modalForm) => {
    const root = modalForm.modal.getRoot()[0];
    const alttext = root.querySelector(SELECTORS.alttext);
    const currentcount = root.querySelector(SELECTORS.currentcount);
    const maxlengthfeedback = root.querySelector(SELECTORS.maxlengthfeedback);
    const region = root.querySelector(SELECTORS.region);
    const decorative = root.querySelector(SELECTORS.decorative);
    if (!alttext) {
        return;
    }

    // Toggled so the feedback text differs each time the limit is hit, forcing screen readers to re-announce it
    // (matching Tiny's own image details dialog, which uses the same trick for the same reason).
    let toggleMaxlengthFeedbackSuffix = false;

    const updateCharCount = async() => {
        if (!currentcount) {
            return;
        }
        currentcount.textContent = alttext.value.length;
        if (!maxlengthfeedback || alttext.value.length < alttext.maxLength) {
            return;
        }
        maxlengthfeedback.textContent = await getString('maxlengthreached', 'core', alttext.maxLength)
            + (toggleMaxlengthFeedbackSuffix ? '' : '.');
        toggleMaxlengthFeedbackSuffix = !toggleMaxlengthFeedbackSuffix;
    };
    alttext.addEventListener('input', updateCharCount);

    // Decorative images need no description at all, so the whole alternative text control group goes away while
    // that box is ticked. Quickform's own hideIf only reaches the textarea, which would otherwise leave the
    // character count and the AI workflow stranded below an empty space.
    if (decorative) {
        const count = root.querySelector(SELECTORS.count);
        const updateDecorativeState = () => {
            count?.classList.toggle('d-none', decorative.checked);
            region?.classList.toggle('d-none', decorative.checked);
        };
        decorative.addEventListener('change', updateDecorativeState);
        updateDecorativeState();
    }

    // Occurrences that are not AI eligible get no region at all, so the character counter above is all there is
    // to wire up for them.
    if (!region) {
        return;
    }

    const occurrenceid = root.querySelector(SELECTORS.occurrenceid)?.value;

    // The server already rendered whichever state the stored suggestion is in, so the starting state is read back
    // out of that markup rather than re-rendered, which would discard a queued, ready or failed suggestion.
    let state = region.querySelector('.alert') ? 'server' : 'idle';
    // Bumped by every request and by cancelling, so a response that is no longer the one being awaited is ignored.
    let requestId = 0;
    let renderId = 0;

    // Set by the server, which is the only side that can tell whether a provider is configured and enabled for this
    // context. Without it every state rendered here would offer to write another description, including on a site
    // whose provider has since been turned off, where the request cannot be served.
    const canregenerate = region.dataset.canregenerate === '1';

    // What to put back in the field if a description is rejected. It starts as the image's own alternative text,
    // because the field opens showing any description already waiting to be reviewed rather than that text, and is
    // replaced by whatever the field holds each time a new description is asked for, so unsaved edits made before
    // generating survive rejecting the result.
    let altBeforeSuggestion = region.dataset.storedalttext ?? '';

    // Descriptions written while this dialog has been open. Leaving without saving throws these away, because a
    // description nobody accepted is a change of mind, and one left behind is worse than none: the report reports it
    // as waiting to be reviewed, with no way back to it from a dialog that has closed.
    //
    // Only the ones written here. A description that was already waiting when the dialog opened belongs to whatever
    // produced it - a bulk request being worked through on the batch page - and closing a dialog opened to look at
    // it must not throw that away.
    const generatedHere = new Set();
    let saved = false;

    const render = async(newstate, extra = {}) => {
        state = newstate;
        const thisrender = ++renderId;
        const html = await Templates.render(TEMPLATE_SUGGESTION, {
            occurrenceid,
            [newstate]: true,
            hasalt: alttext.value.trim() !== '',
            canregenerate,
            ...extra,
        });
        if (thisrender === renderId) {
            region.innerHTML = html;
            focusAfterRender(newstate);
        }
    };

    // Every state transition above replaces region.innerHTML wholesale, which destroys whatever control was
    // focused (typically the button just activated) and drops keyboard/screen-reader focus to the document body.
    // Restoring it explicitly after each render keeps a keyboard or screen-reader user in a predictable place while
    // working through generate/regenerate/discard/confirm/cancel, instead of losing their position on every action.
    const focusAfterRender = (newstate) => {
        // Returning to idle is returning control to the field itself, so that is where focus belongs, whether or
        // not this state renders a generate button (it does not, on a site with no provider to regenerate with).
        if (newstate === 'idle') {
            alttext.focus();
            return;
        }
        const focusable = region.querySelector('button, [href], input, select, textarea, [tabindex]');
        if (focusable) {
            focusable.focus();
            return;
        }
        // No control was rendered at all (for example a provider-less "waiting" state with only a status message).
        // Focusing the live region itself still keeps focus somewhere sighted and reported, rather than silently at
        // the document body.
        region.setAttribute('tabindex', '-1');
        region.focus();
    };

    // Keep the button's label in step with whether generating would overwrite something the user has typed.
    alttext.addEventListener('input', () => {
        if (state === 'idle') {
            render('idle');
        }
    });

    const generate = async() => {
        const thisrequest = ++requestId;
        altBeforeSuggestion = alttext.value;
        await render('generating');
        try {
            const result = await Ajax.call([{
                methodname: 'report_imagealt_generate_suggestion',
                args: {occurrenceid},
            }])[0];
            if (thisrequest !== requestId) {
                return;
            }
            if (result.status === 'ready') {
                alttext.value = result.suggestiontext;
                await updateCharCount();
                generatedHere.add(result.suggestionid);
                await render('success', {suggestionid: result.suggestionid});
            } else {
                await render('error', {errormessage: result.errormessage});
            }
        } catch (error) {
            if (thisrequest === requestId) {
                await render('error', {errormessage: error.message});
            }
        }
    };

    const ensurePolicyAndGenerate = async() => {
        if (!await Policy.getPolicyStatus(M.cfg.userId)) {
            const policyModal = await PolicyModal.create();
            policyModal.getModal().on(
                CustomEvents.events.activate,
                policyModal.getActionSelector('save'),
                () => ensurePolicyAndGenerate(),
            );
            return;
        }
        await generate();
    };

    const discardSuggestion = (suggestionid) => Ajax.call([{
        methodname: 'report_imagealt_discard_suggestion',
        args: {suggestionid},
    }])[0];

    // Saving settles every description this dialog wrote, on the server, so there is nothing left for closing to
    // throw away. Watched rather than assumed, because the form can also fail validation and stay open.
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        saved = true;
    });

    // Covers every way out that is not saving: Cancel, the close button, Escape, and clicking away from the dialog.
    // Closing the tab outright cannot be caught here and leaves the description behind, which is the one case this
    // does not reach.
    modalForm.modal.getRoot().on(ModalEvents.hidden, () => {
        if (saved) {
            return;
        }
        generatedHere.forEach((suggestionid) => {
            discardSuggestion(suggestionid).catch(() => {
                // Nothing useful can be said to somebody who has already closed the dialog, and the description is
                // theirs to deal with from the report if this fails.
                return;
            });
        });
        generatedHere.clear();
    });

    const discard = async(button) => {
        button.disabled = true;
        try {
            await discardSuggestion(button.dataset.suggestionid);
            generatedHere.delete(Number(button.dataset.suggestionid));
            // Taken out of the field as well as marked rejected. Left in, it could still be saved onto the image by
            // pressing Save, publishing the description the reviewer just rejected - and recorded as their own text,
            // since keeping a description unedited is what tells the two apart.
            alttext.value = altBeforeSuggestion;
            await updateCharCount();
            await render('idle');
        } catch (error) {
            button.disabled = false;
            Notification.exception(error);
        }
    };

    region.addEventListener('click', (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (!action) {
            return;
        }
        event.preventDefault();

        if (action === 'report-imagealt-generate') {
            // Regenerating from the ready state is already an explicit choice, so only an idle field the user has
            // typed into needs the overwrite confirmation.
            if (state === 'idle' && alttext.value.trim() !== '') {
                render('confirm');
            } else {
                ensurePolicyAndGenerate();
            }
        } else if (action === 'report-imagealt-replace') {
            ensurePolicyAndGenerate();
        } else if (action === 'report-imagealt-keep') {
            render('idle');
        } else if (action === 'report-imagealt-cancel') {
            requestId++;
            render('idle');
        } else if (action === 'report-imagealt-discard') {
            discard(event.target.closest('[data-action]'));
        }
    });
};
