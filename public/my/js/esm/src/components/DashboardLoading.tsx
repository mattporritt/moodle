// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Full-viewport dashboard loading placeholder.
 *
 * @module     core_my/components/DashboardLoading
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useEffect, useRef, useState} from 'react';
import {columnsForWidth, packLayout, ROW_HEIGHT, type LayoutItem} from '../layout';

interface DashboardLoadingProps {
    label: string;
    layout?: LayoutItem[];
}

const GenericGrid = () => <div className="core-my-dashboard-loading__grid" aria-hidden="true">
    {Array.from({length: 6}, (_, index) => <div className="core-my-dashboard-loading__tile" key={index}>
        <div className="core-my-dashboard-loading__heading" />
        <div className="core-my-dashboard-loading__line core-my-dashboard-loading__line--long" />
        <div className="core-my-dashboard-loading__line" />
        <div className="core-my-dashboard-loading__line core-my-dashboard-loading__line--short" />
    </div>)}
</div>;

// Positioned tiles reuse the real grid's column count and packing so, once real data arrives,
// content appears in place rather than the whole page jumping to a differently shaped layout.
const PositionedGrid = ({layout}: {layout: LayoutItem[]}) => {
    const gridRef = useRef<HTMLDivElement>(null);
    const [columnCount, setColumnCount] = useState(1);

    useEffect(() => {
        const grid = gridRef.current;
        if (!grid) {
            return undefined;
        }
        const measure = () => setColumnCount(columnsForWidth(grid.getBoundingClientRect().width));
        const observer = new ResizeObserver(measure);
        observer.observe(grid);
        measure();
        return () => observer.disconnect();
    }, []);

    const positioned = packLayout(layout, columnCount);
    const rows = Math.max(1, ...positioned.map(item => item.row + item.rows));

    return <div
        ref={gridRef}
        className="core-my-dashboard-grid core-my-dashboard-loading__grid--positioned"
        style={{
            gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
            gridTemplateRows: `repeat(${rows}, ${ROW_HEIGHT}px)`,
        }}
        aria-hidden="true"
        data-columns={columnCount}
    >
        {positioned.map(item => <div
            key={item.id}
            className="core-my-dashboard-tile core-my-dashboard-loading__tile core-my-dashboard-loading__tile--positioned"
            style={{
                gridColumn: `${item.column + 1} / span ${item.columns}`,
                gridRow: `${item.row + 1} / span ${item.rows}`,
            }}
        >
            <div className="core-my-dashboard-loading__heading" />
            <div className="core-my-dashboard-loading__line core-my-dashboard-loading__line--long" />
            <div className="core-my-dashboard-loading__line" />
        </div>)}
    </div>;
};

const DashboardLoading = ({label, layout = []}: DashboardLoadingProps) => <div
    className="core-my-dashboard-loading"
    role="status"
    aria-label={label}
    aria-busy="true"
>
    <span className="visually-hidden">{label}</span>
    {layout.length > 0 ? <PositionedGrid layout={layout} /> : <GenericGrid />}
</div>;

export default DashboardLoading;
