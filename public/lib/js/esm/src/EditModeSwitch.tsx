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
 * The site-wide Edit mode switch, rendered with the design system Switch component.
 *
 * @module     core/EditModeSwitch
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect} from 'react';
import {Switch} from '@moodlehq/design-system';
import {requireAsync} from '@moodle/lms/core/amd';

/**
 * Minimal shape of the legacy `core/edit_switch` AMD module this component drives.
 */
type EditSwitchModule = {
    init: (editingSwitchId: string) => void;
};

export interface EditModeSwitchProps {
    /** The id shared with the NonJS fallback markup, so behaviour attaches to the same element. */
    id: string;
    /** The contextid editing is being toggled for. */
    context: number;
    /** The URL to redirect to once the mode change is confirmed. */
    pageurl: string;
    /** The current session key, required by the underlying core_change_editmode web service call. */
    sesskey: string;
    /** Whether editing is currently on. */
    checked: boolean;
    /** The visible/accessible label, e.g. "Edit mode". */
    label: string;
}

/**
 * Render the Edit mode switch and wire it up to the existing core/edit_switch behaviour.
 *
 * Rendering moves onto the design system Switch component here, but the toggle behaviour itself
 * (the core_change_editmode ajax call, the editModeSet event, the redirect-on-success) stays in
 * the existing core/edit_switch AMD module: this only takes over calling its init() once mounted,
 * exactly as the old mustache {{#js}} block used to do server-side.
 *
 * @param props Component props.
 * @returns The rendered Edit mode switch.
 */
export default function EditModeSwitch({id, context, pageurl, sesskey, checked, label}: EditModeSwitchProps) {
    useEffect(() => {
        let cancelled = false;
        requireAsync<EditSwitchModule>('core/edit_switch').then((editSwitch) => {
            if (!cancelled) {
                editSwitch.init(id);
            }
            return undefined;
        });
        return () => {
            cancelled = true;
        };
        // Sesskey/pageurl/context are read by core/edit_switch straight off the DOM node's own
        // data attributes/name, not passed as arguments, so only a change of id re-attaches init().
    }, [id]);

    return (
        <Switch
            id={id}
            name="setmode"
            variant="enable"
            labelSide="start"
            label={label}
            defaultChecked={checked}
            data-context={context}
            data-pageurl={pageurl}
            data-sesskey={sesskey}
        />
    );
}
