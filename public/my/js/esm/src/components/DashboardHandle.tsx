// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Accessible move and resize handle for dashboard tiles.
 *
 * @module     core_my/components/DashboardHandle
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {Button} from '@moodlehq/design-system';

interface DashboardHandleProps {
    mode: 'move' | 'resize';
    label: string;
    instructionsId: string;
    active: boolean;
    onStart: () => void;
    onKeyDown: (event: React.KeyboardEvent) => void;
    onPointerDown: (event: React.PointerEvent) => void;
}

const DashboardHandle = ({
    mode,
    label,
    instructionsId,
    active,
    onStart,
    onKeyDown,
    onPointerDown,
}: DashboardHandleProps) => <Button
    size="md"
    variant="ghost"
    className={`core-my-dashboard-handle core-my-dashboard-handle--${mode}`}
    aria-label={label}
    aria-describedby={instructionsId}
    aria-pressed={active}
    title={label}
    startIcon={<i
        className={`fa fa-${mode === 'move'
            ? 'arrows-up-down-left-right'
            : 'up-right-and-down-left-from-center'}`}
        aria-hidden="true"
    />}
    onClick={event => {
        // Pointer activation starts on pointerdown so dragging works immediately.
        // A zero-detail click covers keyboard and assistive-technology activation.
        if (event.detail === 0) {
            onStart();
        }
    }}
    onKeyDown={onKeyDown}
    onPointerDown={event => {
        event.currentTarget.focus();
        onPointerDown(event);
    }}
/>;

export default DashboardHandle;
