<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiplacement_reportbuilder;

/**
 * AI actions offered from a report.
 *
 * Reports are the one surface where a user acts on many rows at once, so this is where an AI action can be requested
 * in bulk rather than one item at a time. That is the reason it is worth managing separately from the placements that
 * offer the same actions one item at a time: a site can allow AI image descriptions while editing, and still withhold
 * the button that requests them for a whole course.
 *
 * @package    aiplacement_reportbuilder
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement extends \core_ai\placement {
    #[\Override]
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\describe_image::class,
        ];
    }
}
