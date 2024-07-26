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
 * AI Subsystem action functions.
 *
 * @module     core/ai/action
 * @copyright  Meirza <meirza.arson@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      4.5
 */


import {refreshTableContent} from 'core_table/dynamic';
import * as Selectors from 'core_table/local/dynamic/selectors';
import {call as fetchMany} from 'core/ajax';
import Pending from 'core/pending';
import {fetchNotifications} from 'core/notification';

let watching = false;

export default class {
    /**
     * @property {function[]} clickHandlers a list of handlers to call on click.
     */
    clickHandlers = [];

    constructor() {
        this.addClickHandler(this.handleStateToggle);
        this.registerEventListeners();
    }

    /**
     * Initialise an instance of the class.
     *
     * This is just a way of making it easier to initialise an instance of the class from PHP.
     */
    static init() {
        if (watching) {
            return;
        }
        watching = true;
        new this();
    }

    /**
     * Add a click handler to the list of handlers.
     *
     * @param {Function} handler A handler to call on a click event
     */
    addClickHandler(handler) {
        this.clickHandlers.push(handler.bind(this));
    }

    /**
     * Register the event listeners for this instance.
     */
    registerEventListeners() {
        document.addEventListener('click', function(e) {
            const tableRoot = this.getTableRoot(e);

            if (!tableRoot) {
                return;
            }

            this.clickHandlers.forEach((handler) => handler(tableRoot, e));
        }.bind(this));
    }

    /**
     * Get the table root from an event.
     *
     * @param {Event} e
     * @returns {HTMLElement|bool}
     */
    getTableRoot(e) {
        const tableRoot = e.target.closest(Selectors.main.region);
        if (!tableRoot) {
            return false;
        }

        return tableRoot;
    }

    /**
     * Set the plugin state (enabled or disabled)
     *
     * @param {string} methodname The web service to call
     * @param {string} plugin The name of the plugin to set the state for
     * @param {string} action The name of the plugin to set the state for
     * @param {number} state The state to set
     * @returns {Promise}
     */
    setActionState(methodname, plugin, action, state) {
        return fetchMany([{
            methodname,
            args: {
                plugin,
                action,
                state,
            },
        }])[0];
    }

    /**
     * Handle state toggling.
     *
     * @param {HTMLElement} tableRoot
     * @param {Event} e
     */
    async handleStateToggle(tableRoot, e) {
        const stateToggle = e.target.closest('[data-action="togglestate"][data-toggle-method]');
        if (stateToggle) {
            e.preventDefault();
            const pendingPromise = new Pending('core_table/dynamic:togglestate');
            await this.setActionState(
                stateToggle.dataset.toggleMethod,
                stateToggle.dataset.plugin,
                stateToggle.dataset.value,
                stateToggle.dataset.state === '1' ? 0 : 1,
            );
            const [updatedRoot] = await Promise.all([
                refreshTableContent(tableRoot, false, 'core_ai_get_action'),
                fetchNotifications(),
            ]);

            // Refocus on the link that as pressed in the first place.
            stateToggle.dataset.value = stateToggle.dataset.value.replace(/\\/g, '\\\\');
            updatedRoot.querySelector(`[data-action="togglestate"][data-value="${stateToggle.dataset.value}"]`).focus();
            pendingPromise.resolve();
        }
    }

}
