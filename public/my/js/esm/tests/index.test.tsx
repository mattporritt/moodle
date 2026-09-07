// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the top-level flexible dashboard React application.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, screen, waitFor, within} from '@testing-library/react';
import type React from 'react';
import * as Ajax from '@moodle/lms/core/ajax';
import {requireManyAsync} from '@moodle/lms/core/amd';
import Dashboard from '../src/index';
import type {DashboardData, DashboardLabels} from '../src/repository';
import type {LayoutItem} from '../src/layout';

jest.mock('@moodlehq/design-system', () => {
    const ReactActual = require('react');
    return {
        Button: ReactActual.forwardRef((
            {label, startIcon, size: _size, variant: _variant, className, ...props}:
            React.ButtonHTMLAttributes<HTMLButtonElement> & {
                label?: string;
                startIcon?: React.ReactElement;
                size?: string;
                variant?: string;
            },
            ref: React.Ref<HTMLButtonElement>,
        ) => <button
            ref={ref}
            type="button"
            className={className ?? ''}
            {...props}
        >{startIcon}{label}</button>),
        Badge: ({label, variant: _variant, subtle: _subtle, pill: _pill, ...props}:
        React.HTMLAttributes<HTMLSpanElement> & {label?: string; variant?: string; subtle?: boolean; pill?: boolean}) =>
            <span {...props}>{label}</span>,
        Link: ({label, ...props}: React.AnchorHTMLAttributes<HTMLAnchorElement> & {label?: string}) =>
            <a {...props}>{label}</a>,
    };
}, {virtual: true});

// Jsdom has no ResizeObserver; the dashboard's own column-count measurement effect uses one
// directly (unlike DashboardTile's, this one has no feature-detection guard). Width comes from
// the getBoundingClientRect stub below instead of a real observed measurement.
class StubResizeObserver {
    observe(): void {
        // No-op: width comes from the getBoundingClientRect stub below.
    }
    unobserve(): void {
        // No-op.
    }
    disconnect(): void {
        // No-op.
    }
}
(global as unknown as {ResizeObserver: typeof ResizeObserver}).ResizeObserver =
    StubResizeObserver as unknown as typeof ResizeObserver;

// Jsdom does not implement <dialog>'s imperative API, used by BlockPalette/ConfirmationDialog.
HTMLDialogElement.prototype.showModal = jest.fn(function markOpen(this: HTMLDialogElement) {
    // eslint-disable-next-line no-invalid-this
    this.setAttribute('open', '');
});
HTMLDialogElement.prototype.close = jest.fn(function markClosed(this: HTMLDialogElement) {
    // eslint-disable-next-line no-invalid-this
    this.removeAttribute('open');
});

// Jsdom's layout is a no-op, so every element measures as a zero-size box by default. Pin a wide
// width so columnsForWidth resolves to the full six columns the fixtures below are written for.
const originalGetBoundingClientRect = Element.prototype.getBoundingClientRect;
beforeAll(() => {
    Element.prototype.getBoundingClientRect = jest.fn(() => ({
        width: 2000,
        height: 800,
        top: 0,
        left: 0,
        bottom: 800,
        right: 2000,
        x: 0,
        y: 0,
        toJSON: () => ({}),
    }));
});
afterAll(() => {
    Element.prototype.getBoundingClientRect = originalGetBoundingClientRect;
});

const labels: DashboardLabels = {
    addblock: 'Add a block',
    addblocktop: 'Add a block at the start of the dashboard',
    addblockbottom: 'Add a block at the end of the dashboard',
    blockactions: 'More actions for {$a}',
    cancel: 'Cancel',
    close: 'Close',
    confirm: 'Confirm',
    confirmremove: 'Remove this block?',
    confirmreset: 'Reset this dashboard?',
    done: 'Done',
    down: 'Down',
    emptycell: 'Empty cell',
    left: 'Left',
    loading: 'Loading',
    gridcell: 'Row {$a->row}, column {$a->column}',
    move: 'Move {$a} block',
    movecontrols: 'Move block controls',
    moveinstructions: 'Use the arrow keys to move',
    remove: 'Delete {$a} block',
    removeheading: 'Remove block',
    reset: 'Reset page to default',
    resetheading: 'Reset dashboard',
    resize: 'Resize block',
    resizecontrols: 'Resize block controls',
    resizeinstructions: 'Use the arrow keys to resize',
    right: 'Right',
    scopeown: 'Editing your dashboard',
    scopesitedefault: 'Editing the default dashboard for all users',
    switchtoown: 'Switch to editing your dashboard',
    switchtositedefault: 'Switch to editing the default dashboard for all users',
    tile: '{$a} block',
    up: 'Up',
};

