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
 * Tiny AI UI.
 *
 * @module      tiny_aiplacement/ui
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalEvents from 'core/modal_events';
import ImageModal from 'tiny_aiplacement/imagemodal';
import {getContextId} from 'tiny_aiplacement/options';
import Ajax from 'core/ajax';
import {loadingMessages} from 'tiny_aiplacement/loading';
import {getString} from 'core/str';
import Templates from 'core/templates';

let responseObj = null;

/**
 * Display the modal when the AI button is clicked.
 *
 * @param {TinyMCE.editor} editor The tinyMCE editor instance.
 */
export const displayImageModal = async(editor) => {
    const modalObject = await ImageModal.create({
        type: ImageModal.TYPE,
        templateContext: getTemplateContext(editor),
        xlarge: true,
    });

    const modalroot = await modalObject.getRoot();
    const root = modalroot[0];

    modalObject.show();
    modalroot.on(ModalEvents.hidden, () => {
        modalObject.destroy();
    });

    // Add the event listener for the button click events.
    root.addEventListener('click', (e) => {
        const submitBtn = e.target.closest('[data-action="generate"]');
        const insertBtn = e.target.closest('[data-action="inserter"]');
        const cancelBtn = e.target.closest('[data-action="cancel"]');
        if (submitBtn) {
            e.preventDefault();
            handleSubmit(editor, root, submitBtn);
        } else if (insertBtn) {
            e.preventDefault();
            handleInsert(editor, root);
            modalObject.destroy();
        } else if (cancelBtn) {
            modalObject.destroy();
        }
    });

    const generateBtn = root.querySelector('#' + editor.id + '_tiny_aiplacement_generatebutton');
    const promptArea = root.querySelector('#' + editor.id + '_tiny_aiplacement_imageprompt');

    // Add the event listener for the prompt text area.
    promptArea.addEventListener('input', () => {
        // Enable the generate button if there is text in the prompt area.
        // Disable the generate button if there is no text in the prompt area.
        generateBtn.disabled = promptArea.value.trim() === '';
    });
};

/**
 * Get the context to use in the modal template.
 *
 * @param {TinyMCE.editor} editor
 * @returns {Object}
 */
const getTemplateContext = (editor) => {
    return {
        elementid: editor.id,
    };
};

/**
 * Handle the submit action.
 *
 * @param {TinyMCE.editor} editor The tinyMCE editor instance.
 * @param {Object} root The root element of the modal.
 * @param {Object} submitBtn The submit button element.
 */
const handleSubmit = async(editor, root, submitBtn) => {
    // Display the loading spinner.
    displayLoading(editor.id, root, submitBtn);

    const displayArgs = getDisplayArgs(editor, root);

    // Pass the prompt text to the webservice using Ajax.
    const request = {
        methodname: 'aiplacement_tinymce_generate_image',
        args: displayArgs
    };

    // Try making the ajax call and catch any errors.
    try {
        responseObj = await Ajax.call([request])[0];
        if (responseObj.error) {
            // If there is an error, display an error message to the user.
            handleGenerationError(editor.id, root, submitBtn);
        } else {
            // If there is no error, display the image to the user.
            const imageDisplayContainer = root.querySelector('#' + editor.id + '_image_display_container');

            // Add image to the image display container.
            imageDisplayContainer.innerHTML = await Templates.render(
                'tiny_aiplacement/image',
                {url: responseObj.drafturl});

            // Don't hide the loading spinner until the image has been displayed.
            const img = new Image();
            img.src = responseObj.drafturl;
            img.onload = () => {
                hideLoading(editor.id, root, submitBtn);
            };

            window.console.log(responseObj);
        }
    } catch (error) {
        // This means an unhandled error has occurred.
        // Shouldn't happen, but if it does, display an error message to the user, so they can continue.

        handleGenerationError(editor.id, root, submitBtn);
    }
};

/**
 * Display the loading action in the modal.
 *
 * @param {Integer} editorId The id of the editor.
 * @param {Object} root The root element of the modal.
 * @param {Object} submitBtn The submit button element.
 */
