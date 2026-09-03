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
 * Pure responsive dashboard layout helpers.
 *
 * @module     core_my/layout
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const MAX_COLUMNS = 6;
export const SIX_COLUMN_THRESHOLD = 1921;
export const FOUR_COLUMN_THRESHOLD = 920;
export const TWO_COLUMN_THRESHOLD = 690;
export const ROW_HEIGHT = 96;
export const GRID_GAP = 16;
export const MIN_COLUMNS = 1;
export const MIN_ROWS = 2;

export interface LayoutItem {
    id: number;
    column: number;
    row: number;
    columns: number;
    rows: number;
    sourceColumns?: number;
}

export const columnsForWidth = (width: number): number => {
    if (width >= SIX_COLUMN_THRESHOLD) {
        return 6;
    }
    if (width >= FOUR_COLUMN_THRESHOLD) {
        return 4;
    }
    if (width >= TWO_COLUMN_THRESHOLD) {
        return 2;
    }
    return 1;
};

const fits = (occupied: Set<string>, column: number, row: number, columns: number, rows: number): boolean => {
    for (let y = row; y < row + rows; y++) {
        for (let x = column; x < column + columns; x++) {
            if (occupied.has(`${x}:${y}`)) {
                return false;
            }
        }
    }
    return true;
};

const occupy = (occupied: Set<string>, item: LayoutItem): void => {
    for (let y = item.row; y < item.row + item.rows; y++) {
        for (let x = item.column; x < item.column + item.columns; x++) {
            occupied.add(`${x}:${y}`);
        }
    }
};

export const packInOrder = (items: LayoutItem[], columnCount: number): LayoutItem[] => {
    const occupied = new Set<string>();
    return items.map(item => {
        const columns = Math.min(item.columns, columnCount);
        let placed: LayoutItem | null = null;
        for (let row = 0; row < 10000 && !placed; row++) {
            for (let column = 0; column + columns <= columnCount; column++) {
                if (fits(occupied, column, row, columns, item.rows)) {
                    placed = {
                        ...item,
                        column,
                        row,
                        columns,
                        sourceColumns: item.sourceColumns ?? item.columns,
                    };
                    break;
                }
            }
        }
        if (!placed) {
            throw new Error('Unable to place dashboard block.');
        }
        occupy(occupied, placed);
        return placed;
    });
};

export const packLayout = (items: LayoutItem[], columnCount: number): LayoutItem[] => {
    const occupied = new Set<string>();
    return [...items]
        .sort((left, right) => left.row - right.row || left.column - right.column || left.id - right.id)
        .map(item => {
            const columns = Math.min(item.columns, columnCount);
            const column = Math.max(0, Math.min(item.column, columnCount - columns));
            const row = Math.max(0, item.row);
            let placed: LayoutItem | null = null;
            if (fits(occupied, column, row, columns, item.rows)) {
                placed = {...item, column, row, columns, sourceColumns: item.sourceColumns ?? item.columns};
            } else {
                for (let nextrow = 0; nextrow < 10000 && !placed; nextrow++) {
                    for (let nextcolumn = 0; nextcolumn + columns <= columnCount; nextcolumn++) {
                        if (fits(occupied, nextcolumn, nextrow, columns, item.rows)) {
                            placed = {
                                ...item,
                                column: nextcolumn,
                                row: nextrow,
                                columns,
                                sourceColumns: item.sourceColumns ?? item.columns,
                            };
                            break;
                        }
                    }
                }
            }
            if (!placed) {
                throw new Error('Unable to place dashboard block.');
            }
            occupy(occupied, placed);
            return placed;
        });
};

interface GridRect {
    column: number;
    row: number;
    columns: number;
    rows: number;
}

const rectsOverlap = (left: GridRect, right: GridRect): boolean =>
    left.column < right.column + right.columns && left.column + left.columns > right.column &&
    left.row < right.row + right.rows && left.row + left.rows > right.row;

/**
 * Slide a rect rightwards, in its own row, to the nearest free columns.
 */
const pushRight = (rect: GridRect, occupied: Set<string>, columnCount: number): GridRect | null => {
    for (let column = rect.column + 1; column + rect.columns <= columnCount; column++) {
        if (fits(occupied, column, rect.row, rect.columns, rect.rows)) {
            return {...rect, column};
        }
    }
    return null;
};

/**
 * Slide a rect downwards, in its own column, to the nearest free rows.
 */
