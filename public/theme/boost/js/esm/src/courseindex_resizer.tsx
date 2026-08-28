// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Resize handle for the course index drawer.
 *
 * Renders a keyboard and pointer operable separator on the inner edge of the
 * course index drawer. The width is applied by setting the
 * --drawer-index-width custom property on the body, which the theme SCSS maps
 * into the effective drawer width at the lg breakpoint and up. The chosen
 * width is persisted as a user preference once per interaction, on drag end
 * or debounced after key presses, never per pointer move.
 *
 * @module     theme_boost/courseindex_resizer
 * @copyright  2026 A K M Safat Shahin <safatshahin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {KeyboardEvent, PointerEvent} from 'react';
import Fetch from '@moodle/lms/core/fetch';
import Pending from '@moodle/lms/core/pending';

type Props = {
    /** Smallest allowed drawer width in pixels. */
    minwidth: number;
    /** Largest allowed drawer width in pixels. */
    maxwidth: number;
    /** Name of the user preference the width is persisted to. */
    preference: string;
    /** Accessible label for the separator. */
    label: string;
    /** Element id of the drawer this handle controls. */
    drawerid: string;
};

/** The custom property the theme SCSS reads the requested width from. */
const WIDTH_PROPERTY = '--drawer-index-width';

/** Width change per arrow key press, in pixels. */
const KEY_STEP = 24;

/** Width change per arrow key press with Shift held, in pixels. */
const KEY_STEP_LARGE = 48;

/** Delay before persisting a keyboard resize, so key repeats save once, in milliseconds. */
const PERSIST_DEBOUNCE_MS = 400;

