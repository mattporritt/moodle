// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Flexible responsive dashboard React application.
 *
 * @module     core_my/index
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {Badge, Button, Link} from '@moodlehq/design-system';
import {getString} from '@moodle/lms/core/stringUtils';
import {requireManyAsync} from '@moodle/lms/core/amd';
import Pending from '@moodle/lms/core/pending';
import DashboardTile from './components/DashboardTile';
import ConfirmationDialog from './components/ConfirmationDialog';
import BlockPalette from './components/BlockPalette';
import GridCell from './components/GridCell';
import DashboardLoading from './components/DashboardLoading';
import {
    GRID_GAP,
    MIN_COLUMNS,
    MIN_ROWS,
    ROW_HEIGHT,
    columnsForWidth,
    disturbedCount,
    maxRow,
    packLayout,
    packWithPinned,
    writeBack,
    type LayoutItem,
} from './layout';
import {
    getDashboard,
    updateDashboard,
    type AvailableBlock,
    type DashboardData,
    type DashboardLabels,
    type DashboardUrls,
} from './repository';

interface DashboardScopeBannerProps {
    siteDefault: boolean;
    caneditotherscope: boolean;
    urls: DashboardUrls;
    labels: DashboardLabels;
}

// Kept in this file rather than its own module: it's only ever used from Dashboard below, and
// every ESM component here is a separate network request, so folding trivially-small, non-swizzled
// pieces into their one caller cuts requests without changing what's independently themeable (see
// swizzle.json - this was never a swizzle point of its own).
export const DashboardScopeBanner = ({siteDefault, caneditotherscope, urls, labels}: DashboardScopeBannerProps) =>
    <div className="core-my-dashboard-scope">
        <Badge
            variant={siteDefault ? 'warning' : 'info'}
            subtle
            pill
            label={siteDefault ? labels.scopesitedefault : labels.scopeown}
        />
        {caneditotherscope && <Link
            variant="secondary"
            href={siteDefault ? urls.ownpage : urls.sitedefault}
            label={siteDefault ? labels.switchtoown : labels.switchtositedefault}
        />}
    </div>;

interface Interaction {
    id: number;
    mode: 'move' | 'resize';
    origin: 'keyboard' | 'mouseclick' | 'pointer';
    original: LayoutItem;
    draft: LayoutItem;
    before: LayoutItem[];
    drag?: PointerDrag;
}

interface PointerDrag {
    x: number;
    y: number;
    width?: number;
    height?: number;
    shrinking?: boolean;
}

interface PaletteTarget {
    column?: number;
    row?: number;
    position?: 'start' | 'end';
}

type ConfirmAction = {type: 'remove'; id: number} | {type: 'reset'};

// See the settling MutationObserver in the collected-JS effect below for what these guard.
const DASHBOARD_SETTLE_QUIET_MS = 250;
const DASHBOARD_SETTLE_TIMEOUT_MS = 5000;
let dashboardSettleCounter = 0;

const isSiteDefault = (): boolean => window.location.pathname.endsWith('/my/indexsys.php');

const layoutChanged = (original: LayoutItem, draft: LayoutItem): boolean =>
    original.column !== draft.column || original.row !== draft.row ||
    original.columns !== draft.columns || original.rows !== draft.rows;

/**
 * Run a freshly loaded dashboard's collected block JavaScript, and report the dashboard as
 * pending (via core/pending) until any resulting DOM churn settles.
 *
 * A block's own async fetch-and-replace of its rendered content (e.g. Course overview, Recently
 * accessed items) is invisible to anything watching M.util.pending_js - Behat's
 * wait_for_pending_js() included - unless that block's own code says so, which this project's
 * acceptance criteria rules out asking every block to do (a backwards-compatible block API
 * extension to do this properly is filed as a follow-up on the parent epic). Detect it
 * generically instead: such a replacement is always a structural (childList) DOM mutation
 * somewhere under the grid, so treat the dashboard as still settling from the moment this
 * reload's collected JS starts running until the grid goes quiet for a short window - bounded by
 * a hard ceiling in case some future block's own mutations (or a genuine failure) never quiesce,
 * so this can never hang a caller indefinitely. Only childList is watched (not
 * attributes/characterData), so unrelated noise - drag positioning, resize-driven style changes -
 * never resets it.
 *
 * @param data The current dashboard payload, or null before the first load resolves.
 * @param gridRef The grid element to watch for mutations under.
 */
