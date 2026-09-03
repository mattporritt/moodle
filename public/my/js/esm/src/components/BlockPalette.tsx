// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Replaceable block palette built with Moodle Design System controls.
 *
 * @module     core_my/components/BlockPalette
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useEffect, useRef} from 'react';
import {Button} from '@moodlehq/design-system';
import type {AvailableBlock} from '../repository';

interface BlockPaletteProps {
    title: string;
    closeLabel: string;
    blocks: AvailableBlock[];
    onSelect: (block: AvailableBlock) => void;
    onClose: () => void;
}

const BlockPalette = ({title, closeLabel, blocks, onSelect, onClose}: BlockPaletteProps) => {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        dialogRef.current?.showModal();
    }, []);

    return <dialog ref={dialogRef} className="core-my-dashboard-palette" onCancel={onClose}>
        <div className="core-my-dashboard-palette__panel">
            <div className="core-my-dashboard-palette__header">
                <h2>{title}</h2>
                <Button variant="ghost" label={closeLabel} onClick={onClose} />
            </div>
            <div className="core-my-dashboard-palette__list">
                {blocks.map(block => <Button
                    key={block.name}
                    variant="secondary"
                    label={block.title}
                    onClick={() => onSelect(block)}
                />)}
            </div>
        </div>
    </dialog>;
};

export default BlockPalette;
