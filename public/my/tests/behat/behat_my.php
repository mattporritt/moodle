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
}