export default function CourseindexResizer({
    minwidth,
    maxwidth,
    preference,
    label,
    drawerid,
}: Props) {
    const handleRef = useRef<HTMLDivElement>(null);
    const dragState = useRef<{pointerId: number; startX: number; startWidth: number; moved: boolean} | null>(null);
    const persistTimer = useRef<number | undefined>(undefined);
    const debouncePending = useRef<Pending | null>(null);

    const clamp = useCallback(
        (value: number) => Math.min(maxwidth, Math.max(minwidth, Math.round(value))),
        [minwidth, maxwidth],
    );

    // The drawer's rendered width. Reliable whenever the handle is usable,
    // because the handle only exists while the drawer is open in push mode.
    // Returns null when the drawer is not laid out (closed, or overlay mode).
    const measureDrawerWidth = useCallback((): number | null => {
        const drawer = document.getElementById(drawerid);
        const measured = drawer ? Math.round(drawer.getBoundingClientRect().width) : 0;
        return measured > 0 ? clamp(measured) : null;
    }, [drawerid, clamp]);

    // The width currently applied to the page. The saved preference, emitted
    // pre-paint as an inline custom property on the body, wins when present.
    // Otherwise measure the drawer itself rather than assuming a default, so
    // a child theme overriding the default width in SCSS stays authoritative
    // and the first keyboard or pointer resize starts from the real width.
    const readAppliedWidth = useCallback(() => {
        const raw = parseInt(getComputedStyle(document.body).getPropertyValue(WIDTH_PROPERTY), 10);
        if (!Number.isNaN(raw)) {
            return clamp(raw);
        }
        return measureDrawerWidth() ?? minwidth;
    }, [clamp, measureDrawerWidth, minwidth]);

    const [width, setWidthState] = useState(readAppliedWidth);
    const [dragging, setDragging] = useState(false);

    // The drawer may be closed (unmeasurable) when the component mounts, so
    // re-sync the announced value when the handle actually receives focus.
    const syncWidthState = useCallback(() => {
        setWidthState(readAppliedWidth());
    }, [readAppliedWidth]);

    // In right-to-left languages the drawer sits on the right, so pointer and
    // arrow key deltas invert.
    const isRTL = () => !!handleRef.current && getComputedStyle(handleRef.current).direction === 'rtl';

    // Apply a width to the page synchronously so the drawer tracks the
    // pointer within the same frame, then mirror it into state for ARIA.
    const applyWidth = useCallback((value: number): number => {
        const clamped = clamp(value);
        document.body.style.setProperty(WIDTH_PROPERTY, `${clamped}px`);
        setWidthState(clamped);
        return clamped;
    }, [clamp]);

    const persistWidth = useCallback((value: number) => {
        const pending = new Pending('theme_boost/courseindex_resizer:save');
        Fetch.performPost('core_user', `current/preferences/${preference}`, {body: {value}})
            .catch(() => null)
            .finally(() => pending.resolve());
    }, [preference]);

    useEffect(() => () => {
        document.body.classList.remove('drawer-resizing');
        window.clearTimeout(persistTimer.current);
        debouncePending.current?.resolve();
        debouncePending.current = null;
    }, []);

    const onPointerDown = (e: PointerEvent<HTMLDivElement>) => {
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }
        dragState.current = {
            pointerId: e.pointerId,
            startX: e.clientX,
            startWidth: readAppliedWidth(),
            moved: false,
        };
        setDragging(true);
        document.body.classList.add('drawer-resizing');
        // Keep receiving pointer events when the pointer strays off the
        // handle mid-drag, without any window level listeners.
        e.currentTarget.setPointerCapture(e.pointerId);
        e.preventDefault();
        // Focus explicitly: the preventDefault above suppresses the browser's
        // click-to-focus, and clicking the handle must let arrow keys resize it.
        e.currentTarget.focus();
    };

    const onPointerMove = (e: PointerEvent<HTMLDivElement>) => {
        if (!dragState.current || e.pointerId !== dragState.current.pointerId) {
            return;
        }
        const delta = e.clientX - dragState.current.startX;
        if (Math.abs(delta) > 2) {
            dragState.current.moved = true;
        }
        applyWidth(dragState.current.startWidth + (isRTL() ? -delta : delta));
    };

    // End the drag and persist. Also wired to lostpointercapture, which fires
    // whenever the capture ends for any reason (pointerup, pointercancel, or
    // the browser reclaiming the pointer), so a drag can never be left
    // half-finished with the drawer-resizing class stuck on the body.
    const onPointerEnd = (e: PointerEvent<HTMLDivElement>) => {
        if (!dragState.current || e.pointerId !== dragState.current.pointerId) {
            return;
        }
        const {moved} = dragState.current;
        dragState.current = null;
        setDragging(false);
        document.body.classList.remove('drawer-resizing');
        if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            e.currentTarget.releasePointerCapture(e.pointerId);
        }
        // Save once at drag end, never per pointer move. A stationary click
        // only focuses the handle, so it writes nothing.
        if (moved) {
            persistWidth(readAppliedWidth());
        }
    };

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const step = e.shiftKey ? KEY_STEP_LARGE : KEY_STEP;
        const direction = isRTL() ? -1 : 1;
        // Step from the width actually applied to the page, not component
        // state, which can be stale when the drawer was closed on mount.
        const current = readAppliedWidth();
        let next: number;
        switch (e.key) {
            case 'ArrowRight':
                next = current + step * direction;
                break;
            case 'ArrowLeft':
                next = current - step * direction;
                break;
            case 'Home':
                next = minwidth;
                break;
            case 'End':
                next = maxwidth;
                break;
            default:
                return;
        }
        e.preventDefault();
        const applied = applyWidth(next);
        // Debounce the save across key repeats, but hold a pending open for
        // the whole debounce window so Behat waits for the write to land.
        window.clearTimeout(persistTimer.current);
        debouncePending.current ??= new Pending('theme_boost/courseindex_resizer:debounce');
        persistTimer.current = window.setTimeout(() => {
            persistWidth(applied);
            debouncePending.current?.resolve();
            debouncePending.current = null;
        }, PERSIST_DEBOUNCE_MS);
    };

    return (
        <div
            ref={handleRef}
            className={`drawerresizehandle${dragging ? ' dragging' : ''}`}
            role="separator"
            tabIndex={0}
            aria-orientation="vertical"
            aria-label={label}
            aria-controls={drawerid}
            aria-valuemin={minwidth}
            aria-valuemax={maxwidth}
            aria-valuenow={width}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerEnd}
            onPointerCancel={onPointerEnd}
            onLostPointerCapture={onPointerEnd}
            onFocus={syncWidthState}
            onKeyDown={onKeyDown}
        />
    );
}
