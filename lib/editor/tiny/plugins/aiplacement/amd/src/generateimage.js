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
 * Tiny AI generate images.
 *
 * @module      tiny_aiplacement/generateimage
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalEvents from 'core/modal_events';
import ImageModal from 'tiny_aiplacement/imagemodal';
import Ajax from 'core/ajax';
import {getString} from 'core/str';
import Templates from 'core/templates';
import AiMediaImage from './mediaimage';
import {getContextId} from 'tiny_aiplacement/options';
import GenerateBase from 'tiny_aiplacement/generatebase';

export default class GenerateImage extends GenerateBase {

    /**
     * The modal configuration object.
     * @type {{xlarge: boolean, type: *}}
     */
    static modalConfig = {
        type: ImageModal.TYPE,
        xlarge: true,
    };

    /**
     * Class constructor.
     *
     * @param {TinyMCE.editor} editor The tinyMCE editor instance.
     */
    constructor(editor) {
        super(editor);
        this.imageURL = null;
    }

    /**
     * Set up the base image generation modal with default body content.
     *
     * @returns {ImageModal} The image modal object.
     */
    setupModal = async() => {
        const modalObject = await ImageModal.create(GenerateImage.modalConfig);
        const modalRoot = await modalObject.getRoot();

        modalRoot.on(ModalEvents.hidden, () => {
            modalObject.destroy();
        });

        return modalObject;
    };

    /**
     * Set up the image modal.
     *
     * @param {ImageModal} modalObject The modal object.
     * @param {object} templateContext The template context.
     * @returns {Promise<void>} A promise that resolves when the modal is set up.
     */
    setupContentModal = async(modalObject, templateContext) => {
        const [loadingBody, imageBody, imageFooter] = await Promise.all([
            Templates.render('tiny_aiplacement/loading', templateContext),
            Templates.render('tiny_aiplacement/modalbodyimage', templateContext),
            Templates.render('tiny_aiplacement/modalfooterimage', templateContext)
        ]);

        modalObject.setBody(loadingBody + imageBody);
        modalObject.setFooter(imageFooter);
        this.addContentEventListeners(modalObject);
    };

    /**
     * Handle click events within the image modal.
     *
     * @param {Event} e - The click event object.
     * @param {ImageModal} modalObject - The image modal object.
     * @param {HTMLElement} root - The root element of the modal.
     */
    handleContentModalClick = (e, modalObject, root) => {
        const actions = {
            generate: () => this.handleSubmit(root, e.target),
            inserter: () => this.handleInsert(modalObject),
            cancel: () => modalObject.destroy()
        };

        const actionKey = Object.keys(actions).find(key => e.target.closest(`[data-action="${key}"]`));
        if (actionKey) {
            e.preventDefault();
            actions[actionKey]();
        }
    };

    /**
     * Set up the prompt area in the modal, adding necessary event listeners.
     *
     * @param {HTMLElement} root - The root element of the modal.
     */
    setupPromptArea = (root) => {
        const generateBtn = root.querySelector(`#${this.editor.id}_tiny_aiplacement_generatebutton`);
        const promptArea = root.querySelector(`#${this.editor.id}_tiny_aiplacement_imageprompt`);

        promptArea.addEventListener('input', () => {
            generateBtn.disabled = promptArea.value.trim() === '';
        });
    };

    /**
     * Handle the submit action.
     *
     * @param {Object} root The root element of the modal.
     * @param {Object} submitBtn The submit button element.
     */
    handleSubmit = async(root, submitBtn) => {
        await this.displayLoading(root, submitBtn);

        const displayArgs = this.getDisplayArgs(root);
        const request = {
            methodname: 'aiplacement_tinymce_generate_image',
            args: displayArgs
        };

        try {
            this.responseObj = await Ajax.call([request])[0];
            if (this.responseObj.error) {
                this.handleGenerationError(root, submitBtn);
            } else {
                await this.displayGeneratedImage(root);
                this.hideLoading(root, submitBtn);
                window.console.log(this.responseObj);
            }
        } catch (error) {
            this.handleGenerationError(root, submitBtn);
        }
    };

