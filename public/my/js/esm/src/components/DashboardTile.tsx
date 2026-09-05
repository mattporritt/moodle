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

import React, {useEffect, useLayoutEffect, useRef, useState} from 'react';
import {Button} from '@moodlehq/design-system';
import type {BlockAction, DashboardBlock, DashboardLabels} from '../repository';
import {NARROW_BLOCK_WIDTH, type LayoutItem} from '../layout';
import DashboardHandle from './DashboardHandle';

interface BlockActionsMenuProps {
    blockId: number;
    actions: BlockAction[];
    label: string;
}

// Kept in this file rather than its own module: it's only ever used from DashboardTile below,
// and every ESM component here is a separate network request, so folding trivially-small,
// non-swizzled pieces into their one caller cuts requests without changing what's independently
// themeable (see swizzle.json - this was never a swizzle point of its own).
export const BlockActionsMenu = ({blockId, actions, label}: BlockActionsMenuProps) => {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const itemRefs = useRef<(HTMLAnchorElement | null)[]>([]);
    const menuId = `core-my-dashboard-block-actions-${blockId}`;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        // Move focus onto the first item, matching the standard menu-button pattern.
        itemRefs.current[0]?.focus();

        const closeIfOutside = (event: PointerEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                triggerRef.current?.focus();
            }
        };
        document.addEventListener('pointerdown', closeIfOutside);
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('pointerdown', closeIfOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    if (actions.length === 0) {
        return null;
    }

    const moveFocus = (fromIndex: number, delta: number) => {
        const count = actions.length;
        const nextIndex = (fromIndex + delta + count) % count;
        itemRefs.current[nextIndex]?.focus();
    };

    const onItemKeyDown = (event: React.KeyboardEvent<HTMLAnchorElement>, index: number) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                moveFocus(index, 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                moveFocus(index, -1);
                break;
            case 'Home':
                event.preventDefault();
                itemRefs.current[0]?.focus();
                break;
            case 'End':
                event.preventDefault();
                itemRefs.current[actions.length - 1]?.focus();
                break;
            case 'Tab':
                // Items are not in the natural tab order (roving tabindex); let focus leave
                // the menu as normal, but close it so it doesn't linger open and unfocused.
                setOpen(false);
                break;
            default:
                break;
        }
    };

    return <div className="core-my-dashboard-block-actions" ref={containerRef}>
        <Button
            ref={triggerRef}
            size="md"
            variant="ghost"
            className="core-my-dashboard-block-actions__trigger"
            aria-label={label}
            aria-haspopup="menu"
            aria-expanded={open}
            aria-controls={menuId}
            startIcon={<i className="fa fa-ellipsis-vertical" aria-hidden="true" />}
            onClick={() => setOpen(current => !current)}
        />
        {open && <div
            id={menuId}
            role="menu"
            aria-label={label}
            className="core-my-dashboard-block-actions__menu"
        >
            {actions.map((action, index) => <a
                key={action.id}
                ref={element => {
                    itemRefs.current[index] = element;
                }}
                role="menuitem"
                tabIndex={-1}
                className="core-my-dashboard-block-actions__item"
                href={action.url}
                // The modal form actions are picked up by the core_block/edit click handler
                // already loaded as part of the block's own collected JavaScript requirements.
                data-action={action.modalform ? 'editblock' : undefined}
                data-blockid={action.modalform ? blockId : undefined}
                data-blockform={action.modalform || undefined}
                data-header={action.modalform ? action.label : undefined}
                onKeyDown={event => onItemKeyDown(event, index)}
                onClick={() => setOpen(false)}
            >{action.label}</a>)}
        </div>}
    </div>;
};

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
    const contentRef = useRef<HTMLDivElement>(null);
    const previousPosition = useRef<DOMRect | null>(null);
    const positionAnimation = useRef<Animation | null>(null);
    const [narrow, setNarrow] = useState(false);
    const moveInstructionsId = `core-my-dashboard-move-instructions-${block.id}`;
    const resizeInstructionsId = `core-my-dashboard-resize-instructions-${block.id}`;

    const displayItem = drag && dragOrigin ? dragOrigin : item;

    useEffect(() => {
        const content = contentRef.current;
        if (!content || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const measure = () => setNarrow(content.getBoundingClientRect().width < NARROW_BLOCK_WIDTH);
        const observer = new ResizeObserver(measure);
        observer.observe(content);
        measure();
        return () => observer.disconnect();
    }, []);

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
        data-blockregion={narrow ? 'side-pre' : 'content'}
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
                <BlockActionsMenu
                    blockId={block.id}
                    actions={block.actions}
                    label={labels.blockactions.replace('{$a}', block.title)}
                />
            </div>}
        </header>
        <div
            ref={contentRef}
            className={`core-my-dashboard-tile__content block block_${block.name}`}
            dangerouslySetInnerHTML={{__html: block.content}}
        />
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
