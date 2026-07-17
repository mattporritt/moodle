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
 * AI-generated alt text controls for the Tiny image details dialog.
 *
 * @module      tiny_aiplacement/alttext
 * @copyright   2026 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import CustomEvents from 'core/custom_interaction_events';
import Policy from 'core_ai/policy';
import PolicyModal from 'core_ai/policymodal';
import Prefetch from 'core/prefetch';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {MAX_LENGTH_ALT} from 'tiny_media/image/imagehelpers';
import {getContextId, getUserId, isPolicyAgreed} from './options';

const EVENT_IMAGE_DETAILS_READY = 'TinyMediaImageDetailsReady';
const SUPPORTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const TEMPLATE_ALT_TEXT = 'tiny_aiplacement/alttext';

Prefetch.prefetchTemplate(PolicyModal.TEMPLATE);
Prefetch.prefetchTemplate(TEMPLATE_ALT_TEXT);

export default class AltTextGenerator {
    constructor(editor) {
        this.editor = editor;
        this.requestId = 0;
        this.renderId = 0;
    }

    register() {
        this.editor.on(EVENT_IMAGE_DETAILS_READY, ({imageDetails}) => this.setup(imageDetails));
    }

    async setup(imageDetails) {
        this.imageDetails = imageDetails;
        this.altField = imageDetails.root.querySelector('.tiny_image_altentry');
        this.presentationField = imageDetails.root.querySelector('.tiny_image_presentation');
        const counter = imageDetails.root.querySelector('#the-count');
        if (!this.altField || !this.presentationField || !counter) {
            return;
        }

        this.host = document.createElement('div');
        this.host.dataset.region = 'tiny-aiplacement-alt-text';
        counter.insertAdjacentElement('afterend', this.host);
        this.host.addEventListener('click', (event) => this.handleClick(event));
        this.presentationField.addEventListener('change', () => this.updatePresentationState());
        this.altField.addEventListener('input', () => {
            const hasAlt = this.altField.value.trim() !== '';
            if (this.state !== 'generating' && (this.state !== 'idle' || hasAlt !== this.hasAlt)) {
                this.render('idle');
            }
        });
        this.updatePresentationState();
    }

    updatePresentationState() {
        if (this.presentationField.checked) {
            this.cancel();
            this.host.classList.add('d-none');
        } else {
            this.host.classList.remove('d-none');
            this.render('idle');
        }
    }

    async render(state) {
        this.state = state;
        const hasalt = this.altField.value.trim() !== '';
        const renderid = ++this.renderId;
        const html = await Templates.render(TEMPLATE_ALT_TEXT, {
            [state]: true,
            hasalt,
        });
        if (renderid === this.renderId) {
            this.host.innerHTML = html;
        }
    }

    handleClick(event) {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (!action) {
            return;
        }
        event.preventDefault();

        if (action === 'generate-alt-text') {
            if (this.altField.value.trim() !== '') {
                this.render('confirm');
            } else {
                this.ensurePolicyAndGenerate();
            }
        } else if (action === 'replace-alt-text' || action === 'regenerate-alt-text' || action === 'retry-alt-text') {
            this.ensurePolicyAndGenerate();
        } else if (action === 'keep-alt-text') {
            this.render('idle');
        } else if (action === 'cancel-alt-text') {
            this.cancel();
            this.render('idle');
        }
    }

    async ensurePolicyAndGenerate() {
        const userid = getUserId(this.editor);
        Policy.preconfigurePolicyState(userid, isPolicyAgreed(this.editor));
        if (!await Policy.getPolicyStatus(userid)) {
            const policyModal = await PolicyModal.create();
            policyModal.getModal().on(CustomEvents.events.activate, policyModal.getActionSelector('save'), () => {
                this.ensurePolicyAndGenerate();
            });
            return;
        }
        await this.generate();
    }

    cancel() {
        this.requestId++;
        this.abortController?.abort();
    }

    async generate() {
        const requestId = ++this.requestId;
        this.abortController = new AbortController();
        await this.render('generating');

        try {
            const response = await fetch(this.imageDetails.currentUrl, {
                credentials: 'same-origin',
                signal: this.abortController.signal,
            });
            if (!response.ok) {
                throw new Error('Image could not be loaded.');
            }
            const blob = await response.blob();
            if (!SUPPORTED_TYPES.includes(blob.type)) {
                throw new Error('Unsupported image type.');
            }
            const imagedata = await this.readBlob(blob);
            const result = await Ajax.call([{
                methodname: 'aiplacement_editor_describe_image',
                args: {
                    contextid: getContextId(this.editor),
                    imagedata,
                    mimetype: blob.type,
                    descriptivecontext: this.editor.getContent({format: 'text'}).slice(0, 4000),
                },
            }])[0];

            if (requestId !== this.requestId) {
                return;
            }
            if (!result.success || !result.generatedcontent) {
                throw new Error('Image description generation failed.');
            }

            const watermark = await getString('contentwatermark', 'core_ai');
            this.altField.value = this.withWatermark(result.generatedcontent, watermark);
            await this.imageDetails.handleKeyupCharacterCount();
            await this.render('success');
            this.altField.focus();
        } catch (error) {
            if (requestId === this.requestId && error.name !== 'AbortError') {
                await this.render('error');
            }
        }
    }

    readBlob(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.addEventListener('load', () => resolve(reader.result.split(',')[1]));
            reader.addEventListener('error', () => reject(reader.error));
            reader.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
            this.abortController.signal.addEventListener('abort', () => reader.abort(), {once: true});
            reader.readAsDataURL(blob);
        });
    }

    withWatermark(description, watermark) {
        const suffix = ` - ${watermark}`;
        if (description.length + suffix.length <= MAX_LENGTH_ALT) {
            return description + suffix;
        }
        const ellipsis = '...';
        return description.substring(0, MAX_LENGTH_ALT - suffix.length - ellipsis.length) + ellipsis + suffix;
    }
}
