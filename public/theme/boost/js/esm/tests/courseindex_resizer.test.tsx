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
 * Tests for the course index drawer resize handle.
 *
 * @copyright  2026 A K M Safat Shahin <safatshahin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {render, screen, fireEvent, act} from '@testing-library/react';
import CourseindexResizer from '@moodle/lms/theme_boost/courseindex_resizer';
import Fetch from '@moodle/lms/core/fetch';

jest.mock('@moodle/lms/core/fetch', () => ({
    '__esModule': true,
    'default': {
        performPost: jest.fn().mockResolvedValue({ok: true}),
    },
}));

const DEFAULT_PROPS = {
    minwidth: 285,
    maxwidth: 640,
    preference: 'drawer-index-width',
    label: 'Resize course index',
    drawerid: 'theme_boost-drawers-courseindex',
};

/**
 * Add a fake rendered drawer to the document, as jsdom performs no layout.
 *
 * @param width The width its getBoundingClientRect should report.
 * @returns The drawer element.
 */
const addFakeDrawer = (width: number): HTMLElement => {
    const drawer = document.createElement('div');
    drawer.id = DEFAULT_PROPS.drawerid;
    drawer.getBoundingClientRect = () => ({width} as DOMRect);
    document.body.appendChild(drawer);
    return drawer;
};

const renderHandle = (props = {}) => {
    render(<CourseindexResizer {...DEFAULT_PROPS} {...props} />);
    return screen.getByRole('separator');
};

describe('@moodle/lms/theme_boost/courseindex_resizer', () => {
    beforeEach(() => {
        jest.useFakeTimers();
        document.body.style.removeProperty('--drawer-index-width');
        document.getElementById(DEFAULT_PROPS.drawerid)?.remove();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('renders an accessible separator with the width limits', () => {
        const handle = renderHandle();

        expect(handle).toHaveAttribute('aria-orientation', 'vertical');
        expect(handle).toHaveAttribute('aria-label', 'Resize course index');
        expect(handle).toHaveAttribute('aria-controls', 'theme_boost-drawers-courseindex');
        expect(handle).toHaveAttribute('aria-valuemin', '285');
        expect(handle).toHaveAttribute('aria-valuemax', '640');
        expect(handle).toHaveAttribute('aria-valuenow', '285');
        expect(handle).toHaveAttribute('tabindex', '0');
    });

    it('starts from the width the server emitted on the body', () => {
        document.body.style.setProperty('--drawer-index-width', '400px');
        const handle = renderHandle();

        expect(handle).toHaveAttribute('aria-valuenow', '400');
    });

    it('clamps an out of range server width', () => {
        document.body.style.setProperty('--drawer-index-width', '9999px');
        const handle = renderHandle();

        expect(handle).toHaveAttribute('aria-valuenow', '640');
    });

    it('starts from the rendered drawer width when a theme changes the default', () => {
        addFakeDrawer(340);
        const handle = renderHandle();

        expect(handle).toHaveAttribute('aria-valuenow', '340');

        fireEvent.keyDown(handle, {key: 'ArrowRight'});
        expect(handle).toHaveAttribute('aria-valuenow', '364');
        expect(document.body.style.getPropertyValue('--drawer-index-width')).toBe('364px');
    });

    it('resizes with arrow keys and applies the width to the body', () => {
        const handle = renderHandle();

        fireEvent.keyDown(handle, {key: 'ArrowRight'});
        expect(handle).toHaveAttribute('aria-valuenow', '309');
        expect(document.body.style.getPropertyValue('--drawer-index-width')).toBe('309px');

        fireEvent.keyDown(handle, {key: 'ArrowRight', shiftKey: true});
        expect(handle).toHaveAttribute('aria-valuenow', '357');

        fireEvent.keyDown(handle, {key: 'ArrowLeft'});
        expect(handle).toHaveAttribute('aria-valuenow', '333');
    });

    it('jumps to the limits with Home and End and never exceeds them', () => {
        const handle = renderHandle();

        fireEvent.keyDown(handle, {key: 'End'});
        expect(handle).toHaveAttribute('aria-valuenow', '640');

        fireEvent.keyDown(handle, {key: 'ArrowRight'});
        expect(handle).toHaveAttribute('aria-valuenow', '640');

        fireEvent.keyDown(handle, {key: 'Home'});
        expect(handle).toHaveAttribute('aria-valuenow', '285');

        fireEvent.keyDown(handle, {key: 'ArrowLeft'});
        expect(handle).toHaveAttribute('aria-valuenow', '285');
    });

    it('persists a keyboard resize once, after the debounce', () => {
        const handle = renderHandle();

        fireEvent.keyDown(handle, {key: 'ArrowRight'});
        fireEvent.keyDown(handle, {key: 'ArrowRight'});
        expect(Fetch.performPost).not.toHaveBeenCalled();

        act(() => {
            jest.runAllTimers();
        });

        expect(Fetch.performPost).toHaveBeenCalledTimes(1);
        expect(Fetch.performPost).toHaveBeenCalledWith(
            'core_user',
            'current/preferences/drawer-index-width',
            {body: {value: 333}},
        );
    });

    it('ignores other keys', () => {
        const handle = renderHandle();

        fireEvent.keyDown(handle, {key: 'Enter'});
        fireEvent.keyDown(handle, {key: 'ArrowUp'});

        expect(handle).toHaveAttribute('aria-valuenow', '285');
    });
});
