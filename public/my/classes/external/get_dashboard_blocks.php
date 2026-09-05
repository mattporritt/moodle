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

namespace core_my\external;

use core_external\external_api;
use core_external\external_files;
use core_external\external_format_value;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_my\local\dashboard;

/**
 * Return the current user's flexible dashboard blocks, in their real on-screen order.
 *
 * Replaces {@see \core_block_external::get_dashboard_blocks()} for the default dashboard: that
 * function reports each block's legacy region and weight, which the flexible dashboard grid
 * (MDL-89636) no longer keeps up to date once a user edits their layout - only the grid's own
 * column/row/columns/rows position does. This function returns that real position directly, with
 * blocks pre-sorted into the same row-major reading order the web grid uses.
 *
 * Scoped to the current user's own default dashboard only. The site default and the My courses
 * page (a separate page, unaffected by the flexible dashboard rewrite) are out of scope here;
 * My courses is unaffected and continues to be served by get_dashboard_blocks.
 *
 * @package    core_my
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_dashboard_blocks extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Fetch the current user's dashboard blocks and their real grid position.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        $dashboard = dashboard::get(false);

        $layoutbyid = [];
        foreach ($dashboard['layout'] as $item) {
            $layoutbyid[$item['id']] = $item;
        }

        $blocks = $dashboard['blocks'];
        usort($blocks, static function (array $left, array $right) use ($layoutbyid): int {
            $leftposition = $layoutbyid[$left['id']] ?? ['row' => 0, 'column' => 0];
            $rightposition = $layoutbyid[$right['id']] ?? ['row' => 0, 'column' => 0];
            return [$leftposition['row'], $leftposition['column'], $left['id']]
                <=> [$rightposition['row'], $rightposition['column'], $right['id']];
        });

        $result = [];
        foreach ($blocks as $block) {
            $position = $layoutbyid[$block['id']] ?? null;
            $result[] = [
                'instanceid' => $block['id'],
                'name' => $block['name'],
                'contents' => [
                    'title' => $block['title'],
                    'content' => $block['content'],
                    'contentformat' => FORMAT_HTML,
                    'footer' => $block['footer'],
                    'files' => [],
                ],
                'column' => $position['column'] ?? 0,
                'row' => $position['row'] ?? 0,
                'columns' => $position['columns'] ?? 0,
                'rows' => $position['rows'] ?? 0,
            ];
        }

        return [
            'blocks' => $result,
            'warnings' => [],
        ];
    }

    /**
     * Define return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'blocks' => new external_multiple_structure(new external_single_structure([
                'instanceid' => new external_value(PARAM_INT, 'Block instance id.'),
                'name' => new external_value(PARAM_PLUGIN, 'Block plugin name.'),
                'contents' => new external_single_structure([
                    'title' => new external_value(PARAM_RAW, 'Block title.'),
                    'content' => new external_value(PARAM_RAW, 'Block contents.'),
                    'contentformat' => new external_format_value('content'),
                    'footer' => new external_value(PARAM_RAW, 'Block footer.'),
                    'files' => new external_files('Block files.'),
                ], 'Block contents.'),
                'column' => new external_value(PARAM_INT, 'Zero-based grid column.'),
                'row' => new external_value(PARAM_INT, 'Zero-based grid row.'),
                'columns' => new external_value(PARAM_INT, 'Grid column span.'),
                'rows' => new external_value(PARAM_INT, 'Grid row span.'),
            ]), 'Dashboard blocks, in the same reading order shown on the web.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
