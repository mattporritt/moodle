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

use core_ai\manager;

/**
 * Availability checks for AI actions offered from a report.
 *
 * @package    aiplacement_reportbuilder
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Check whether one AI action can currently be requested from a report.
     *
     * @param \context $context The context the content being acted on lives in.
     * @param string $actionname The action basename, which is also the capability suffix.
     * @param string $actionclass The fully qualified action class name.
     * @param int|null $userid The user the action is requested on behalf of. Defaults to the current user. A report
     *      may request an action in the background, long after the request that asked for it, so the caller has to
     *      name the user rather than let this resolve to whoever the running session happens to belong to.
     * @param bool $checkcontext Whether to check the action is enabled in this part of the site.
     * @return bool
     */
    public static function is_placement_action_available(
        \context $context,
        string $actionname,
        string $actionclass,
        ?int $userid = null,
        bool $checkcontext = true,
    ): bool {
        if (!self::is_placement_available()) {
            return false;
        }

        $aimanager = \core\di::get(manager::class);
        return has_capability("aiplacement/reportbuilder:{$actionname}", $context, $userid)
            && $aimanager->is_action_available($actionclass)
            && $aimanager->is_action_enabled('aiplacement_reportbuilder', $actionclass)
            && (!$checkcontext || $aimanager->is_action_enabled_in_context($context, $actionclass));
    }

    /**
     * Check whether the placement itself is enabled.
     *
     * @return bool
     */
    public static function is_placement_available(): bool {
        [$plugintype, $pluginname] = explode(
            '_',
            \core_component::normalize_componentname('aiplacement_reportbuilder'),
            2,
        );
        $pluginmanager = \core_plugin_manager::resolve_plugininfo_class($plugintype);

        return $pluginmanager::is_plugin_enabled($pluginname);
    }
}
