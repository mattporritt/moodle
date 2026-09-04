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
import {Button} from '@moodlehq/design-system';
import {getString} from '@moodle/lms/core/stringUtils';
import {requireManyAsync} from '@moodle/lms/core/amd';
import DashboardTile from './components/DashboardTile';
import ConfirmationDialog from './components/ConfirmationDialog';
import BlockPalette from './components/BlockPalette';
import GridCell from './components/GridCell';
import DashboardLoading from './components/DashboardLoading';
import DashboardScopeBanner from './components/DashboardScopeBanner';
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
} from './repository';

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

const isSiteDefault = (): boolean => window.location.pathname.endsWith('/my/indexsys.php');

const layoutChanged = (original: LayoutItem, draft: LayoutItem): boolean =>
    original.column !== draft.column || original.row !== draft.row ||
    original.columns !== draft.columns || original.rows !== draft.rows;

interface DashboardProps {
    loadingLabel?: string;
    initialLayout?: LayoutItem[];
}

const Dashboard = ({loadingLabel = '', initialLayout = []}: DashboardProps) => {
    const [data, setData] = useState<DashboardData | null>(null);
    const [canonical, setCanonical] = useState<LayoutItem[]>([]);
    const [columnCount, setColumnCount] = useState(1);
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
    const columnCountRef = useRef(1);
    const dataRef = useRef<DashboardData | null>(null);
    const siteDefault = isSiteDefault();

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

    useEffect(() => {
        if (!data?.javascript) {
            return undefined;
        }
        // Each dashboard reload re-renders blocks with fresh DOM ids and JS initialisers.
        // Guard against a superseded reload's async script running after a newer one has
        // already replaced the tile content it was written to target.
        let superseded = false;
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
        };
    }, [data]);

    useEffect(() => {
        const grid = gridRef.current;
        if (!grid) {
            return;
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

    const announce = useCallback(async(key: string, value?: string | Record<string, unknown>) => {
        setAnnouncement(await getString(key, 'my', value));
    }, []);

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

    const cancel = useCallback(() => {
        if (interaction) {
            void announce('dashboardoperationdiscarded');
        }
        interactionRef.current = null;
        setInteraction(null);
    }, [announce, interaction]);

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
