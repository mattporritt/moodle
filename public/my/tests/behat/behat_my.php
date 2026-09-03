<?php
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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

/**
 * Steps definitions related to the dashboard.
 *
 * @package    core_my
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_my extends behat_base {
    /**
     * Assert that the page-level dashboard editing switch is off.
     *
     * @Then the dashboard edit mode switch should be off
     */
    public function dashboard_edit_mode_switch_should_be_off(): void {
        $this->spin(
            function ($context): bool {
                return $context->evaluate_script(<<<'JS'
                    return document.querySelector('input[name="setmode"]')?.checked === false;
                JS);
            },
            false,
            5,
            new \Exception('The dashboard edit mode switch is still on after resetting the dashboard.')
        );
    }

    /**
     * Drag a dashboard block one column into free grid space and verify its saved position.
     *
     * @Then dragging the :block dashboard block into an adjacent free column persists
     * @param string $block Block name.
     */
    public function dragging_dashboard_block_into_an_adjacent_free_column_persists(string $block): void {
        $drag = $this->evaluate_script(<<<JS
            return (() => {
                const tile = document.querySelector('.core-my-dashboard-tile[data-block="{$block}"]');
                const handle = tile?.querySelector('.core-my-dashboard-handle--move');
                const grid = document.querySelector('.core-my-dashboard-grid');
                if (!tile || !handle || !grid) {
                    return null;
                }
                const tilerect = tile.getBoundingClientRect();
                const handlerect = handle.getBoundingClientRect();
                const gridrect = grid.getBoundingClientRect();
                const columns = Number(grid.dataset.columns);
                const gap = parseFloat(getComputedStyle(grid).gap);
                const stride = (gridrect.width - gap * (columns - 1)) / columns + gap;
                const column = Number(getComputedStyle(tile).gridColumnStart);
                const span = Number(getComputedStyle(tile).gridColumnEnd.replace('span ', ''));
                const direction = column + span <= columns ? 1 : -1;
                if (direction < 0 && column <= 1) {
                    return {error: 'The fixture does not have an adjacent free column.'};
                }
                const x = handlerect.left + handlerect.width / 2;
                const y = handlerect.top + handlerect.height / 2;
                const pointer = (type, clientx, buttons) => new PointerEvent(type, {
                    bubbles: true,
                    button: 0,
                    buttons,
                    clientX: clientx,
                    clientY: y,
                    isPrimary: true,
                    pointerId: 3,
                    pointerType: 'mouse',
                });
                handle.dispatchEvent(pointer('pointerdown', x, 1));
                window.dispatchEvent(pointer('pointermove', x + direction * stride * 0.75, 1));
                return {column, expected: column + direction, x, y, stride, direction};
            })();
        JS);

        if ($drag === null || isset($drag['error'])) {
            throw new \Exception($drag['error'] ?? 'The dashboard move handle was not found.');
        }

        usleep(100000);
        $preview = $this->evaluate_script(<<<JS
            return (() => {
                const tile = document.querySelector('.core-my-dashboard-tile[data-block="{$block}"]');
                return {
                    dragging: tile?.classList.contains('core-my-dashboard-tile--pointer-dragging'),
                    transform: tile?.style.transform,
                };
            })();
        JS);
        if (empty($preview['dragging']) || empty($preview['transform'])) {
            throw new \Exception('Dashboard move did not produce a fluid drag preview: ' . json_encode($preview));
        }
        $this->evaluate_script(<<<JS
            window.dispatchEvent(new PointerEvent('pointerup', {
                bubbles: true,
                button: 0,
                buttons: 0,
                clientX: {$drag['x']} + {$drag['direction']} * {$drag['stride']} * 0.75,
                clientY: {$drag['y']},
                isPrimary: true,
                pointerId: 3,
                pointerType: 'mouse',
            }));
            return true;
        JS);
        usleep(500000);
        $actual = (int)$this->evaluate_script(<<<JS
            return getComputedStyle(document.querySelector('.core-my-dashboard-tile[data-block="{$block}"]')).gridColumnStart;
        JS);
        if ($actual !== $drag['expected']) {
            throw new \Exception("Dashboard block '{$block}' starts in grid column '{$actual}', expected '{$drag['expected']}'.");
        }
    }

    /**
     * Assert that keyboard activation exposes the discrete resize controls.
     *
     * @Then keyboard activation shows resize directional controls
     */
    public function keyboard_activation_shows_resize_directional_controls(): void {
        $activated = $this->evaluate_script(<<<'JS'
            return (() => {
                const handle = document.querySelector(
                    ".core-my-dashboard-tile[data-block='calendar_month'] .core-my-dashboard-handle--resize"
                );
                if (!handle) {
                    return null;
                }
                handle.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Enter'}));
                return true;
            })();
        JS);

        if ($activated === null) {
            throw new \Exception('The Calendar resize handle was not found.');
        }

        usleep(100000);
        $controls = $this->evaluate_script(<<<'JS'
            return document.querySelectorAll('.core-my-grid-controls__direction').length;
        JS);
        $this->evaluate_script(<<<'JS'
            document.querySelector(".core-my-dashboard-tile[data-block='calendar_month'] .core-my-dashboard-handle--resize")
                ?.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Escape'}));
            return true;
        JS);

        if ($controls !== 4) {
            throw new \Exception('Keyboard activation did not show all resize directional controls.');
        }
    }

    /**
     * Assert that a pointer resize stays fluid until it crosses a grid-cell threshold.
     *
     * @Then the mouse resize preview follows the pointer before snapping to a grid cell
     */
    public function mouse_resize_preview_follows_the_pointer(): void {
        $start = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector(".core-my-dashboard-tile[data-block='calendar_month']");
                const handle = tile?.querySelector('.core-my-dashboard-handle--resize');
                const grid = document.querySelector('.core-my-dashboard-grid');
                if (!tile || !handle || !grid) {
                    return null;
                }

                const tilerect = tile.getBoundingClientRect();
                const gridrect = grid.getBoundingClientRect();
                const columns = Number(grid.dataset.columns);
                const gap = parseFloat(getComputedStyle(grid).gap);
                const cellwidth = (gridrect.width - gap * (columns - 1)) / columns;
                const pointer = (type, x) => new PointerEvent(type, {
                    bubbles: true,
                    button: 0,
                    buttons: type === 'pointerup' || type === 'pointercancel' ? 0 : 1,
                    clientX: x,
                    clientY: tilerect.bottom - 20,
                    isPrimary: true,
                    pointerId: 1,
                    pointerType: 'mouse',
                });
                const x = tilerect.right - 20;
                handle.dispatchEvent(pointer('pointerdown', x));
                window.dispatchEvent(pointer('pointermove', x + (cellwidth + gap) * 0.4));
                return {x, y: tilerect.bottom - 20, stride: cellwidth + gap, width: tilerect.width};
            })();
        JS);

        if ($start === null) {
            throw new \Exception('The Calendar resize handle was not found.');
        }

        usleep(100000);
        $beforethreshold = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector(".core-my-dashboard-tile[data-block='calendar_month']");
                return {
                    dragging: tile?.classList.contains('core-my-dashboard-tile--pointer-dragging'),
                    width: parseFloat(tile?.style.width || '0'),
                    prospective: document.querySelectorAll('.core-my-grid-cell--prospective').length,
                    controls: document.querySelectorAll('.core-my-grid-controls__direction').length,
                };
            })();
        JS);

        if (
            !$beforethreshold['dragging'] || $beforethreshold['width'] <= $start['width'] ||
                $beforethreshold['prospective'] !== 0 || $beforethreshold['controls'] !== 0
        ) {
            throw new \Exception('Resize did not remain fluid below the grid-cell threshold: ' .
                json_encode($beforethreshold));
        }

        $this->evaluate_script(<<<JS
            window.dispatchEvent(new PointerEvent('pointermove', {
                bubbles: true,
                buttons: 1,
                clientX: {$start['x']} + {$start['stride']} * 0.6,
                clientY: {$start['y']},
                isPrimary: true,
                pointerId: 1,
                pointerType: 'mouse',
            }));
            return true;
        JS);
        usleep(100000);
        $afterthreshold = $this->evaluate_script(<<<'JS'
            return {
                prospective: document.querySelectorAll('.core-my-grid-cell--prospective').length,
                controls: document.querySelectorAll('.core-my-grid-controls__direction').length,
            };
        JS);
        $this->evaluate_script(<<<'JS'
            window.dispatchEvent(new PointerEvent('pointercancel', {
                bubbles: true,
                pointerId: 1,
                pointerType: 'mouse',
            }));
            return true;
        JS);

        if ($afterthreshold['prospective'] === 0 || $afterthreshold['controls'] !== 0) {
            throw new \Exception('Pointer resize controls or snap preview are incorrect after crossing the threshold: ' .
                json_encode($afterthreshold));
        }

        $shrinkstart = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector(".core-my-dashboard-tile[data-block='calendar_month']");
                const handle = tile?.querySelector('.core-my-dashboard-handle--resize');
                const grid = document.querySelector('.core-my-dashboard-grid');
                if (!tile || !handle || !grid) {
                    return null;
                }

                const tilerect = tile.getBoundingClientRect();
                const gridrect = grid.getBoundingClientRect();
                const columns = Number(grid.dataset.columns);
                const gap = parseFloat(getComputedStyle(grid).gap);
                const stride = (gridrect.width - gap * (columns - 1)) / columns + gap;
                const x = tilerect.right - 20;
                const y = tilerect.bottom - 20;
                const pointer = (type, clientx) => new PointerEvent(type, {
                    bubbles: true,
                    buttons: 1,
                    clientX: clientx,
                    clientY: y,
                    isPrimary: true,
                    pointerId: 2,
                    pointerType: 'mouse',
                });
                handle.dispatchEvent(pointer('pointerdown', x));
                window.dispatchEvent(pointer('pointermove', x - stride * 0.4));
                return {width: tilerect.width};
            })();
        JS);

        if ($shrinkstart === null) {
            throw new \Exception('The Calendar resize handle was not found for the shrink preview.');
        }

        usleep(100000);
        $shrinkpreview = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector(".core-my-dashboard-tile[data-block='calendar_month']");
                return {
                    width: parseFloat(tile?.style.width || '0'),
                    prospective: document.querySelectorAll('.core-my-grid-cell--prospective').length,
                };
            })();
        JS);
        $this->evaluate_script(<<<'JS'
            window.dispatchEvent(new PointerEvent('pointercancel', {
                bubbles: true,
                pointerId: 2,
                pointerType: 'mouse',
            }));
            return true;
        JS);

        if ($shrinkpreview['width'] >= $shrinkstart['width'] || $shrinkpreview['prospective'] === 0) {
            throw new \Exception('Resize did not show its occupied cells while shrinking below the snap threshold: ' .
                json_encode($shrinkpreview));
        }
    }

    /**
     * Assert that a pointer resize eases blocks that are displaced by its new grid footprint.
     *
     * @Then blocks bumped by a pointer resize ease into their new grid position
     */
    public function bumped_blocks_ease_into_their_new_grid_position(): void {
        // A resize handle only ever grows a tile rightwards from its fixed left edge, so the
        // tile needs real content in the way of that growth to produce a bump. With the default
        // layout's empty margin columns, Calendar (anchored hard against the grid's right edge)
        // has no room left to grow into and would never collide with anything. Course overview
        // starts with room to spare before the Calendar column, so growing it is guaranteed to
        // collide with, and bump, its row neighbour.
        $start = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector(".core-my-dashboard-tile[data-block='myoverview']");
                const handle = tile?.querySelector('.core-my-dashboard-handle--resize');
                const grid = document.querySelector('.core-my-dashboard-grid');
                if (!tile || !handle || !grid) {
                    return null;
                }
                const tileRect = tile.getBoundingClientRect();
                const gridRect = grid.getBoundingClientRect();
                const columns = Number(grid.dataset.columns);
                const gap = parseFloat(getComputedStyle(grid).gap);
                const stride = (gridRect.width - gap * (columns - 1)) / columns + gap;
                const x = tileRect.right - 20;
                const y = tileRect.bottom - 20;
                const pointer = (type, clientX, buttons) => new PointerEvent(type, {
                    bubbles: true,
                    button: 0,
                    buttons,
                    clientX,
                    clientY: y,
                    isPrimary: true,
                    pointerId: 4,
                    pointerType: 'mouse',
                });
                handle.dispatchEvent(pointer('pointerdown', x, 1));
                window.dispatchEvent(pointer('pointermove', x + stride * 0.75, 1));
                return {x, y};
            })();
        JS);

        if ($start === null) {
            throw new \Exception('The Course overview resize handle was not found.');
        }

        usleep(100000);
        $animation = $this->evaluate_script(<<<'JS'
            return (() => {
                const tile = document.querySelector('.core-my-dashboard-tile[data-bumped="true"]');
                if (!tile) {
                    return null;
                }
                return {
                    animations: tile.getAnimations().map(animation => ({
                        duration: animation.effect?.getTiming().duration,
                        state: animation.playState,
                    })),
                };
            })();
        JS);
        if (
            $animation === null ||
            !array_filter(
                $animation['animations'],
                fn(array $animation): bool => $animation['duration'] === 180 && $animation['state'] !== 'finished'
            )
        ) {
            throw new \Exception('A bumped dashboard tile did not run its position easing animation: ' .
                json_encode($animation));
        }

        $returning = $this->evaluate_script(<<<JS
            return (() => {
                const tile = document.querySelector('.core-my-dashboard-tile[data-bumped="true"]');
                const grid = document.querySelector('.core-my-dashboard-grid');
                const handle = document.querySelector(
                    ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-handle--resize"
                );
                if (!tile || !grid || !handle) {
                    return null;
                }
                const gridRect = grid.getBoundingClientRect();
                const columns = Number(grid.dataset.columns);
                const gap = parseFloat(getComputedStyle(grid).gap);
                const stride = (gridRect.width - gap * (columns - 1)) / columns + gap;
                window.dispatchEvent(new PointerEvent('pointermove', {
                    bubbles: true,
                    buttons: 1,
                    clientX: {$start['x']} + stride * 0.4,
                    clientY: {$start['y']},
                    isPrimary: true,
                    pointerId: 4,
                    pointerType: 'mouse',
                }));
                return {block: tile.dataset.block};
            })();
        JS);
        if ($returning === null) {
            throw new \Exception('The bumped dashboard tile did not remain available for the return animation.');
        }
        usleep(100000);
        $returnanimation = $this->evaluate_script(<<<JS
            return (() => {
                const tile = document.querySelector('.core-my-dashboard-tile[data-block="{$returning['block']}"]');
                if (!tile) {
                    return null;
                }
                return tile.getAnimations().map(animation => ({
                    duration: animation.effect?.getTiming().duration,
                    state: animation.playState,
                }));
            })();
        JS);
        $this->evaluate_script(<<<JS
            window.dispatchEvent(new PointerEvent('pointercancel', {
                bubbles: true,
                pointerId: 4,
                pointerType: 'mouse',
                clientX: {$start['x']},
                clientY: {$start['y']},
            }));
            return true;
        JS);
        if (
            $returnanimation === null ||
            !array_filter(
                $returnanimation,
                fn(array $animation): bool => $animation['duration'] === 180 && $animation['state'] !== 'finished'
            )
        ) {
            throw new \Exception('A returning dashboard tile did not run its position easing animation: ' .
                json_encode($returnanimation));
        }
    }

    /**
     * Assert that the four resize controls are centred around their active handle.
     *
     * @Then the resize directional controls are centred on the active resize handle
     */
    public function resize_directional_controls_are_centred(): void {
        $geometry = $this->evaluate_script(<<<'JS'
            return (() => {
                const handle = document.querySelector('.core-my-dashboard-handle--resize[aria-pressed="true"]');
                const controls = handle?.closest('.core-my-dashboard-handle-wrapper')?.querySelector('.core-my-grid-controls');
                if (!handle || !controls) {
                    return null;
                }

                const centre = rect => ({x: rect.left + rect.width / 2, y: rect.top + rect.height / 2});
                const handlecentre = centre(handle.getBoundingClientRect());
                const directions = ['up', 'left', 'right', 'down'];
                const offsets = Object.fromEntries(directions.map(direction => {
                    const control = controls.querySelector(`.core-my-grid-controls__direction--${direction}`);
                    const controlcentre = centre(control.getBoundingClientRect());
                    const iconrect = control.querySelector('i').getBoundingClientRect();
                    return [direction, {
                        x: controlcentre.x - handlecentre.x,
                        y: controlcentre.y - handlecentre.y,
                        iconwidth: iconrect.width,
                        iconheight: iconrect.height,
                        controlwidth: control.getBoundingClientRect().width,
                        controlheight: control.getBoundingClientRect().height,
                    }];
                }));

                return {offsets};
            })();
        JS);

        if ($geometry === null) {
            throw new \Exception('The active resize handle or its directional controls were not found.');
        }

        $offsets = $geometry['offsets'];
        $tolerance = 1;
        if (
            abs($offsets['up']['x']) > $tolerance || abs($offsets['down']['x']) > $tolerance ||
                abs($offsets['left']['y']) > $tolerance || abs($offsets['right']['y']) > $tolerance
        ) {
            throw new \Exception('Resize directional controls are not centred: ' . json_encode($offsets));
        }

        foreach ($offsets as $offset) {
            if (
                abs($offset['iconwidth'] - $offset['controlwidth']) > $tolerance ||
                    abs($offset['iconheight'] - $offset['controlheight']) > $tolerance
            ) {
                throw new \Exception('Resize directional icons do not fill their controls: ' . json_encode($offsets));
            }
        }
    }

    /**
     * Assert that growing a block into a row neighbour pushes that neighbour sideways into free
     * grid space, rather than relocating it to the start of the row.
     *
     * The default dashboard fixture fills the row with no free column, so the Calendar block is
     * first shrunk by one column to reproduce the "one empty column at the row's end" layout the
     * bug report was raised against, before Course overview is grown into it.
     *
     * @Then resizing the Course overview block into its row neighbour pushes it right
     */
    public function resizing_into_row_neighbour_pushes_it_right(): void {
        $this->execute('behat_general::i_click_on_in_the', ['Resize block', 'button', 'Calendar', 'block']);
        $this->execute('behat_general::i_click_on_in_the', ['Left', 'button', 'Calendar', 'block']);
        $this->execute('behat_general::i_click_on_in_the', ['Resize block', 'button', 'Calendar', 'block']);

        $result = $this->keyboard_resize_and_capture_bumped_tile('Course overview', 'myoverview', 'Right');
        if ($result['block'] !== 'calendar_month') {
            throw new \Exception('Expected the Calendar block to be pushed: ' . json_encode($result));
        }
        if ($result['before']['row'] !== $result['after']['row']) {
            throw new \Exception('The pushed row neighbour changed row instead of staying in place: ' .
                json_encode($result));
        }
        if ($result['after']['column'] <= $result['before']['column']) {
            throw new \Exception('The pushed row neighbour did not move further right into free space: ' .
                json_encode($result));
        }
    }

    /**
     * Assert that growing a block into a column neighbour pushes that neighbour downwards into
     * free grid space, rather than relocating it to the start of the grid.
     *
     * @Then resizing the Calendar block into its column neighbour pushes it down
     */
    public function resizing_into_column_neighbour_pushes_it_down(): void {
        $result = $this->keyboard_resize_and_capture_bumped_tile('Calendar', 'calendar_month', 'Down');
        if ($result['block'] !== 'recentlyaccesseditems') {
            throw new \Exception('Expected the Recently accessed items block to be pushed: ' . json_encode($result));
        }
        if ($result['before']['column'] !== $result['after']['column']) {
            throw new \Exception('The pushed column neighbour changed column instead of staying in place: ' .
                json_encode($result));
        }
        if ($result['after']['row'] <= $result['before']['row']) {
            throw new \Exception('The pushed column neighbour did not move further down into free space: ' .
                json_encode($result));
        }
    }

    /**
     * Grow a block by one grid cell in the given direction, using the same discrete-control click
     * sequence as the existing accessible-controls scenario (activate, direction, activate again
     * to commit), and capture the grid position of whichever other tile moved as a result.
     *
     * The pointer-only "bumped" marker used for the FLIP-easing animation is not set for
     * keyboard-origin interactions, so the displaced tile is identified by diffing positions
     * instead.
     *
     * @param string $blocklabel Human-readable block title, as used in "in the ... block" steps.
     * @param string $blockname Internal block name (data-block attribute value) of the resized block.
     * @param string $direction One of 'Right' or 'Down'.
     * @return array Displaced block name plus its before/after {column, row}.
     */
    protected function keyboard_resize_and_capture_bumped_tile(
        string $blocklabel,
        string $blockname,
        string $direction
    ): array {
        $before = $this->read_dashboard_tile_positions();

        $this->execute('behat_general::i_click_on_in_the', ['Resize block', 'button', $blocklabel, 'block']);
        $this->execute('behat_general::i_click_on_in_the', [$direction, 'button', $blocklabel, 'block']);
        $this->execute('behat_general::i_click_on_in_the', ['Resize block', 'button', $blocklabel, 'block']);

        $after = $this->read_dashboard_tile_positions();
        $displaced = null;
        foreach ($before as $name => $position) {
            if ($name !== $blockname && $position !== ($after[$name] ?? null)) {
                $displaced = $name;
                break;
            }
        }

        if ($displaced === null) {
            throw new \Exception('No other dashboard tile moved as a result of the resize: ' .
                json_encode(['before' => $before, 'after' => $after]));
        }

        return [
            'block' => $displaced,
            'before' => $before[$displaced],
            'after' => $after[$displaced],
        ];
    }

    /**
     * Read the current grid column/row of every dashboard tile.
     *
     * @return array Map of block name to {column, row}.
     */
    protected function read_dashboard_tile_positions(): array {
        return $this->evaluate_script(<<<'JS'
            return Object.fromEntries(
                Array.from(document.querySelectorAll('.core-my-dashboard-tile[data-block]')).map(candidate => {
                    const style = getComputedStyle(candidate);
                    return [candidate.dataset.block, {
                        column: Number(style.gridColumnStart),
                        row: Number(style.gridRowStart),
                    }];
                })
            );
        JS);
    }
}
