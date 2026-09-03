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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_my\local\dashboard;

/**
 * Fetch the responsive dashboard data.
 *
 * @package    core_my
 * @category   external
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_dashboard extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sitedefault' => new external_value(PARAM_BOOL, 'Whether to fetch the site-default dashboard.'),
        ]);
    }

    /**
     * Execute the request.
     *
     * @param bool $sitedefault Whether to fetch the site default.
     * @return array
     */
    public static function execute(bool $sitedefault): array {
        ['sitedefault' => $sitedefault] = self::validate_parameters(self::execute_parameters(), [
            'sitedefault' => $sitedefault,
        ]);
        $context = $sitedefault ? \context_system::instance() : \context_user::instance($GLOBALS['USER']->id);
        self::validate_context($context);
        if ($sitedefault) {
            require_capability('moodle/my:configsyspages', $context);
        }
        return dashboard::get($sitedefault);
    }

    /**
     * Define return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'blocks' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Block instance id.'),
                'name' => new external_value(PARAM_PLUGIN, 'Block plugin name.'),
                'title' => new external_value(PARAM_RAW, 'Rendered block title.'),
                'content' => new external_value(PARAM_RAW, 'Rendered block content.'),
                'footer' => new external_value(PARAM_RAW, 'Rendered block footer.'),
                'region' => new external_value(PARAM_ALPHANUMEXT, 'Legacy block region.'),
                'weight' => new external_value(PARAM_INT, 'Legacy block weight.'),
            ])),
            'layout' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Block instance id.'),
                'column' => new external_value(PARAM_INT, 'Zero-based column.'),
                'row' => new external_value(PARAM_INT, 'Zero-based row.'),
                'columns' => new external_value(PARAM_INT, 'Column span.'),
                'rows' => new external_value(PARAM_INT, 'Row span.'),
            ])),
            'availableblocks' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_PLUGIN, 'Block plugin name.'),
                'title' => new external_value(PARAM_RAW, 'Block title.'),
            ])),
            'canedit' => new external_value(PARAM_BOOL, 'Whether the user may edit.'),
            'editing' => new external_value(PARAM_BOOL, 'Whether Edit mode is active.'),
            'sitedefault' => new external_value(PARAM_BOOL, 'Whether this is the site default.'),
            'caneditotherscope' => new external_value(
                PARAM_BOOL,
                'Whether the user may also edit the other dashboard scope (own page versus site default).'
            ),
            'urls' => new external_single_structure([
                'ownpage' => new external_value(PARAM_URL, 'URL of the user\'s own dashboard.'),
                'sitedefault' => new external_value(PARAM_URL, 'URL of the site-default dashboard.'),
            ]),
            'javascript' => new external_value(PARAM_RAW, 'Collected block JavaScript requirements.'),
            'labels' => new external_single_structure([
                'addblock' => new external_value(PARAM_RAW, 'Add block label.'),
                'addblocktop' => new external_value(PARAM_RAW, 'Top add block label.'),
                'addblockbottom' => new external_value(PARAM_RAW, 'Bottom add block label.'),
                'cancel' => new external_value(PARAM_RAW, 'Cancel label.'),
                'close' => new external_value(PARAM_RAW, 'Close label.'),
                'confirm' => new external_value(PARAM_RAW, 'Confirm label.'),
                'confirmremove' => new external_value(PARAM_RAW, 'Remove confirmation text.'),
                'confirmreset' => new external_value(PARAM_RAW, 'Reset confirmation text.'),
                'done' => new external_value(PARAM_RAW, 'Done label.'),
                'down' => new external_value(PARAM_RAW, 'Down label.'),
                'emptycell' => new external_value(PARAM_RAW, 'Empty cell label.'),
                'gridcell' => new external_value(PARAM_RAW, 'Grid cell position label.'),
                'left' => new external_value(PARAM_RAW, 'Left label.'),
                'loading' => new external_value(PARAM_RAW, 'Loading label.'),
                'move' => new external_value(PARAM_RAW, 'Move label.'),
                'movecontrols' => new external_value(PARAM_RAW, 'Move controls label.'),
                'moveinstructions' => new external_value(PARAM_RAW, 'Move handle instructions.'),
                'remove' => new external_value(PARAM_RAW, 'Remove label.'),
                'removeheading' => new external_value(PARAM_RAW, 'Remove dialog heading.'),
                'reset' => new external_value(PARAM_RAW, 'Reset label.'),
                'resetheading' => new external_value(PARAM_RAW, 'Reset dialog heading.'),
                'resize' => new external_value(PARAM_RAW, 'Resize label.'),
                'resizecontrols' => new external_value(PARAM_RAW, 'Resize controls label.'),
                'resizeinstructions' => new external_value(PARAM_RAW, 'Resize handle instructions.'),
                'right' => new external_value(PARAM_RAW, 'Right label.'),
                'scopeown' => new external_value(PARAM_RAW, 'Own-dashboard scope indicator label.'),
                'scopesitedefault' => new external_value(PARAM_RAW, 'Site-default scope indicator label.'),
                'switchtoown' => new external_value(PARAM_RAW, 'Switch-to-own-dashboard CTA label.'),
                'switchtositedefault' => new external_value(PARAM_RAW, 'Switch-to-site-default CTA label.'),
                'tile' => new external_value(PARAM_RAW, 'Dashboard tile label.'),
                'up' => new external_value(PARAM_RAW, 'Up label.'),
            ]),
        ]);
    }
}