const urls = {ownpage: 'https://example.com/my/index.php', sitedefault: 'https://example.com/my/indexsys.php'};

/**
 * Two side-by-side blocks with a free column between them, wide enough apart that moving the
 * first one right by a single column cannot collide with the second.
 */
const baseLayout: LayoutItem[] = [
    {id: 101, column: 0, row: 0, columns: 2, rows: 2},
    {id: 102, column: 3, row: 0, columns: 2, rows: 2},
];

const secondBlockOnly = [
    {
        id: 102, name: 'online_users', title: 'Second block', content: '<p>Second</p>',
        footer: '', region: 'content', weight: 1, actions: [],
    },
];

const buildData = (overrides: Partial<DashboardData> = {}): DashboardData => ({
    blocks: [
        {
            id: 101, name: 'html', title: 'First block', content: '<p>First</p>',
            footer: '', region: 'content', weight: 0, actions: [],
        },
        {
            id: 102, name: 'online_users', title: 'Second block', content: '<p>Second</p>',
            footer: '', region: 'content', weight: 1, actions: [],
        },
    ],
    layout: baseLayout,
    availableblocks: [{name: 'calendar_month', title: 'Calendar'}],
    canedit: true,
    editing: true,
    sitedefault: false,
    caneditotherscope: false,
    urls,
    javascript: '',
    labels,
    ...overrides,
});

/** Route fetchOne by methodname to whichever fixture/queue applies. */
const mockDashboardApi = (options: {
    getResponses: DashboardData[];
    updateImpl?: (action: string, args: Record<string, unknown>) => Promise<{status: boolean; blockid: number}>;
}) => {
    const {getResponses, updateImpl} = options;
    let getCallCount = 0;
    const fetchOneSpy = jest.spyOn(Ajax, 'fetchOne');
    fetchOneSpy.mockImplementation((request: {methodname: string; args?: Record<string, unknown>}) => {
        if (request.methodname === 'core_my_get_dashboard') {
            const response = getResponses[Math.min(getCallCount, getResponses.length - 1)];
            getCallCount += 1;
            return Promise.resolve(response);
        }
        if (request.methodname === 'core_my_update_dashboard') {
            const args = request.args ?? {};
            if (updateImpl) {
                return updateImpl(args.action as string, args);
            }
            return Promise.resolve({status: true, blockid: 999});
        }
        return Promise.reject(new Error(`Unexpected methodname: ${request.methodname}`));
    });
    return fetchOneSpy;
};

