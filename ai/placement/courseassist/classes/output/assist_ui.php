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

use core\hook\output\before_footer_html_generation;

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
     * @param before_footer_html_generation $hook
     */
    public static function load_assist_ui(before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT;

        // Preflight checks.
        if (during_initial_install()) {
            return;
        }

        if (!get_config('aiplacement_courseassist', 'version')) {
            return;
        }

        if (in_array($PAGE->pagelayout, ['maintenance', 'print', 'redirect'])) {
            // Do not try to show user tours inside iframe, in maintenance mode,
            // when printing, or during redirects.
            return;
        }

        // Check we are in the right context, exit if not course or activity.
        if ($PAGE->context->contextlevel < 50) {
            return;
        }

        // Load the markup for the assist interface.
        $params = [];
        $html = $OUTPUT->render_from_template('aiplacement_courseassist/sidebar', $params);
        $hook->add_html($html);

        // Load the required JS.
        $PAGE->requires->js_call_amd('aiplacement_courseassist/placement', 'init', []);

    }
}
