// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the responsive dashboard layout engine.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    columnsForWidth,
    disturbedCount,
    packLayout,
    packWithPinned,
    writeBack,
    type LayoutItem,
} from '../src/layout';

const canonical: LayoutItem[] = [
    {id: 1, column: 0, row: 0, columns: 4, rows: 2},
    {id: 2, column: 4, row: 0, columns: 2, rows: 2},
    {id: 3, column: 0, row: 2, columns: 2, rows: 2},
];

describe('core_my responsive dashboard layout', () => {
    it.each([
        [500, 1],
        [690, 2],
        [919, 2],
        [920, 4],
        [2099, 4],
        [2100, 6],
    ])('uses %dpx as a %d-column container', (width, columns) => {
        expect(columnsForWidth(width)).toBe(columns);
    });

    it('preserves row-major order and clamps spans at narrower widths', () => {
        const packed = packLayout(canonical, 2);

        expect(packed.map(item => item.id)).toEqual([1, 2, 3]);
        expect(packed.map(item => item.columns)).toEqual([2, 2, 2]);
        expect(packed.map(item => item.row)).toEqual([0, 2, 4]);
    });

    it('keeps the active block pinned while reflowing disturbed blocks', () => {
        const packed = packWithPinned(canonical, 6, {...canonical[2], column: 0, row: 0});

        expect(packed.find(item => item.id === 3)).toMatchObject({column: 0, row: 0});
        expect(disturbedCount(canonical, packed, 3)).toBeGreaterThan(0);
    });

    it('restores canonical spans after a temporary responsive clamp', () => {
        const narrow = packLayout(canonical, 2);
        const restored = writeBack(canonical, narrow);

        expect(restored.find(item => item.id === 1)?.columns).toBe(4);
        expect(restored.find(item => item.id === 2)?.columns).toBe(2);
    });

    it('clamps an oversized pinned block without producing a negative column', () => {
        const packed = packWithPinned(canonical, 1, {...canonical[0], column: 5});

        expect(packed.find(item => item.id === 1)).toMatchObject({column: 0, columns: 1});
    });
});