const pushDown = (rect: GridRect, occupied: Set<string>): GridRect | null => {
    for (let row = rect.row + 1; row < 10000; row++) {
        if (fits(occupied, rect.column, row, rect.columns, rect.rows)) {
            return {...rect, row};
        }
    }
    return null;
};

/**
 * Displace a colliding rect towards the empty grid space the collision came from, rather than
 * relocating it to the first free cell in reading order. A block sharing a row with whatever it
 * collided with is pushed right; a block sharing a column is pushed down.
 */
const displaceFromBlocker = (
    rect: GridRect,
    blocker: GridRect,
    occupied: Set<string>,
    columnCount: number,
): GridRect | null => {
    const sameRow = rect.row === blocker.row;
    const sameColumn = rect.column === blocker.column;
    if (sameColumn && !sameRow) {
        return pushDown(rect, occupied) ?? pushRight(rect, occupied, columnCount);
    }
    if (sameRow) {
        return pushRight(rect, occupied, columnCount) ?? pushDown(rect, occupied);
    }
    return null;
};

/**
 * Fall back to the first free cell in reading order, used when directional displacement finds
 * no room in either direction.
 */
const firstFreeCell = (rect: GridRect, occupied: Set<string>, columnCount: number): GridRect | null => {
    for (let row = 0; row < 10000; row++) {
        for (let column = 0; column + rect.columns <= columnCount; column++) {
            if (fits(occupied, column, row, rect.columns, rect.rows)) {
                return {...rect, column, row};
            }
        }
    }
    return null;
};

export const packWithPinned = (
    items: LayoutItem[],
    columnCount: number,
    pinned: LayoutItem,
): LayoutItem[] => {
    const columns = Math.min(pinned.columns, columnCount);
    const safePinned = {
        ...pinned,
        column: Math.max(0, Math.min(pinned.column, columnCount - columns)),
        row: Math.max(0, pinned.row),
        columns,
        rows: Math.max(MIN_ROWS, pinned.rows),
    };
    const occupied = new Set<string>();
    occupy(occupied, safePinned);
    const result: LayoutItem[] = [safePinned];
    const remaining = items
        .filter(item => item.id !== pinned.id)
        .sort((left, right) => left.row - right.row || left.column - right.column || left.id - right.id);
    for (const item of remaining) {
        const columns = Math.min(item.columns, columnCount);
        const column = Math.max(0, Math.min(item.column, columnCount - columns));
        const row = Math.max(0, item.row);
        const rect: GridRect = {column, row, columns, rows: item.rows};
        let placed: LayoutItem | null = null;
        if (fits(occupied, column, row, columns, item.rows)) {
            placed = {...item, column, row, columns};
        } else {
            const blocker = result.find(candidate => rectsOverlap(rect, candidate));
            const resolved = (blocker && displaceFromBlocker(rect, blocker, occupied, columnCount))
                ?? firstFreeCell(rect, occupied, columnCount);
            if (resolved) {
                placed = {...item, column: resolved.column, row: resolved.row, columns};
            }
        }
        if (!placed) {
            throw new Error('Unable to place dashboard block.');
        }
        occupy(occupied, placed);
        result.push(placed);
    }
    return result;
};

export const writeBack = (
    canonical: LayoutItem[],
    derived: LayoutItem[],
    pinnedId?: number,
): LayoutItem[] => {
    const original = new Map(canonical.map(item => [item.id, item]));
    const restored = [...derived]
        .sort((left, right) => left.row - right.row || left.column - right.column || left.id - right.id)
        .map(item => ({
            ...item,
            columns: item.id === pinnedId
                ? item.columns
                : (item.sourceColumns ?? original.get(item.id)?.columns ?? item.columns),
            sourceColumns: undefined,
        }));
    const pinned = restored.find(item => item.id === pinnedId);
    const packed = pinned ? packWithPinned(restored, MAX_COLUMNS, pinned) : packInOrder(restored, MAX_COLUMNS);
    return packed.map(item => ({
        id: item.id,
        column: item.column,
        row: item.row,
        columns: item.columns,
        rows: item.rows,
    }));
};

export const disturbedCount = (before: LayoutItem[], after: LayoutItem[], activeId: number): number => {
    const positions = new Map(before.map(item => [item.id, `${item.column}:${item.row}`]));
    return after.filter(item => item.id !== activeId
        && positions.get(item.id) !== `${item.column}:${item.row}`).length;
};

export const maxRow = (items: LayoutItem[]): number => items.reduce(
    (maximum, item) => Math.max(maximum, item.row + item.rows),
    0,
);
