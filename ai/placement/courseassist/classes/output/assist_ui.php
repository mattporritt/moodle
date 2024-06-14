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

namespace aiplacement_courseassist\output;

/**
 * Output handler for the course assist AI Placement.
 *
 * @package    aiplacement_courseassist
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assist_ui {
    /**
     * Bootstrap the course assist UI.
     *
     */
    public static function load_assist_ui(): void {
        global $PAGE;

        // Preflight checks.
        if (during_initial_install()) {
            return;
        }

        if (!get_config('aiplacement_courseassist', 'version')) {
            return;
        }

        // Check we are in the right context, exit if not course or activity.
        if ($PAGE->context->contextlevel < 50) {
            return;
        }

        $PAGE->requires->js_call_amd('aiplacement_courseassist/placement', 'init', []);
    }
}
