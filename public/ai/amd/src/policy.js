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

import {getPolicyStatus, setPolicyStatus} from "./repository";

/**
 * The Javascript module to handle the policy acceptance.
 *
 * @module     core_ai/policy
 * @copyright  2024 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class {
    static #policyAcceptedFor = {};
    static #acceptancePromise = null;

    static preconfigurePolicyState(userid, state) {
        if (!this.#policyAcceptedFor.hasOwnProperty(userid)) {
            this.#policyAcceptedFor[userid] = state;
        }
    }

    /**
     * Get the policy status for a user.
     *
     * @param {Number} userid The user ID.
     * @return {Promise<Object>} The policy status.
     */
    static async getPolicyStatus(userid) {
        if (this.#policyAcceptedFor[userid]) {
            return this.#policyAcceptedFor[userid];
        }

        const accepted = await getPolicyStatus(userid);

        this.#policyAcceptedFor[userid] = accepted.status;

        return accepted.status;
    }

    /**
     * Record policy acceptance for the current user and wait for it to be confirmed.
     *
     * The local status is only flipped once the write is confirmed, rather than optimistically beforehand, so a
     * caller that awaits this before doing something irreversible (such as submitting a form that navigates away)
     * cannot proceed while the acceptance is still only believed true on the client. Concurrent callers (for
     * example, more than one listener reacting to the same modal save event) share a single in-flight request
     * instead of writing the acceptance record twice, which the database would otherwise reject.
     *
     * @return {Promise}
     */
    static acceptPolicy() {
        if (!this.#acceptancePromise) {
            // Two-argument then() rather than a trailing catch()/finally(), because setPolicyStatus() returns
            // whatever core/ajax hands back, which is not guaranteed to be a native Promise supporting finally().
            this.#acceptancePromise = setPolicyStatus(M.cfg.contextid).then(
                (result) => {
                    this.#policyAcceptedFor[M.cfg.userId] = true;
                    this.#acceptancePromise = null;
                    return result;
                },
                (error) => {
                    this.#acceptancePromise = null;
                    throw error;
                },
            );
        }

        return this.#acceptancePromise;
    }
}