describe('core_my Dashboard application', () => {
    it('loads and renders the dashboard from the server', async() => {
        mockDashboardApi({getResponses: [buildData()]});

        render(<Dashboard />);

        expect(await screen.findByText('First block')).toBeInTheDocument();
        expect(screen.getByText('Second block')).toBeInTheDocument();
    });

    it('shows an error message when the initial load fails', async() => {
        jest.spyOn(Ajax, 'fetchOne').mockImplementation(() => Promise.reject(new Error('Network is down')));

        render(<Dashboard />);

        expect(await screen.findByRole('alert')).toHaveTextContent('Network is down');
    });

    it('commits a keyboard move and persists the new canonical layout', async() => {
        const fetchOneSpy = mockDashboardApi({getResponses: [buildData()]});

        render(<Dashboard />);
        await screen.findByText('First block');

        const moveHandle = screen.getByRole('button', {name: 'Move First block block'});
        fireEvent.keyDown(moveHandle, {key: ' '});
        fireEvent.keyDown(moveHandle, {key: 'ArrowRight'});
        await act(async() => {
            fireEvent.keyDown(moveHandle, {key: ' '});
        });

        await waitFor(() => {
            const saveCall = fetchOneSpy.mock.calls.find(([request]) =>
                (request as {methodname: string}).methodname === 'core_my_update_dashboard');
            expect(saveCall).toBeDefined();
        });

        const saveCall = fetchOneSpy.mock.calls.find(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard')!;
        const args = (saveCall[0] as {args: {action: string; layout: LayoutItem[]}}).args;
        expect(args.action).toBe('save');
        const moved = args.layout.find(item => item.id === 101);
        expect(moved).toMatchObject({column: 1, row: 0});
    });

    it('discards a keyboard interaction on Escape without saving', async() => {
        const fetchOneSpy = mockDashboardApi({getResponses: [buildData()]});

        render(<Dashboard />);
        await screen.findByText('First block');

        const moveHandle = screen.getByRole('button', {name: 'Move First block block'});
        fireEvent.keyDown(moveHandle, {key: ' '});
        fireEvent.keyDown(moveHandle, {key: 'ArrowRight'});
        fireEvent.keyDown(moveHandle, {key: 'Escape'});

        expect(fetchOneSpy.mock.calls.some(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard')).toBe(false);
    });

    it('rejects a keyboard resize that would fall below the minimum tile size', async() => {
        const fetchOneSpy = mockDashboardApi({getResponses: [buildData()]});

        render(<Dashboard />);
        await screen.findByText('First block');

        // Both tiles' resize handles share the generic "Resize block" label; scope to the first
        // block's own tile to disambiguate.
        const firstTile = screen.getByRole('region', {name: 'First block block'});
        const resizeHandle = within(firstTile).getByRole('button', {name: 'Resize block'});
        fireEvent.keyDown(resizeHandle, {key: ' '});
        // The first block is 2 rows tall; MIN_ROWS is 2, so shrinking up once more is rejected.
        fireEvent.keyDown(resizeHandle, {key: 'ArrowUp'});
        await act(async() => {
            fireEvent.keyDown(resizeHandle, {key: ' '});
        });

        const saveCall = fetchOneSpy.mock.calls.find(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard')!;
        // Committing still saves (Space always commits the current draft) - but the rejected
        // shrink left the draft's row span unchanged at 2, proving the minimum-size guard, not
        // the commit path itself, is what's under test here.
        const args = (saveCall[0] as {args: {layout: LayoutItem[]}}).args;
        expect(args.layout.find(item => item.id === 101)?.rows).toBe(2);
    });

    // A full pixel-based pointer drag (pointerdown, a pointermove past the drag threshold, then
    // pointerup committing a new cell) is not exercised here: jsdom's PointerEvent support does
    // not propagate clientX/clientY through dispatched events, so the drag-distance math in
    // pointerDown's `move` handler has no reliable pixel input to work from in this environment.
    // The "no movement at all" path below (a plain click) does not depend on those coordinates and
    // is covered; the drag-distance-to-grid-cell math itself is covered directly by the pure
    // layout.ts helpers it delegates to (see layout.test.ts), which pointerDown's own comment
    // documents as the same clamping logic shift()/keyDown exercise here via the keyboard.
    it('reveals move/resize controls on a plain pointer click that never drags', async() => {
        mockDashboardApi({getResponses: [buildData()]});

        render(<Dashboard />);
        await screen.findByText('First block');

        const moveHandle = screen.getByRole('button', {name: 'Move First block block'});
        await act(async() => {
            fireEvent.pointerDown(moveHandle, {clientX: 0, clientY: 0});
            fireEvent.pointerUp(window);
        });

        expect(screen.getByRole('group', {name: 'Move block controls'})).toBeInTheDocument();
    });

    it('places a newly added block into the specific empty cell that was clicked', async() => {
        const newBlock = {
            id: 103, name: 'calendar_month', title: 'Calendar', content: '<p>Cal</p>',
            footer: '', region: 'content', weight: 2, actions: [],
        };
        // The server appends the new block wherever it likes (here, far from where the user
        // clicked) - the client is expected to notice and immediately re-save it into the cell.
        const afterAdd = buildData({
            blocks: [...buildData().blocks, newBlock],
            layout: [...baseLayout, {id: 103, column: 0, row: 6, columns: 2, rows: 2}],
        });
        const fetchOneSpy = mockDashboardApi({
            getResponses: [buildData(), afterAdd],
            updateImpl: (action) => Promise.resolve({status: true, blockid: action === 'add' ? 103 : 0}),
        });

        const {container} = render(<Dashboard />);
        await screen.findByText('First block');
        // The column-count measurement effect (ResizeObserver + an immediate manual measure())
        // runs after the grid first mounts, one render after the block text itself appears -
        // wait for it, so the empty cell this test targets (column index 2) actually exists yet.
        await waitFor(() => expect(container.querySelector('.core-my-dashboard-grid'))
            .toHaveAttribute('data-columns', '6'));

        const emptyCell = screen.getByRole('button', {name: 'Empty cell, Row 1, column 3'});
        fireEvent.click(emptyCell);
        fireEvent.click(await screen.findByRole('button', {name: 'Calendar'}));

        await waitFor(() => {
            const calls = fetchOneSpy.mock.calls.filter(([request]) =>
                (request as {methodname: string}).methodname === 'core_my_update_dashboard');
            expect(calls).toHaveLength(2);
        });
        const calls = fetchOneSpy.mock.calls.filter(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard');
        const [, secondCall] = calls;
        const args = (secondCall[0] as {args: {action: string; layout: LayoutItem[]}}).args;
        expect(args.action).toBe('save');
        const placed = args.layout.find(item => item.id === 103);
        expect(placed).toMatchObject({column: 2, row: 0});
    });

    it('removes a block after confirmation and reloads the dashboard', async() => {
        const afterRemoval = buildData({
            blocks: secondBlockOnly,
            layout: [{id: 102, column: 3, row: 0, columns: 2, rows: 2}],
        });
        const fetchOneSpy = mockDashboardApi({getResponses: [buildData(), afterRemoval]});

        render(<Dashboard />);
        await screen.findByText('First block');

        fireEvent.click(screen.getByRole('button', {name: 'Delete First block block'}));
        fireEvent.click(await screen.findByRole('button', {name: 'Confirm'}));

        await waitFor(() => expect(screen.queryByText('First block')).not.toBeInTheDocument());

        const removeCall = fetchOneSpy.mock.calls.find(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard');
        expect((removeCall![0] as {args: {action: string; blockid: number}}).args).toMatchObject({
            action: 'remove',
            blockid: 101,
        });
        expect(fetchOneSpy.mock.calls.filter(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_get_dashboard')).toHaveLength(2);
    });

    it('adds a block from the palette and reloads the dashboard', async() => {
        const newBlock = {
            id: 103, name: 'calendar_month', title: 'Calendar', content: '<p>Cal</p>',
            footer: '', region: 'content', weight: 2, actions: [],
        };
        const afterAdd = buildData({
            blocks: [...buildData().blocks, newBlock],
            layout: [...baseLayout, {id: 103, column: 0, row: 2, columns: 2, rows: 2}],
        });
        const fetchOneSpy = mockDashboardApi({getResponses: [buildData(), afterAdd]});

        render(<Dashboard />);
        await screen.findByText('First block');

        fireEvent.click(screen.getByRole('button', {name: labels.addblocktop}));
        fireEvent.click(await screen.findByRole('button', {name: 'Calendar'}));

        expect(await screen.findByText('Calendar')).toBeInTheDocument();

        const addCall = fetchOneSpy.mock.calls.find(([request]) =>
            (request as {methodname: string}).methodname === 'core_my_update_dashboard');
        expect((addCall![0] as {args: {action: string; blockname: string}}).args).toMatchObject({
            action: 'add',
            blockname: 'calendar_month',
        });
    });

    it('runs only the latest reload\'s collected block JS, not a superseded one', async() => {
        const firstData = buildData({javascript: 'window.__ran = "first";'});
        const secondData = buildData({
            javascript: 'window.__ran = "second";',
            blocks: secondBlockOnly,
            layout: [{id: 102, column: 3, row: 0, columns: 2, rows: 2}],
        });
        mockDashboardApi({getResponses: [firstData, secondData]});

        const runTemplateJS = jest.fn();
        const processCollectedJavascript = jest.fn((source: string) => source);
        let resolveFirst: (modules: unknown[]) => void;
        const firstRequire = new Promise(resolve => {
            resolveFirst = resolve;
        });
        const secondRequire = Promise.resolve([{processCollectedJavascript}, {runTemplateJS}]);
        (requireManyAsync as jest.Mock)
            .mockImplementationOnce(() => firstRequire)
            .mockImplementationOnce(() => secondRequire);

        render(<Dashboard />);
        await screen.findByText('First block');

        // Trigger a second load (remove-block flow) while the first reload's collected-JS
        // promise is still unresolved, simulating a slow first response overtaken by a second.
        fireEvent.click(screen.getByRole('button', {name: 'Delete First block block'}));
        fireEvent.click(await screen.findByRole('button', {name: 'Confirm'}));
        await waitFor(() => expect(screen.queryByText('First block')).not.toBeInTheDocument());

        // Now let the superseded first reload's collected-JS resolve.
        await act(async() => {
            resolveFirst([{processCollectedJavascript}, {runTemplateJS}]);
            await secondRequire;
        });

        expect(runTemplateJS).toHaveBeenCalledTimes(1);
        expect(runTemplateJS).toHaveBeenCalledWith('window.__ran = "second";');
    });

    it('keeps the dashboard pending until the grid\'s DOM mutations from a reload settle', async() => {
        const data = buildData({javascript: 'window.__ran = "settle-mutate";'});
        mockDashboardApi({getResponses: [data]});
        (requireManyAsync as jest.Mock).mockImplementation(() => Promise.resolve([
            {processCollectedJavascript: (source: string) => source},
            {runTemplateJS: jest.fn()},
        ]));
        const pendingSpy = jest.spyOn((globalThis as {M: {util: {js_pending: Function}}}).M.util, 'js_pending');
        const completeSpy = jest.spyOn((globalThis as {M: {util: {js_complete: Function}}}).M.util, 'js_complete');

        const {container} = render(<Dashboard />);
        await screen.findByText('First block');

        await waitFor(() => expect(pendingSpy.mock.calls.some(
            ([key]) => (key as string).startsWith('core_my/dashboard:settling:'),
        )).toBe(true));
        const settlingKey = pendingSpy.mock.calls
            .map(([key]) => key as string)
            .find(key => key.startsWith('core_my/dashboard:settling:'))!;

        // Simulate a block's own async fetch replacing its placeholder content - a structural
        // (childList) mutation somewhere under the grid - well within the quiet window.
        const grid = container.querySelector('.core-my-dashboard-grid')!;
        await act(async() => {
            grid.appendChild(document.createElement('div'));
        });

        expect(completeSpy).not.toHaveBeenCalledWith(settlingKey);

        await waitFor(() => expect(completeSpy).toHaveBeenCalledWith(settlingKey), {timeout: 2000});
    });

    it('settles quickly when a reload causes no DOM mutations at all', async() => {
        const data = buildData({javascript: 'window.__ran = "settle-quiet";'});
        mockDashboardApi({getResponses: [data]});
        (requireManyAsync as jest.Mock).mockImplementation(() => Promise.resolve([
            {processCollectedJavascript: (source: string) => source},
            {runTemplateJS: jest.fn()},
        ]));
        const pendingSpy = jest.spyOn((globalThis as {M: {util: {js_pending: Function}}}).M.util, 'js_pending');
        const completeSpy = jest.spyOn((globalThis as {M: {util: {js_complete: Function}}}).M.util, 'js_complete');

        render(<Dashboard />);
        await screen.findByText('First block');

        await waitFor(() => expect(pendingSpy.mock.calls.some(
            ([key]) => (key as string).startsWith('core_my/dashboard:settling:'),
        )).toBe(true));
        const settlingKey = pendingSpy.mock.calls
            .map(([key]) => key as string)
            .find(key => key.startsWith('core_my/dashboard:settling:'))!;

        await waitFor(() => expect(completeSpy).toHaveBeenCalledWith(settlingKey), {timeout: 2000});
    });
});
