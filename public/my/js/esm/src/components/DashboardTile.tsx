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

import React, {useLayoutEffect, useRef} from 'react';
import {Button} from '@moodlehq/design-system';
import type {DashboardBlock, DashboardLabels} from '../repository';
import type {LayoutItem} from '../layout';
import DashboardHandle from './DashboardHandle';

interface PointerDrag {
    x: number;
    y: number;
    width?: number;
    height?: number;
}

interface DashboardTileProps {
    block: DashboardBlock;
    item: LayoutItem;
    labels: DashboardLabels;
    editing: boolean;
    activeMode?: 'move' | 'resize';
    showControls: boolean;
    drag?: PointerDrag;
    dragOrigin?: LayoutItem;
    shouldAnimatePosition?: boolean;
    isBumped?: boolean;
    onStart: (id: number, mode: 'move' | 'resize') => void;
    onKeyDown: (event: React.KeyboardEvent, id: number, mode: 'move' | 'resize') => void;
    onPointerDown: (event: React.PointerEvent, id: number, mode: 'move' | 'resize') => void;
    onDirection: (horizontal: number, vertical: number) => void;
    onCommit: () => void;
    onRemove: (id: number) => void;
}

const DashboardTile = ({
    block,
    item,
    labels,
    editing,
    activeMode,
    showControls,
    drag,
    dragOrigin,
    shouldAnimatePosition = false,
    isBumped = false,
    onStart,
    onKeyDown,
    onPointerDown,
    onDirection,
    onCommit,
    onRemove,
}: DashboardTileProps) => {
    const tileRef = useRef<HTMLElement>(null);
    const previousPosition = useRef<DOMRect | null>(null);
    const positionAnimation = useRef<Animation | null>(null);
    const moveInstructionsId = `core-my-dashboard-move-instructions-${block.id}`;
    const resizeInstructionsId = `core-my-dashboard-resize-instructions-${block.id}`;

    const displayItem = drag && dragOrigin ? dragOrigin : item;

    useLayoutEffect(() => {
        const tile = tileRef.current;
        if (!tile) {
            return;
        }
        positionAnimation.current?.cancel();
        const currentPosition = tile.getBoundingClientRect();
        const priorPosition = previousPosition.current;
        previousPosition.current = currentPosition;

        if (!shouldAnimatePosition || !priorPosition ||
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || !tile.animate) {
            return;
        }

        const horizontal = priorPosition.left - currentPosition.left;
        const vertical = priorPosition.top - currentPosition.top;
        if (!horizontal && !vertical) {
            return;
        }

        // Use FLIP so CSS grid's discrete row/column placement eases visually.
        positionAnimation.current = tile.animate([
            {transform: `translate3d(${horizontal}px, ${vertical}px, 0)`},
            {transform: 'translate3d(0, 0, 0)'},
        ], {
            duration: 180,
            easing: 'cubic-bezier(.2, 0, 0, 1)',
        });
    }, [item.column, item.columns, item.row, item.rows, shouldAnimatePosition]);

    return <section
        ref={tileRef}
        className={`core-my-dashboard-tile${activeMode ? ' core-my-dashboard-tile--active' : ''}${drag ? ' core-my-dashboard-tile--pointer-dragging' : ''}${isBumped ? ' core-my-dashboard-tile--bumped' : ''}`}
        style={{
            gridColumn: `${displayItem.column + 1} / span ${displayItem.columns}`,
            gridRow: `${displayItem.row + 1} / span ${displayItem.rows}`,
            transform: drag && activeMode === 'move' ? `translate3d(${drag.x}px, ${drag.y}px, 0)` : undefined,
            width: drag?.width ? `${drag.width}px` : undefined,
            height: drag?.height ? `${drag.height}px` : undefined,
        }}
        aria-label={labels.tile.replace('{$a}', block.title)}
        data-block={block.name}
        data-block-id={block.id}
        data-bumped={isBumped || undefined}
    >
        {editing && <>
            <span id={moveInstructionsId} className="visually-hidden">{labels.moveinstructions}</span>
            <span id={resizeInstructionsId} className="visually-hidden">{labels.resizeinstructions}</span>
        </>}
        <header className="core-my-dashboard-tile__header">
            {editing && <DashboardHandle
                mode="move"
                label={labels.move.replace('{$a}', block.title)}
                labels={labels}
                instructionsId={moveInstructionsId}
                active={activeMode === 'move'}
                showControls={showControls}
                onStart={() => onStart(block.id, 'move')}
                onKeyDown={event => onKeyDown(event, block.id, 'move')}
                onPointerDown={event => onPointerDown(event, block.id, 'move')}
                onDirection={onDirection}
                onCommit={onCommit}
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
        <div className="core-my-dashboard-tile__content" dangerouslySetInnerHTML={{__html: block.content}} />
        {block.footer && <div
            className="core-my-dashboard-tile__block-footer"
            dangerouslySetInnerHTML={{__html: block.footer}}
        />}
        {editing && <footer className="core-my-dashboard-tile__dashboard-footer">
            <DashboardHandle
                mode="resize"
                label={labels.resize}
                labels={labels}
                instructionsId={resizeInstructionsId}
                active={activeMode === 'resize'}
                showControls={showControls}
                onStart={() => onStart(block.id, 'resize')}
                onKeyDown={event => onKeyDown(event, block.id, 'resize')}
                onPointerDown={event => onPointerDown(event, block.id, 'resize')}
                onDirection={onDirection}
                onCommit={onCommit}
            />
        </footer>}
    </section>;
};

export default DashboardTile;