const displayLoading = async(editorId, root, submitBtn) => {
    const loadingSpinnerDiv = root.querySelector('#' + editorId + "_tiny_aiplacement_spinner");
    const overlayDiv = root.querySelector('#' + editorId + '_tiny_aiplacement_overlay');
    const blurDiv = root.querySelector('#' + editorId + '_tiny_aiplacement_blur');
    const loadingTextDiv = root.querySelector('#' + editorId + "_tiny_aiplacement_loading_text");

    loadingMessages(loadingTextDiv);
    loadingSpinnerDiv.classList.remove('hidden');
    overlayDiv.classList.remove('hidden');
    blurDiv.classList.add('tiny-aiplacement-blur');
    submitBtn.innerHTML = await getString('generating', 'tiny_aiplacement');
    submitBtn.disabled = true;
};

/**
 * Hide the loading action in the modal.
 *
 * @param {Integer} editorId The id of the editor.
 * @param {Object} root The root element of the modal.
 * @param {Object} submitBtn The submit button element.
 */
const hideLoading = async(editorId, root, submitBtn) => {
    const loadingSpinnerDiv = root.querySelector('#' + editorId + "_tiny_aiplacement_spinner");
    const overlayDiv = root.querySelector('#' + editorId + '_tiny_aiplacement_overlay');
    const blurDiv = root.querySelector('#' + editorId + '_tiny_aiplacement_blur');

    loadingSpinnerDiv.classList.add('hidden');
    overlayDiv.classList.add('hidden');
    blurDiv.classList.remove('tiny-aiplacement-blur');
    submitBtn.innerHTML = await getString('regenerate', 'tiny_aiplacement');
    submitBtn.disabled = false;
};

/**
 * Handle the insert action.
 *
 * @param {TinyMCE.editor} editor The tinyMCE editor instance.
 * @param {Object} root The root element of the modal.
 */
const handleInsert = async(editor, root) => {
    //TODO: Insert the generated content into the editor.
    window.console.log(editor);
    window.console.log(root);
};

/**
 * Handle a generation error.
 *
 * @param {Integer} editorId The id of the editor.
 * @param {Object} root The root element of the modal.
 * @param {Object} submitBtn The submit button element.
 */
const handleGenerationError = async(editorId, root, submitBtn) => {
    const imageContainer = root.querySelector('#' + editorId + '_tiny_aiplacement_generate_image');
    const faImage = root.querySelector('#' + editorId + "_tiny_aiplacement_fa_image");
    const faImageText = root.querySelector('#' + editorId + '_tiny_aiplacement_fa_image_text');

    imageContainer.classList.add('alert-danger');
    faImage.classList.add('fa-ban');
    faImage.classList.remove('fa-image');
    faImageText.innerHTML = await getString('errorgen', 'tiny_aiplacement');
    hideLoading(editorId, root, submitBtn);
};

/**
 * Get the value of a checkbox.
 *
 * @param {String} checkboxId The id of the checkbox.
 * @param {Boolean} defaultValue The default value of the checkbox.
 */
const getCheckboxValue = (checkboxId, defaultValue = false) => {
    const checkbox = document.getElementById(checkboxId);
    if (checkbox) {
        return checkbox.checked;
    }
    return defaultValue;
};

/**
 * Get the value of the selected radio button.
 *
 * @param {String} radioName The name of the radio button group.
 * @param {String} defaultValue The default value of the radio button.
 */
const getSelectedRadioValue = (radioName, defaultValue = null) => {
    const radios = document.getElementsByName(radioName);
    for (const radio of radios) {
        if (radio.checked) {
            return radio.value;
        }
    }
    return defaultValue; // Return default value if no radio button is selected or if elements are not found.
};

/**
 * Get the display args for the image.
 *
 * @param {TinyMCE.editor} editor The tinyMCE editor instance.
 * @param {Object} root The root element of the modal.
 */
const getDisplayArgs = (editor, root) => {
    const contextId = getContextId(editor); // Get the context id.
    const promptText = root.querySelector('#' + editor.id + '_tiny_aiplacement_imageprompt').value;

    const imageQualitySwitchId = `${editor.id}_image_quality_switch`;
    const imageStyleSwitchId = `${editor.id}_image_style_switch`;

    const aspectRatio = getSelectedRadioValue('aspect-ratio', 'square');
    const imageQuality = getCheckboxValue(imageQualitySwitchId, false);
    const imageStyle = getCheckboxValue(imageStyleSwitchId, false);

    return {
        contextid: contextId,
        prompttext: promptText,
        aspectratio: aspectRatio,
        quality: imageQuality ? 'hd' : 'standard',
        style: imageStyle ? 'vivid' : 'natural',
    };
};
