// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Responsive dashboard tile.
 *
 * @module     core_my/components/DashboardTile
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {Button} from '@moodlehq/design-system';
import type {DashboardBlock, DashboardLabels} from '../repository';
import type {LayoutItem} from '../layout';
import GridControls from './GridControls';
import DashboardHandle from './DashboardHandle';

interface DashboardTileProps {
    block: DashboardBlock;
    item: LayoutItem;
    labels: DashboardLabels;
    editing: boolean;
    activeMode?: 'move' | 'resize';
    onStart: (id: number, mode: 'move' | 'resize') => void;
    onKeyDown: (event: React.KeyboardEvent, id: number, mode: 'move' | 'resize') => void;
    onPointerDown: (event: React.PointerEvent, id: number, mode: 'move' | 'resize') => void;
    onDirection: (horizontal: number, vertical: number) => void;
    onCommit: () => void;
    onCancel: () => void;
    onRemove: (id: number) => void;
}

const DashboardTile = ({
    block,
    item,
    labels,
    editing,
    activeMode,
    onStart,
    onKeyDown,
    onPointerDown,
    onDirection,
    onCommit,
    onCancel,
    onRemove,
}: DashboardTileProps) => {
    const moveInstructionsId = `core-my-dashboard-move-instructions-${block.id}`;
    const resizeInstructionsId = `core-my-dashboard-resize-instructions-${block.id}`;

    return <section
        className={`core-my-dashboard-tile${activeMode ? ' core-my-dashboard-tile--active' : ''}`}
        style={{
            gridColumn: `${item.column + 1} / span ${item.columns}`,
            gridRow: `${item.row + 1} / span ${item.rows}`,
        }}
        aria-label={labels.tile.replace('{$a}', block.title)}
        data-block={block.name}
        data-block-id={block.id}
    >
        {editing && <>
            <span id={moveInstructionsId} className="visually-hidden">{labels.moveinstructions}</span>
            <span id={resizeInstructionsId} className="visually-hidden">{labels.resizeinstructions}</span>
        </>}
        <header className="core-my-dashboard-tile__header">
            {editing && <DashboardHandle
                mode="move"
                label={labels.move.replace('{$a}', block.title)}
                instructionsId={moveInstructionsId}
                active={activeMode === 'move'}
                onStart={() => onStart(block.id, 'move')}
                onKeyDown={event => onKeyDown(event, block.id, 'move')}
                onPointerDown={event => onPointerDown(event, block.id, 'move')}
            />}
            <h2 className="core-my-dashboard-tile__title">{block.title}</h2>
            {editing && <div className="core-my-dashboard-tile__actions">
                <Button
                    size="md"
                    variant="ghost"
                    className="core-my-dashboard-remove"
                    aria-label={labels.remove.replace('{$a}', block.title)}
                    title={labels.remove.replace('{$a}', block.title)}
                    startIcon={<i className="fa fa-trash-can" aria-hidden="true" />}
                    onClick={() => onRemove(block.id)}
                />
            </div>}
        </header>
        {activeMode && <GridControls
            mode={activeMode}
            labels={labels}
            onDirection={onDirection}
            onCommit={onCommit}
            onCancel={onCancel}
        />}
        <div className="core-my-dashboard-tile__content" dangerouslySetInnerHTML={{__html: block.content}} />
        {block.footer && <footer
            className="core-my-dashboard-tile__footer"
            dangerouslySetInnerHTML={{__html: block.footer}}
        />}
        {editing && <DashboardHandle
            mode="resize"
            label={labels.resize}
            instructionsId={resizeInstructionsId}
            active={activeMode === 'resize'}
            onStart={() => onStart(block.id, 'resize')}
            onKeyDown={event => onKeyDown(event, block.id, 'resize')}
            onPointerDown={event => onPointerDown(event, block.id, 'resize')}
        />}
    </section>;
};

export default DashboardTile;
