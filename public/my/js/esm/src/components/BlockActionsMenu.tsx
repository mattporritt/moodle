// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Kebab menu surfacing a dashboard block's remaining legacy editing-controls actions.
 *
 * @module     core_my/components/BlockActionsMenu
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useEffect, useRef, useState} from 'react';
import {Button} from '@moodlehq/design-system';
import type {BlockAction} from '../repository';

interface BlockActionsMenuProps {
    blockId: number;
    actions: BlockAction[];
    label: string;
}

const BlockActionsMenu = ({blockId, actions, label}: BlockActionsMenuProps) => {
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

export default BlockActionsMenu;
