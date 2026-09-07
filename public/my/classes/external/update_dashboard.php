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
 * Persist layout changes and perform dashboard block actions.
 *
 * A single endpoint for every dashboard mutation (save/add/remove/reset), rather than one
 * function per action, keeps the client to one call shape for every button on the grid; the
 * capability check for the requested action is left to the corresponding
 * {@see \core_my\local\dashboard} method itself (each of save(), add(), remove() and reset()
 * calls require_capability()/require_edit_capability() before touching anything), since which
 * capability applies can depend on the action (reset(), for example, is only ever meaningful for
 * a user's own dashboard, never the site default).
 *
 * @package    core_my
 * @category   external
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_dashboard extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'One of save, add, remove or reset.'),
            'sitedefault' => new external_value(PARAM_BOOL, 'Whether to update the site default.'),
            'layout' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Block instance id.'),
                'column' => new external_value(PARAM_INT, 'Zero-based column.'),
                'row' => new external_value(PARAM_INT, 'Zero-based row.'),
                'columns' => new external_value(PARAM_INT, 'Column span.'),
                'rows' => new external_value(PARAM_INT, 'Row span.'),
            ]), 'Layout items.', VALUE_DEFAULT, []),
            'blockname' => new external_value(PARAM_PLUGIN, 'Block plugin name.', VALUE_DEFAULT, ''),
            'blockid' => new external_value(PARAM_INT, 'Block instance id.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the update.
     *
     * @param string $action Action name.
     * @param bool $sitedefault Whether to update the site default.
     * @param array $layout Layout items.
     * @param string $blockname Block plugin name.
     * @param int $blockid Block instance id.
     * @return array
     */
    public static function execute(
        string $action,
        bool $sitedefault,
        array $layout = [],
        string $blockname = '',
        int $blockid = 0,
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'sitedefault' => $sitedefault,
            'layout' => $layout,
            'blockname' => $blockname,
            'blockid' => $blockid,
        ]);
        $context = $params['sitedefault']
            ? \context_system::instance()
            : \context_user::instance($GLOBALS['USER']->id);
        self::validate_context($context);
        require_sesskey();

        $newblockid = 0;
        switch ($params['action']) {
            case 'save':
                // Persist a full drag/resize layout, e.g. after the user releases a drag handle.
                dashboard::save($params['sitedefault'], $params['layout']);
                break;
            case 'add':
                // A new block's position and rendered content are both server-assigned, so the
                // client cannot draw it optimistically; it reloads via get_dashboard afterwards.
                $newblockid = dashboard::add($params['sitedefault'], $params['blockname']);
                break;
            case 'remove':
                dashboard::remove($params['sitedefault'], $params['blockid']);
                break;
            case 'reset':
                // Resetting a user's own dashboard means "discard my customisation and revert to
                // the site default" (see dashboard::reset(), which calls my_reset_page()) - a
                // concept that is meaningless for the site default dashboard itself, since there
                // is nothing above it to revert to. Resetting every user's dashboard at once is a
                // distinct, deliberately separate admin action (see my/indexsys.php's resetall
                // flow), not something this generic per-scope endpoint should also be able to do.
                if ($params['sitedefault']) {
                    throw new \invalid_parameter_exception('The site-default dashboard cannot reset itself.');
                }
                dashboard::reset();
                break;
            default:
                throw new \invalid_parameter_exception('Unknown dashboard update action.');
        }

        return ['status' => true, 'blockid' => $newblockid];
    }

    /**
     * Define return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the action succeeded.'),
            'blockid' => new external_value(PARAM_INT, 'New block instance id, or zero.'),
        ]);
    }
}
