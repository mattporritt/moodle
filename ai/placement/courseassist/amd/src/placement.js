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
 * Module to load and render the tools for the AI assist plugin.
 *
 * @module     aiplacement_courseassist/placement
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    window.console.log('COURSE ASSIST INITIALIZED');

    // Add the event listener to the button.
    const offCanvasContainer = document.getElementById('ai-cta-container');
    const offCanvasToggle = document.getElementById('ai-offcanvas-toggle');
    const offCanvasMenu = document.getElementById('ai-offcanvas-menu');
    const offCanvasClose = document.getElementById('ai-offcanvas-close');

    // Add the event listener to the sparkle button.
    offCanvasToggle.addEventListener('click', () => {
        offCanvasMenu.classList.toggle('show');
        offCanvasContainer.classList.toggle('slide');
    });

    // Add the event listener to the close button.
    offCanvasClose.addEventListener('click', () => {
        offCanvasMenu.classList.remove('show');
        offCanvasContainer.classList.remove('slide');
    });
};
