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
 * AI provider model selection handler.
 *
 * Every Claude model shares the same generation settings fields (endpoint, max_tokens,
 * temperature), so switching models resubmits the form to let the server re-evaluate which
 * of those fields apply, then restores this model's own previously stored values into them
 * (see MDL-89680) instead of leaving the previously selected model's values in place.
 *
 * @module     aiprovider_anthropic/modelchooser
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {populateFields, clearFields} from 'core_ai/helper';

const CUSTOM_MODEL = 'custom';

const Selectors = {
    fields: {
        selector: '[data-modelchooser-field="selector"]',
        updateButton: '[data-modelchooser-field="updateButton"]',
        modelSettingsContainer: 'id_modelsettingsheadercontainer',
        endpoint: 'input[name="endpoint"]',
        model: 'input[name="model"]',
        custommodel: 'input[name="custommodel"]',
    },
};

/**
 * Restore the endpoint field to this model's stored value, or its default if it has none.
 *
 * @param {Object|undefined} modelSettings This model's stored settings, if any.
 */
const restoreEndpoint = (modelSettings) => {
    const endpointField = document.querySelector(Selectors.fields.endpoint);
    if (!endpointField) {
        return;
    }
    endpointField.value = modelSettings?.endpoint ?? endpointField.getAttribute('data-defaultendpoint');
};

/**
 * Initialise the AI provider model chooser.
 */
export const init = () => {
    const modelSelector = document.querySelector(Selectors.fields.selector);
    if (!modelSelector) {
        return;
    }

    // If we have stored settings for the current model, restore them into their fields.
    const storedModelSettings = JSON.parse(modelSelector.getAttribute('data-storedmodelsettings'));
    const modelSettings = storedModelSettings[modelSelector.value];
    const containerId = Selectors.fields.modelSettingsContainer;

    if (modelSettings) {
        populateFields(modelSettings, containerId);
    } else {
        clearFields(containerId);
    }
    restoreEndpoint(modelSettings);

    modelSelector.addEventListener('change', e => {
        const form = e.target.closest('form');
        const selectedModel = e.target.value;

        // Keep the hidden model field in step with the selection. For the custom option the
        // model name comes from the text field the admin fills in instead.
        const modelField = form.querySelector(Selectors.fields.model);
        if (modelField) {
            if (selectedModel === CUSTOM_MODEL) {
                const customModelField = form.querySelector(Selectors.fields.custommodel);
                modelField.value = customModelField ? customModelField.value : '';
            } else {
                modelField.value = selectedModel;
            }
        }

        form.querySelector(Selectors.fields.updateButton).click();
    });
};