const useCollectedBlockJavascript = (
    data: DashboardData | null,
    gridRef: React.RefObject<HTMLDivElement>,
): void => {
    useEffect(() => {
        if (!data?.javascript) {
            return undefined;
        }
        // Each dashboard reload re-renders blocks with fresh DOM ids and JS initialisers.
        // Guard against a superseded reload's async script running after a newer one has
        // already replaced the tile content it was written to target.
        let superseded = false;

        let settled = false;
        let quietTimer: ReturnType<typeof setTimeout> | undefined;
        let hardTimer: ReturnType<typeof setTimeout> | undefined;
        let observer: MutationObserver | undefined;
        const pending = new Pending(`core_my/dashboard:settling:${dashboardSettleCounter++}`);
        const settle = () => {
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(quietTimer);
            clearTimeout(hardTimer);
            observer?.disconnect();
            pending.resolve();
        };
        hardTimer = setTimeout(settle, DASHBOARD_SETTLE_TIMEOUT_MS);
        const grid = gridRef.current;
        if (grid) {
            observer = new MutationObserver(() => {
                clearTimeout(quietTimer);
                quietTimer = setTimeout(settle, DASHBOARD_SETTLE_QUIET_MS);
            });
            observer.observe(grid, {childList: true, subtree: true});
        }
        // Nothing may ever mutate at all (every block already rendered its final content
        // server-side) - start the quiet window immediately too, not only on the observer's
        // first callback.
        quietTimer = setTimeout(settle, DASHBOARD_SETTLE_QUIET_MS);

        void requireManyAsync(['core/fragment', 'core/templates']).then(([fragment, templates]) => {
            if (superseded) {
                return undefined;
            }
            const processed = (fragment as {processCollectedJavascript: (source: string) => string})
                .processCollectedJavascript(data.javascript);
            (templates as {runTemplateJS: (source: string) => void}).runTemplateJS(processed);
            return undefined;
        });
        return () => {
            superseded = true;
            settle();
        };
    }, [data]);
};

/**
 * Track the grid's current responsive column count from its own rendered width.
 *
 * Returns both the reactive state (for rendering/memoization) and a ref mirroring the same value
 * (for imperative reads from callbacks - e.g. commit() in Dashboard below - that must see the
 * latest column count even when invoked from a stale closure, such as a pointerup listener
 * registered several renders ago).
 *
 * @param gridRef The grid element to measure.
 * @param data The current dashboard payload; re-measures whenever a fresh one loads.
 * @return A tuple of [columnCount, columnCountRef].
 */
const useResponsiveColumnCount = (
    gridRef: React.RefObject<HTMLDivElement>,
    data: DashboardData | null,
): [number, React.RefObject<number>] => {
    const [columnCount, setColumnCount] = useState(1);
    const columnCountRef = useRef(1);

    useEffect(() => {
        const grid = gridRef.current;
        if (!grid) {
            return undefined;
        }
        const measure = () => {
            const next = columnsForWidth(grid.getBoundingClientRect().width);
            columnCountRef.current = next;
            setColumnCount(next);
        };
        const observer = new ResizeObserver(measure);
        observer.observe(grid);
        measure();
        return () => observer.disconnect();
    }, [data]);

    return [columnCount, columnCountRef];
};

interface DashboardProps {
    loadingLabel?: string;
    initialLayout?: LayoutItem[];
}

