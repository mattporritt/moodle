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
 * Tests for core/EditModeSwitch, the design-system-backed Edit mode switch.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {render} from '@testing-library/react';
import EditModeSwitch from '@moodle/lms/core/EditModeSwitch';

// @moodlehq/design-system is ESM only, so Jest cannot resolve it from a CommonJS test bundle.
// Stand Switch in with markup carrying the real component's DOM contract (a labelled
// role="switch" checkbox) closely enough for these tests, which are about EditModeSwitch's own
// prop wiring and lifecycle behaviour, not the design system's internal rendering.
jest.mock('@moodlehq/design-system', () => ({

    Switch: ({id, label, defaultChecked, variant, labelSide, ...rest}: {
        id: string; label: string; defaultChecked?: boolean; variant?: string; labelSide?: string;
        [key: string]: unknown;
    }) => (
        <div className="mds-switch">
            <input id={id} type="checkbox" role="switch" defaultChecked={defaultChecked} {...rest} />
            <label className="mds-switch-control" htmlFor={id}>{label}</label>
        </div>
    ),
}), {virtual: true});

const initMock = jest.fn();

// @moodle/lms/core/amd is the real requireAsync() this component uses to reach the legacy
// core/edit_switch AMD module; stub it so init() is observable without a real AMD loader.
jest.mock('@moodle/lms/core/amd', () => ({
    requireAsync: jest.fn(() => Promise.resolve({init: initMock})),
}), {virtual: true});

describe('core/EditModeSwitch', () => {
    beforeEach(() => {
        initMock.mockClear();
    });

    const PROPS = {
        id: 'editingswitch-abc123',
        context: 42,
        pageurl: 'https://example.com/course/view.php?id=2',
        checked: false,
        label: 'Edit mode',
    };

    it('renders a labelled switch input with the given id and checked state', () => {
        const {getByRole, getByLabelText} = render(<EditModeSwitch {...PROPS} checked />);

        const input = getByRole('switch') as HTMLInputElement;
        expect(input.id).toBe(PROPS.id);
        expect(input.checked).toBe(true);
        expect(getByLabelText(PROPS.label)).toBe(input);
    });

    it('passes the context and pageurl through as data attributes', () => {
        const {getByRole} = render(<EditModeSwitch {...PROPS} />);

        const input = getByRole('switch');
        expect(input.getAttribute('data-context')).toBe(String(PROPS.context));
        expect(input.getAttribute('data-pageurl')).toBe(PROPS.pageurl);
    });

    it('initialises core/edit_switch with the same id once mounted', async() => {
        render(<EditModeSwitch {...PROPS} />);

        // Flush the requireAsync().then() microtask queued by the effect.
        await Promise.resolve();
        await Promise.resolve();

        expect(initMock).toHaveBeenCalledWith(PROPS.id);
        expect(initMock).toHaveBeenCalledTimes(1);
    });
});
