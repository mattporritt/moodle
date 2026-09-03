// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Replaceable directional controls built with the Moodle Design System Button.
 *
 * @module     core_my/components/GridControls
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {Button} from '@moodlehq/design-system';
import type {DashboardLabels} from '../repository';

interface GridControlsProps {
    mode: 'move' | 'resize';
    labels: DashboardLabels;
    onDirection: (horizontal: number, vertical: number) => void;
    onCommit: () => void;
    onCancel: () => void;
}

const GridControls = ({mode, labels, onDirection, onCommit, onCancel}: GridControlsProps) => <div
    className="core-my-grid-controls"
    role="toolbar"
    aria-label={mode === 'move' ? labels.movecontrols : labels.resizecontrols}
>
    <Button size="sm" variant="ghost" label={labels.up} onClick={() => onDirection(0, -1)} />
    <Button size="sm" variant="ghost" label={labels.left} onClick={() => onDirection(-1, 0)} />
    <Button size="sm" variant="ghost" label={labels.right} onClick={() => onDirection(1, 0)} />
    <Button size="sm" variant="ghost" label={labels.down} onClick={() => onDirection(0, 1)} />
    <Button size="sm" variant="primary" label={labels.done} onClick={onCommit} />
    <Button size="sm" variant="secondary" label={labels.cancel} onClick={onCancel} />
</div>;

export default GridControls;