const Dashboard = ({loadingLabel = '', initialLayout = []}: DashboardProps) => {
    const [data, setData] = useState<DashboardData | null>(null);
    const [canonical, setCanonical] = useState<LayoutItem[]>([]);
    const [interaction, setInteraction] = useState<Interaction | null>(null);
    const [announcement, setAnnouncement] = useState('');
    const [error, setError] = useState('');
    const [palette, setPalette] = useState<PaletteTarget | null>(null);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null);
    const [saving, setSaving] = useState(false);
    const gridRef = useRef<HTMLDivElement>(null);
    const pointerRef = useRef<{x: number; y: number; moved: boolean} | null>(null);
    const interactionRef = useRef<Interaction | null>(null);
    const canonicalRef = useRef<LayoutItem[]>([]);
    const displayLayoutRef = useRef<LayoutItem[]>([]);
    const dataRef = useRef<DashboardData | null>(null);
    const siteDefault = isSiteDefault();

    /**
     * Fetch a full dashboard payload and reset the canonical layout to match it.
     *
     * Called on mount and after any server-side mutation the client cannot safely predict the
     * result of (adding a block, removing one, resetting to the site default) - see get_dashboard.
     */
    const load = useCallback(async() => {
        try {
            const response = await getDashboard(siteDefault);
            setData(response);
            dataRef.current = response;
            setCanonical(response.layout);
            canonicalRef.current = response.layout;
            setError('');
            return response;
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
            return null;
        }
    }, [siteDefault]);

    useEffect(() => {
        void load();
    }, [load]);

    useCollectedBlockJavascript(data, gridRef);
    const [columnCount, columnCountRef] = useResponsiveColumnCount(gridRef, data);

    const displayLayout = useMemo(() => packLayout(canonical, columnCount), [canonical, columnCount]);
    displayLayoutRef.current = displayLayout;
    const previewLayout = useMemo(() => interaction
        ? packWithPinned(displayLayout, columnCount, interaction.draft)
        : displayLayout, [columnCount, displayLayout, interaction]);
    const bumpedBlockIds = useMemo(() => {
        if (interaction?.origin !== 'pointer') {
            return new Set<number>();
        }
        const originalItems = new Map(displayLayout.map(item => [item.id, item]));
        return new Set(previewLayout.filter(item => {
            const original = originalItems.get(item.id);
            return item.id !== interaction.id && original && (
                item.column !== original.column || item.row !== original.row
            );
        }).map(item => item.id));
    }, [displayLayout, interaction, previewLayout]);
    const blocksById = useMemo(() => new Map((data?.blocks ?? []).map(block => [block.id, block])), [data]);

    /** Set the live region's text so screen readers announce a move/resize outcome. */
    const announce = useCallback(async(key: string, value?: string | Record<string, unknown>) => {
        setAnnouncement(await getString(key, 'my', value));
    }, []);

    /**
     * Begin a move or resize interaction on a tile.
     *
     * `origin` distinguishes how the interaction started (keyboard activation, a mouse click that
     * turns out not to be a drag, or an active pointer drag) because several things downstream -
     * whether the on-screen move/resize controls are shown, whether other tiles animate out of the
     * way live, whether Escape/Enter apply - only make sense for some of these (see showControls
     * and shouldAnimatePosition where Interaction is consumed further down).
     */
    const start = useCallback((id: number, mode: 'move' | 'resize', origin: Interaction['origin'] = 'keyboard') => {
        const item = displayLayout.find(candidate => candidate.id === id);
        const block = blocksById.get(id);
        if (!item || !block) {
            return;
        }
        const next = {id, mode, origin, original: item, draft: item, before: displayLayout};
        interactionRef.current = next;
        setInteraction(next);
        void announce(mode === 'move' ? 'dashboardmovebegin' : 'dashboardresizebegin', block.title);
    }, [announce, blocksById, displayLayout]);

    /**
     * Nudge the in-progress interaction's draft rectangle by whole grid cells - one keypress, one
     * cell. (pointerDown's own `move` handler needs sub-cell drag position for the tile to track
     * the pointer smoothly, so it recomputes the equivalent clamping itself rather than reusing
     * this; see the comment there.) A resize that would fall below the minimum tile size is
     * rejected (with an announcement) rather than clamped, so the keyboard controls never go
     * silently unresponsive at the limit.
     */
    const shift = useCallback((horizontal: number, vertical: number) => {
        setInteraction(current => {
            if (!current) {
                return current;
            }
            const draft = {...current.draft};
            if (current.mode === 'move') {
                draft.column = Math.max(0, Math.min(columnCount - draft.columns, draft.column + horizontal));
                draft.row = Math.max(0, draft.row + vertical);
            } else {
                const nextColumns = draft.columns + horizontal;
                const nextRows = draft.rows + vertical;
                if (nextColumns < MIN_COLUMNS || nextRows < MIN_ROWS) {
                    void announce('dashboardminimumsize', `${MIN_COLUMNS} × ${MIN_ROWS}`);
                    return current;
                }
                draft.columns = Math.min(columnCount - draft.column, nextColumns);
                draft.rows = nextRows;
            }
            const next = {...current, draft};
            interactionRef.current = next;
            return next;
        });
    }, [announce, columnCount]);

    /**
     * Persist the in-progress interaction's draft rectangle as the new canonical layout.
     *
     * Reads from refs (currentData/currentDisplay/currentCanonical/currentColumns), not state,
     * because this can be invoked from a pointerup listener registered once per drag in
     * pointerDown's closure - by the time the user releases, several renders may have happened
     * and captured state there would be stale. packWithPinned resolves any other tiles the
     * committed rectangle now overlaps (recorded in `disturbed`, purely for the a11y announcement);
     * writeBack then restores every item's canonical (full-width) column span and re-packs at
     * that width, so the persisted layout matches what was just resolved on-screen rather than
     * whatever the current, possibly narrower, responsive column count would produce (see the
     * writeBack/packLayout comments in layout.ts).
     */
    const commit = useCallback(async() => {
        const current = interactionRef.current;
        const currentData = dataRef.current;
        const currentDisplay = displayLayoutRef.current;
        const currentCanonical = canonicalRef.current;
        const currentColumns = columnCountRef.current;
        if (!current || !currentData) {
            return;
        }
        const derived = packWithPinned(currentDisplay, currentColumns, current.draft);
        const disturbed = disturbedCount(current.before, derived, current.id);
        const next = writeBack(currentCanonical, derived, current.id);
        setSaving(true);
        try {
            await updateDashboard('save', siteDefault, next);
            setCanonical(next);
            canonicalRef.current = next;
            const item = derived.find(candidate => candidate.id === current.id)!;
            await announce(current.mode === 'move' ? 'dashboardmovecommitted' : 'dashboardresizecommitted', {
                row: item.row + 1,
                column: item.column + 1,
                columns: item.columns,
                rows: item.rows,
                disturbed,
            });
            interactionRef.current = null;
            setInteraction(null);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    }, [announce, siteDefault]);

    /** Abandon the in-progress interaction, discarding its draft rectangle unsaved. */
    const cancel = useCallback(() => {
        if (interaction) {
            void announce('dashboardoperationdiscarded');
        }
        interactionRef.current = null;
        setInteraction(null);
    }, [announce, interaction]);

    /**
     * Keyboard-driven move/resize: Space/Enter starts or commits an interaction, Escape cancels
     * it, and the arrow keys nudge it one cell via shift() - a full keyboard-only equivalent of
     * the pointer drag pointerDown offers with a mouse.
     */
    const keyDown = useCallback((event: React.KeyboardEvent, id: number, mode: 'move' | 'resize') => {
        if (!interaction && (event.key === ' ' || event.key === 'Enter')) {
            event.preventDefault();
            start(id, mode);
            return;
        }
        if (!interaction || interaction.id !== id || interaction.mode !== mode) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            cancel();
        } else if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            void commit();
        } else if (event.key.startsWith('Arrow')) {
            event.preventDefault();
            const directions: Record<string, [number, number]> = {
                ArrowLeft: [-1, 0],
                ArrowRight: [1, 0],
                ArrowUp: [0, -1],
                ArrowDown: [0, 1],
            };
            shift(...directions[event.key]);
        }
    }, [cancel, commit, interaction, shift, start]);

    /**
     * Mouse/touch-driven move or resize, tracked with raw window pointer listeners rather than
     * React drag events so the drag keeps following the pointer even outside the tile or the grid.
     *
     * Two coordinate systems are maintained together throughout the drag: `draft` (whole grid
     * cells - column/row/columns/rows, exactly what shift()/keyDown also produce, and the only
     * form the server ever sees) and `drag` (raw sub-cell pixel offsets/dimensions, used only to
     * animate the dragged tile smoothly under the pointer - see DashboardTile). `columnStride` and
     * `rowStride` are one cell plus one gap each, i.e. the pixel distance between equivalent points
     * on adjacent cells; dividing a pixel delta by a stride and rounding is what turns a drag
     * distance into a whole number of cells for `draft`. A move is capped so the tile's whole
     * rectangle - not just its origin corner - stays on the grid (`columnCount - draft.columns`,
     * never negative row); a resize is capped so it never shrinks below the minimum tile size and
     * never grows past the grid's right edge. `pointer.moved` (a small 4px threshold, to absorb
     * hand tremor on a click that was never meant to be a drag) is what pointerup below uses to
     * decide whether this was a genuine drag to commit, or a plain click that should instead just
     * reveal the on-screen move/resize controls (see the `up` handler and Interaction.origin).
     */
    const pointerDown = useCallback((event: React.PointerEvent, id: number, mode: 'move' | 'resize') => {
        event.preventDefault();
        start(id, mode, 'pointer');
        pointerRef.current = {x: event.clientX, y: event.clientY, moved: false};
        const origin = displayLayout.find(item => item.id === id);
        if (!origin) {
            return;
        }
        const grid = gridRef.current;
        const cellWidth = grid
            ? (grid.getBoundingClientRect().width - GRID_GAP * (columnCount - 1)) / columnCount
            : 1;
        const columnStride = cellWidth + GRID_GAP;
        const rowStride = ROW_HEIGHT + GRID_GAP;
        const originalWidth = origin.columns * cellWidth + (origin.columns - 1) * GRID_GAP;
        const originalHeight = origin.rows * ROW_HEIGHT + (origin.rows - 1) * GRID_GAP;
        const move = (pointerEvent: PointerEvent) => {
            const pointer = pointerRef.current;
            if (!pointer) {
                return;
            }
            const deltaX = pointerEvent.clientX - pointer.x;
            const deltaY = pointerEvent.clientY - pointer.y;
            pointer.moved = pointer.moved || Math.abs(deltaX) >= 4 || Math.abs(deltaY) >= 4;
            const horizontal = Math.round(deltaX / columnStride);
            const vertical = Math.round(deltaY / rowStride);
            setInteraction(current => {
                if (!current) {
                    return current;
                }
                const draft = {...origin};
                let drag: PointerDrag;
                if (mode === 'move') {
                    draft.column = Math.max(0, Math.min(columnCount - draft.columns, origin.column + horizontal));
                    draft.row = Math.max(0, origin.row + vertical);
                    drag = {
                        x: Math.max(-origin.column * columnStride,
                            Math.min((columnCount - origin.column - origin.columns) * columnStride, deltaX)),
                        y: Math.max(-origin.row * rowStride, deltaY),
                    };
                } else {
                    draft.columns = Math.max(MIN_COLUMNS,
                        Math.min(columnCount - draft.column, origin.columns + horizontal));
                    draft.rows = Math.max(MIN_ROWS, origin.rows + vertical);
                    drag = {
                        x: 0,
                        y: 0,
                        width: Math.max(cellWidth, Math.min(
                            (columnCount - origin.column) * columnStride - GRID_GAP,
                            originalWidth + deltaX,
                        )),
                        height: Math.max(ROW_HEIGHT, originalHeight + deltaY),
                        shrinking: deltaX < 0 || deltaY < 0,
                    };
                }
                const next = {...current, draft, drag};
                interactionRef.current = next;
                return next;
            });
        };
        const cleanup = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', abort);
            pointerRef.current = null;
        };
        const up = () => {
            const current = interactionRef.current;
            if (pointerRef.current?.moved && current && layoutChanged(current.original, current.draft)) {
                void commit();
            } else if (!pointerRef.current?.moved) {
                setInteraction(previous => {
                    if (!previous) {
                        return previous;
                    }
                    const next = {...previous, origin: 'mouseclick' as const};
                    interactionRef.current = next;
                    return next;
                });
            } else {
                cancel();
            }
            cleanup();
        };
        const abort = () => {
            cleanup();
            cancel();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', abort);
    }, [cancel, columnCount, commit, displayLayout, start]);

    /** Delete a block instance (after the caller has already confirmed) and reload the dashboard. */
    const remove = useCallback(async(id: number) => {
        if (!data) {
            return;
        }
        setSaving(true);
        try {
            await updateDashboard('remove', siteDefault, [], '', id);
            await load();
            await announce('dashboardblockremoved');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    }, [announce, data, load, siteDefault]);

    /**
     * Add a new block instance, then, if the palette was opened from a specific empty cell or
     * from the top/bottom toolbar buttons, immediately place it there instead of leaving it
     * wherever the server appended it.
     *
     * A newly-added block's id and default size are only known once the server responds, so this
     * cannot be done optimistically: it reloads via load() first, finds the new block in the
     * fresh layout, then - only if the user targeted a specific empty cell (palette.column/row) or
     * the start of the grid (palette.position === 'start') - repacks around a pinned rectangle at
     * that position and saves again. Opening the palette from the bottom toolbar button needs no
     * second save: the block already lands at the bottom, which is where dashboard::add() (server
     * side) puts it by default.
     */
    const add = useCallback(async(block: AvailableBlock) => {
        if (!data) {
            return;
        }
        setSaving(true);
        try {
            const result = await updateDashboard('add', siteDefault, [], block.name);
            const response = await load();
            if (response && palette && result.blockid) {
                const item = response.layout.find(candidate => candidate.id === result.blockid);
                if (item && palette.column !== undefined && palette.row !== undefined) {
                    const pinned = {
                        ...item,
                        column: Math.min(palette.column, columnCount - Math.min(item.columns, columnCount)),
                        row: palette.row,
                        columns: Math.min(item.columns, columnCount),
                    };
                    const next = writeBack(response.layout,
                        packWithPinned(packLayout(response.layout, columnCount), columnCount, pinned), item.id);
                    await updateDashboard('save', siteDefault, next);
                    setCanonical(next);
                    canonicalRef.current = next;
                } else if (item && palette.position === 'start') {
                    const next = writeBack(response.layout,
                        packWithPinned(packLayout(response.layout, columnCount), columnCount,
                            {...item, column: 0, row: 0}), item.id);
                    await updateDashboard('save', siteDefault, next);
                    setCanonical(next);
                    canonicalRef.current = next;
                }
            }
            setPalette(null);
            await announce('dashboardblockadded', block.title);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    }, [announce, columnCount, data, load, palette, siteDefault]);

    /**
     * Discard the user's own dashboard customisation and revert to the site default.
     * Own dashboard only - see the 'reset' case in update_dashboard.php.
     */
    const reset = useCallback(async() => {
        if (!data) {
            return;
        }
        setSaving(true);
        try {
            await updateDashboard('reset', false);
            // Reset restores the system dashboard, which also ends Moodle edit mode.
            // Reload the document to keep the server-rendered edit switch in sync.
            window.location.reload();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    }, [data]);

    if (!data) {
        return error
            ? <div className="core-my-dashboard-status alert alert-danger" role="alert">{error}</div>
            : <DashboardLoading label={loadingLabel} layout={initialLayout} />;
    }

    const rows = Math.max(1, maxRow(previewLayout));
    const prospective = interaction && (layoutChanged(interaction.original, interaction.draft) ||
        (interaction.origin === 'pointer' && interaction.mode === 'resize' && interaction.drag?.shrinking))
        ? interaction.draft : undefined;
    return <div className="core-my-dashboard-app" aria-busy={saving}>
        {error && <div className="alert alert-danger" role="alert">{error}</div>}
        <div className="visually-hidden" aria-live="polite" aria-atomic="true">{announcement}</div>
        {data.editing && <DashboardScopeBanner
            siteDefault={siteDefault}
            caneditotherscope={data.caneditotherscope}
            urls={data.urls}
            labels={data.labels}
        />}
        {data.editing && <div className="core-my-dashboard-toolbar">
            <Button variant="secondary" label={data.labels.addblocktop} onClick={() => setPalette({position: 'start'})} />
            {!siteDefault && <Button
                variant="outline-danger"
                label={data.labels.reset}
                onClick={() => setConfirmAction({type: 'reset'})}
            />}
        </div>}
        <div
            ref={gridRef}
            className="core-my-dashboard-grid"
            style={{
                gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
                gridTemplateRows: `repeat(${rows}, ${ROW_HEIGHT}px)`,
            }}
            data-columns={columnCount}
        >
            {data.editing && Array.from({length: rows}, (_, row) =>
                Array.from({length: columnCount}, (__, column) => {
                    const isProspective = Boolean(prospective
                        && column >= prospective.column && column < prospective.column + prospective.columns
                        && row >= prospective.row && row < prospective.row + prospective.rows);
                    const isOccupied = previewLayout.some(item =>
                        column >= item.column && column < item.column + item.columns
                        && row >= item.row && row < item.row + item.rows
                    );
                    return !isOccupied || isProspective ? <GridCell
                        key={`${column}:${row}`}
                        column={column}
                        row={row}
                        label={data.labels.emptycell}
                        positionLabel={data.labels.gridcell}
                        addLabel={data.labels.addblock}
                        prospective={isProspective}
                        onActivate={(targetColumn, targetRow) => setPalette({column: targetColumn, row: targetRow})}
                    /> : null;
                })
            )}
            {previewLayout.map(item => {
                const block = blocksById.get(item.id);
                if (!block) {
                    return null;
                }
                return <DashboardTile
                    key={item.id}
                    block={block}
                    item={item}
                    labels={data.labels}
                    editing={data.editing}
                    activeMode={interaction?.id === item.id ? interaction.mode : undefined}
                    showControls={interaction?.id === item.id && interaction.origin !== 'pointer'}
                    drag={interaction?.id === item.id ? interaction.drag : undefined}
                    dragOrigin={interaction?.id === item.id ? interaction.original : undefined}
                    shouldAnimatePosition={interaction?.origin === 'pointer' && interaction.id !== item.id}
                    isBumped={bumpedBlockIds.has(item.id)}
                    onStart={start}
                    onKeyDown={keyDown}
                    onPointerDown={pointerDown}
                    onDirection={shift}
                    onCommit={() => void commit()}
                    onRemove={id => setConfirmAction({type: 'remove', id})}
                />;
            })}
        </div>
        {data.editing && <div className="core-my-dashboard-toolbar core-my-dashboard-toolbar--bottom">
            <Button variant="secondary" label={data.labels.addblockbottom} onClick={() => setPalette({position: 'end'})} />
        </div>}
        {palette && <BlockPalette
            title={data.labels.addblock}
            closeLabel={data.labels.close}
            blocks={data.availableblocks}
            onSelect={block => void add(block)}
            onClose={() => setPalette(null)}
        />}
        {confirmAction && <ConfirmationDialog
            title={confirmAction.type === 'remove' ? data.labels.removeheading : data.labels.resetheading}
            message={confirmAction.type === 'remove' ? data.labels.confirmremove : data.labels.confirmreset}
            confirmLabel={data.labels.confirm}
            cancelLabel={data.labels.cancel}
            onConfirm={() => {
                const action = confirmAction;
                setConfirmAction(null);
                if (action.type === 'remove') {
                    void remove(action.id);
                } else {
                    void reset();
                }
            }}
            onCancel={() => setConfirmAction(null)}
        />}
    </div>;
};

export default Dashboard;