    /**
     * Handle the insert action.
     *
     * @param {Object} modalObject The modal object.
     */
    handleInsert = async(modalObject) => {
        const mediaImage = new AiMediaImage(this.editor, this.imageURL);
        mediaImage.loadPreviewImage(this.imageURL);
        await mediaImage.displayDialogue();
        modalObject.destroy();
    };

    /**
     * Handle a generation error.
     *
     * @param {Object} root The root element of the modal.
     * @param {Object} submitBtn The submit button element.
     */
    handleGenerationError = async(root, submitBtn) => {
        const imageContainer = root.querySelector(`#${this.editor.id}_tiny_aiplacement_generate_image`);
        const faImage = root.querySelector(`#${this.editor.id}_tiny_aiplacement_fa_image`);
        const faImageText = root.querySelector(`#${this.editor.id}_tiny_aiplacement_fa_image_text`);

        imageContainer.classList.add('alert-danger');
        faImage.classList.add('fa-ban');
        faImage.classList.remove('fa-image');
        faImageText.innerHTML = await getString('errorgenimage', 'tiny_aiplacement');
        this.hideLoading(root, submitBtn);
    };

    /**
     * Display the generated image in the modal.
     *
     * @param {HTMLElement} root - The root element of the modal.
     */
    displayGeneratedImage = async(root) => {
        const imageDisplayContainer = root.querySelector(`#${this.editor.id}_image_display_container`);
        const insertBtn = root.querySelector('[data-action="inserter"]');
        // Set the draft URL as it's used elsewhere.
        this.imageURL = this.responseObj.drafturl;

        // Render the image template and insert it into the modal.
        imageDisplayContainer.innerHTML = await Templates.render('tiny_aiplacement/image',
            {url: this.responseObj.drafturl, elementid: this.editor.id});
        const imagelement = root.querySelector(`#${this.editor.id}_tiny_generated_image`);

        return new Promise((resolve, reject) => {
            imagelement.onload = () => {
                insertBtn.classList.remove('hidden');
                resolve(); // Resolve the promise when the image is loaded.
            };
            imagelement.onerror = (error) => {
                reject(error); // Reject the promise if there is an error loading the image.
            };
        });
    };

    /**
     * Get the display args for the image.
     *
     * @param {Object} root The root element of the modal.
     */
    getDisplayArgs = (root) => {
        const contextId = getContextId(this.editor);
        const promptText = root.querySelector(`#${this.editor.id}_tiny_aiplacement_imageprompt`).value;

        const imageQualitySwitchId = `${this.editor.id}_image_quality_switch`;
        const imageStyleSwitchId = `${this.editor.id}_image_style_switch`;

        const aspectRatio = this.getSelectedRadioValue('aspect-ratio', 'square');
        const imageQuality = this.getCheckboxValue(imageQualitySwitchId, false);
        const imageStyle = this.getCheckboxValue(imageStyleSwitchId, false);

        return {
            contextid: contextId,
            prompttext: promptText,
            aspectratio: aspectRatio,
            quality: imageQuality ? 'hd' : 'standard',
            numimages: 1,
            style: imageStyle ? 'vivid' : 'natural'
        };
    };

    /**
     * Get the value of a checkbox.
     *
     * @param {String} checkboxId The id of the checkbox.
     * @param {Boolean} defaultValue The default value of the checkbox.
     */
    getCheckboxValue = (checkboxId, defaultValue = false) => {
        const checkbox = document.getElementById(checkboxId);
        return checkbox ? checkbox.checked : defaultValue;
    };

    /**
     * Get the value of the selected radio button.
     *
     * @param {String} radioName The name of the radio button group.
     * @param {String} defaultValue The default value of the radio button.
     */
    getSelectedRadioValue = (radioName, defaultValue = null) => {
        const radios = document.getElementsByName(radioName);
        for (const radio of radios) {
            if (radio.checked) {
                return radio.value;
            }
        }
        return defaultValue;
    };
}
