// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the replaceable dashboard grid components.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fireEvent, render, screen} from '@testing-library/react';
import type React from 'react';
import GridCell from '../src/components/GridCell';
import GridControls from '../src/components/GridControls';
import ConfirmationDialog from '../src/components/ConfirmationDialog';
import DashboardTile from '../src/components/DashboardTile';
import BlockActionsMenu from '../src/components/BlockActionsMenu';
import BlockPalette from '../src/components/BlockPalette';
import DashboardHandle from '../src/components/DashboardHandle';
import DashboardLoading from '../src/components/DashboardLoading';
import DashboardScopeBanner from '../src/components/DashboardScopeBanner';
import type {DashboardLabels} from '../src/repository';

jest.mock('@moodlehq/design-system', () => {
    const ReactActual = require('react');
    return {
        Button: ReactActual.forwardRef((
            {label, startIcon, size, variant: _variant, className, ...props}:
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
            className={`mds-btn--size-${size} ${className ?? ''}`}
            {...props}
        >{startIcon}{label}</button>),
        Badge: ({label, variant: _variant, subtle: _subtle, pill: _pill, ...props}:
        React.HTMLAttributes<HTMLSpanElement> & {label?: string; variant?: string; subtle?: boolean; pill?: boolean}) =>
            <span {...props}>{label}</span>,
        Link: ({label, ...props}: React.AnchorHTMLAttributes<HTMLAnchorElement> & {label?: string}) =>
            <a {...props}>{label}</a>,
    };
}, {virtual: true});

const labels = {
    blockactions: 'More actions for {$a}',
    up: 'Up',
    down: 'Down',
    left: 'Left',
    right: 'Right',
    done: 'Done',
    cancel: 'Cancel',
    movecontrols: 'Move block controls',
    resizecontrols: 'Resize block controls',
    moveinstructions: 'Use the arrow keys to move',
    resizeinstructions: 'Use the arrow keys to resize',
    scopeown: 'Editing your dashboard',
    scopesitedefault: 'Editing the default dashboard for all users',
    switchtoown: 'Switch to editing your dashboard',
    switchtositedefault: 'Switch to editing the default dashboard for all users',
} as DashboardLabels;

const urls = {
    ownpage: 'https://example.com/my/index.php',
    sitedefault: 'https://example.com/my/indexsys.php',
};

describe('core_my grid components', () => {
    it('exposes an available cell as a positioned button', () => {
        const activate = jest.fn();
        render(<GridCell
            column={2}
            row={3}
            label="Empty grid cell"
            positionLabel="Row {$a->row}, column {$a->column}"
            addLabel="Add a block"
            onActivate={activate}
        />);

        const cell = screen.getByRole('button', {name: 'Empty grid cell, Row 4, column 3'});
        cell.focus();
        expect(cell).toHaveFocus();

        fireEvent.click(cell);
        expect(activate).toHaveBeenCalledWith(2, 3);
    });

    it('shows a plus icon and an "add a block" hover/focus cue without changing the accessible name', () => {
        render(<GridCell
            column={2}
            row={3}
            label="Empty grid cell"
            positionLabel="Row {$a->row}, column {$a->column}"
            addLabel="Add a block"
        />);

        const cell = screen.getByRole('button', {name: 'Empty grid cell, Row 4, column 3'});
        expect(cell.querySelector('.core-my-grid-cell__icon')).toHaveAttribute('aria-hidden', 'true');
        const cue = cell.querySelector('.core-my-grid-cell__label');
        expect(cue).toHaveAttribute('aria-hidden', 'true');
        expect(cue).toHaveTextContent('Add a block');
    });

    it('renders prospective placement without adding a focus target', () => {
        const {container} = render(<GridCell
            column={0}
            row={0}
            label="Empty grid cell"
            positionLabel="Row {$a->row}, column {$a->column}"
            addLabel="Add a block"
            prospective
        />);

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(container.firstChild).toHaveAttribute('aria-hidden', 'true');
    });

    it('builds bespoke controls from MDS buttons and relays direction', () => {
        const direction = jest.fn();
        render(<GridControls
            mode="move"
            labels={labels}
            onDirection={direction}
        />);

        expect(screen.getByRole('group', {name: 'Move block controls'})).toBeInTheDocument();
        expect(screen.getAllByRole('button')).toHaveLength(4);
        [
            ['Up', 0, -1],
            ['Left', -1, 0],
            ['Right', 1, 0],
            ['Down', 0, 1],
        ].forEach(([name, horizontal, vertical]) => {
            fireEvent.click(screen.getByRole('button', {name: String(name)}));
            expect(direction).toHaveBeenLastCalledWith(horizontal, vertical);
        });
    });

    it('exposes an icon move handle to pointer and keyboard input', () => {
        const start = jest.fn();
        const pointerDown = jest.fn();
        const keyDown = jest.fn();
        render(<DashboardHandle
            mode="move"
            label="Move Course overview block"
            labels={labels}
            instructionsId="move-instructions"
            active={false}
            showControls={false}
            onStart={start}
            onKeyDown={keyDown}
            onPointerDown={pointerDown}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
        />);

        const handle = screen.getByRole('button', {name: 'Move Course overview block'});
        expect(handle).toHaveAttribute('aria-describedby', 'move-instructions');
        expect(handle).toHaveAttribute('aria-pressed', 'false');
        expect(handle.querySelector('.fa-arrows-up-down-left-right')).toBeInTheDocument();
        fireEvent.pointerDown(handle, {clientX: 10, clientY: 10});
        expect(pointerDown).toHaveBeenCalled();
        expect(handle).toHaveFocus();
        fireEvent.keyDown(handle, {key: 'Enter'});
        expect(keyDown).toHaveBeenCalled();
    });

    it('displays clickable directional controls around an active handle', () => {
        const direction = jest.fn();
        const commit = jest.fn();
        render(<DashboardHandle
            mode="resize"
            label="Resize Course overview block"
            labels={labels}
            instructionsId="resize-instructions"
            active
            showControls
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={direction}
            onCommit={commit}
        />);

        expect(screen.getByRole('group', {name: 'Resize block controls'})).toBeInTheDocument();
        const up = screen.getByRole('button', {name: 'Up'});
        expect(up).toHaveAttribute('tabindex', '-1');
        expect(up.querySelector('.fa-circle-arrow-up')).toBeInTheDocument();
        fireEvent.click(up);
        expect(direction).toHaveBeenCalledWith(0, -1);
        fireEvent.pointerDown(screen.getByRole('button', {name: 'Resize Course overview block'}));
        expect(commit).toHaveBeenCalled();
        fireEvent.blur(screen.getByRole('button', {name: 'Resize Course overview block'}));
        expect(commit).toHaveBeenCalledTimes(2);
    });

    it('hides directional controls during an active pointer interaction', () => {
        render(<DashboardHandle
            mode="resize"
            label="Resize Course overview block"
            labels={labels}
            instructionsId="resize-instructions"
            active
            showControls={false}
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
        />);

        expect(screen.queryByRole('group', {name: 'Resize block controls'})).not.toBeInTheDocument();
    });

    it('renders an accessible full-viewport loading skeleton', () => {
        const {container} = render(<DashboardLoading label="Loading" />);

        expect(screen.getByRole('status', {name: 'Loading'})).toHaveAttribute('aria-busy', 'true');
        expect(container.querySelectorAll('.core-my-dashboard-loading__tile')).toHaveLength(6);
    });

    it('renders block content without changing the tile accessible name', () => {
        render(<DashboardTile
            block={{
                id: 7,
                name: 'html',
                title: 'Useful links',
                content: '<p>Tile content</p>',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [],
            }}
            item={{id: 7, column: 0, row: 0, columns: 2, rows: 2}}
            labels={{...labels, tile: '{$a} block'} as DashboardLabels}
            editing={false}
            showControls={false}
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        expect(screen.getByRole('region', {name: 'Useful links block'})).toBeInTheDocument();
        expect(screen.getByText('Tile content')).toBeInTheDocument();
    });

    it('wraps block content with the standard block classes so shared block CSS keeps applying', () => {
        const {container} = render(<DashboardTile
            block={{
                id: 8,
                name: 'calendar_month',
                title: 'Calendar',
                content: '<p>Tile content</p>',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [],
            }}
            item={{id: 8, column: 0, row: 0, columns: 2, rows: 2}}
            labels={{...labels, tile: '{$a} block'} as DashboardLabels}
            editing={false}
            showControls={false}
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        const content = container.querySelector('.core-my-dashboard-tile__content');
        expect(content).toHaveClass('block', 'block_calendar_month');
        // data-blockregion lives on the tile wrapper (an ancestor of .block), matching how
        // Boost's block CSS selectors expect the two to relate (e.g. "[data-blockregion] .block").
        expect(container.querySelector('.core-my-dashboard-tile')).toHaveAttribute('data-blockregion');
    });

    it('marks a displaced tile for pointer-position easing', () => {
        render(<DashboardTile
            block={{
                id: 9,
                name: 'html',
                title: 'Useful links',
                content: '',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [],
            }}
            item={{id: 9, column: 1, row: 1, columns: 2, rows: 2}}
            labels={{...labels, tile: '{$a} block'} as DashboardLabels}
            editing={false}
            showControls={false}
            shouldAnimatePosition
            isBumped
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        expect(screen.getByRole('region', {name: 'Useful links block'}))
            .toHaveAttribute('data-bumped', 'true');
    });

    it('renders large icon-only move, resize, and remove actions in edit mode', () => {
        render(<DashboardTile
            block={{
                id: 8,
                name: 'html',
                title: 'Useful links',
                content: '<p>Tile content</p>',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [],
            }}
            item={{id: 8, column: 0, row: 0, columns: 2, rows: 2}}
            labels={{
                ...labels,
                move: 'Move {$a} block',
                remove: 'Delete {$a} block',
                resize: 'Resize block',
                tile: '{$a} block',
            } as DashboardLabels}
            editing
            showControls
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        expect(screen.getByRole('button', {name: 'Move Useful links block'}))
            .toHaveClass('mds-btn--size-md');
        expect(screen.getByRole('button', {name: 'Resize block'}))
            .toHaveClass('core-my-dashboard-handle--resize');
        expect(screen.getByRole('button', {name: 'Resize block'}).closest('footer'))
            .toHaveClass('core-my-dashboard-tile__dashboard-footer');
        const remove = screen.getByRole('button', {name: 'Delete Useful links block'});
        expect(remove).toHaveAttribute('title', 'Delete Useful links block');
        expect(remove.querySelector('.fa-trash-can')).toBeInTheDocument();
        expect(remove).not.toHaveTextContent('Delete Useful links block');
    });

    it('renders no block actions menu when the block has no permitted actions', () => {
        render(<BlockActionsMenu blockId={11} actions={[]} label="More actions for Useful links" />);

        expect(screen.queryByRole('button', {name: 'More actions for Useful links'})).not.toBeInTheDocument();
    });

    it('opens the block actions menu and lists each retained action as a link', () => {
        render(<BlockActionsMenu
            blockId={11}
            actions={[
                {id: 'assignroles', label: 'Assign roles', url: 'https://example.com/roles', modalform: ''},
                {id: 'permissions', label: 'Permissions', url: 'https://example.com/perm', modalform: ''},
            ]}
            label="More actions for Useful links"
        />);

        const trigger = screen.getByRole('button', {name: 'More actions for Useful links'});
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(trigger).toHaveAttribute('aria-expanded', 'false');

        fireEvent.click(trigger);

        expect(trigger).toHaveAttribute('aria-expanded', 'true');
        const rolesitem = screen.getByRole('menuitem', {name: 'Assign roles'});
        expect(rolesitem).toHaveAttribute('href', 'https://example.com/roles');
        expect(screen.getByRole('menuitem', {name: 'Permissions'})).toHaveAttribute('href', 'https://example.com/perm');

        fireEvent.click(rolesitem);
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('carries the legacy modal-form data attributes for the configure action only', () => {
        render(<BlockActionsMenu
            blockId={12}
            actions={[
                {
                    id: 'edit',
                    label: 'Configure Useful links block',
                    url: 'https://example.com/edit',
                    modalform: 'block_html\\form\\edit',
                },
                {id: 'permissions', label: 'Permissions', url: 'https://example.com/perm', modalform: ''},
            ]}
            label="More actions for Useful links"
        />);

        fireEvent.click(screen.getByRole('button', {name: 'More actions for Useful links'}));

        const configure = screen.getByRole('menuitem', {name: 'Configure Useful links block'});
        expect(configure).toHaveAttribute('data-action', 'editblock');
        expect(configure).toHaveAttribute('data-blockid', '12');
        expect(configure).toHaveAttribute('data-blockform', 'block_html\\form\\edit');
        expect(configure).toHaveAttribute('data-header', 'Configure Useful links block');

        const permissions = screen.getByRole('menuitem', {name: 'Permissions'});
        expect(permissions).not.toHaveAttribute('data-action');
        expect(permissions).not.toHaveAttribute('data-blockform');
    });

    it('closes the block actions menu on Escape and returns focus to the trigger', () => {
        render(<BlockActionsMenu
            blockId={11}
            actions={[{id: 'permissions', label: 'Permissions', url: 'https://example.com/perm', modalform: ''}]}
            label="More actions for Useful links"
        />);

        const trigger = screen.getByRole('button', {name: 'More actions for Useful links'});
        fireEvent.click(trigger);
        expect(screen.getByRole('menu')).toBeInTheDocument();

        fireEvent.keyDown(document, {key: 'Escape'});

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
    });

    it('moves focus onto the first item on open, then wraps with the arrow keys', () => {
        render(<BlockActionsMenu
            blockId={11}
            actions={[
                {id: 'edit', label: 'Configure', url: 'https://example.com/edit', modalform: ''},
                {id: 'permissions', label: 'Permissions', url: 'https://example.com/perm', modalform: ''},
            ]}
            label="More actions for Useful links"
        />);

        fireEvent.click(screen.getByRole('button', {name: 'More actions for Useful links'}));

        const configure = screen.getByRole('menuitem', {name: 'Configure'});
        const permissions = screen.getByRole('menuitem', {name: 'Permissions'});
        expect(configure).toHaveFocus();

        fireEvent.keyDown(configure, {key: 'ArrowDown'});
        expect(permissions).toHaveFocus();

        fireEvent.keyDown(permissions, {key: 'ArrowDown'});
        expect(configure).toHaveFocus();

        fireEvent.keyDown(configure, {key: 'ArrowUp'});
        expect(permissions).toHaveFocus();

        fireEvent.keyDown(permissions, {key: 'Home'});
        expect(configure).toHaveFocus();

        fireEvent.keyDown(configure, {key: 'End'});
        expect(permissions).toHaveFocus();
    });

    it('only shows the dashboard tile\'s block actions trigger when the block has actions', () => {
        const withoutActions = render(<DashboardTile
            block={{
                id: 13,
                name: 'html',
                title: 'Useful links',
                content: '',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [],
            }}
            item={{id: 13, column: 0, row: 0, columns: 2, rows: 2}}
            labels={{
                ...labels,
                move: 'Move {$a} block',
                remove: 'Delete {$a} block',
                tile: '{$a} block',
            } as DashboardLabels}
            editing
            showControls={false}
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        expect(withoutActions.queryByRole('button', {name: /More actions/})).not.toBeInTheDocument();
        withoutActions.unmount();

        render(<DashboardTile
            block={{
                id: 14,
                name: 'html',
                title: 'Useful links',
                content: '',
                footer: '',
                region: 'content',
                weight: 0,
                actions: [{id: 'permissions', label: 'Permissions', url: 'https://example.com/perm', modalform: ''}],
            }}
            item={{id: 14, column: 0, row: 0, columns: 2, rows: 2}}
            labels={{
                ...labels,
                move: 'Move {$a} block',
                remove: 'Delete {$a} block',
                tile: '{$a} block',
                blockactions: 'More actions for {$a}',
            } as DashboardLabels}
            editing
            showControls={false}
            onStart={jest.fn()}
            onKeyDown={jest.fn()}
            onPointerDown={jest.fn()}
            onDirection={jest.fn()}
            onCommit={jest.fn()}
            onRemove={jest.fn()}
        />);

        const trigger = screen.getByRole('button', {name: 'More actions for Useful links'});
        expect(trigger).toBeInTheDocument();

        const remove = screen.getByRole('button', {name: 'Delete Useful links block'});
        const actionsRow = remove.closest('.core-my-dashboard-tile__actions');
        const children = Array.from(actionsRow?.children ?? []);
        expect(children.indexOf(remove)).toBeLessThan(
            children.findIndex(child => child.contains(trigger)),
        );
    });

    it('uses a native modal shell with MDS confirmation controls', () => {
        HTMLDialogElement.prototype.showModal = jest.fn(function(this: HTMLDialogElement) {
            this.setAttribute('open', '');
        });
        const confirm = jest.fn();
        render(<ConfirmationDialog
            title="Remove block"
            message="Remove this block?"
            confirmLabel="Confirm"
            cancelLabel="Cancel"
            onConfirm={confirm}
            onCancel={jest.fn()}
        />);

        expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalled();
        fireEvent.click(screen.getByRole('button', {name: 'Confirm'}));
        expect(confirm).toHaveBeenCalled();
    });

    it('uses a native modal shell for the replaceable block palette', () => {
        HTMLDialogElement.prototype.showModal = jest.fn(function(this: HTMLDialogElement) {
            this.setAttribute('open', '');
        });
        const select = jest.fn();
        render(<BlockPalette
            title="Add a block"
            closeLabel="Close"
            blocks={[{name: 'online_users', title: 'Online users'}]}
            onSelect={select}
            onClose={jest.fn()}
        />);

        fireEvent.click(screen.getByRole('button', {name: 'Online users'}));
        expect(select).toHaveBeenCalledWith({name: 'online_users', title: 'Online users'});
    });

    it('indicates the own-dashboard scope and offers no switch without the other capability', () => {
        render(<DashboardScopeBanner
            siteDefault={false}
            caneditotherscope={false}
            urls={urls}
            labels={labels}
        />);

        expect(screen.getByText('Editing your dashboard')).toBeInTheDocument();
        expect(screen.queryByText('Switch to editing the default dashboard for all users')).not.toBeInTheDocument();
    });

    it('indicates the site-default scope and offers a switch back to the user\'s own dashboard', () => {
        render(<DashboardScopeBanner
            siteDefault
            caneditotherscope
            urls={urls}
            labels={labels}
        />);

        expect(screen.getByText('Editing the default dashboard for all users')).toBeInTheDocument();
        const link = screen.getByText('Switch to editing your dashboard');
        expect(link.closest('a')).toHaveAttribute('href', urls.ownpage);
    });
});
