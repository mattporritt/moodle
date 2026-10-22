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

namespace mod_lti;

/**
 * Upgrade-specific helper handling data migration from mod_lti into core_ltix.
 *
 * @package    mod_lti
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_migration_upgrade_helper {

    /**
     * Creates a resource link for every lti activity instance. Links are owned by mod_lti and are identified by the placementtype.
     *
     * @return void
     */
    public function create_resource_links(): void {
        global $DB;
        // All lti records need to have a corresponding lti_resource_link record created for them.
        // Note: existing lti links can have typeid = null or typeid = 0, depending on the circumstance:
        // a) If the link is a legacy, manually-configured instance, typeid = 0.
        // b) if the link has been restored, cross-site, where the tool was not restored (site tools aren't), typeid = null.
        // All links created here will map BOTH nulls and 0s to 0, denoting a link that isn't directly associated with a tool.
        $sql = <<<EOF
            INSERT INTO {lti_resource_link} (id, typeid, component, itemtype, itemid, contextid, url, title, text,
                        textformat, gradable, launchcontainer, customparams, icon, servicesalt)
                 SELECT lti.id,
                    (CASE WHEN lti.typeid IS NULL THEN 0 ELSE lti.typeid END) AS typeid,
                    :component, :itemtype, cm.id, ctx.id, lti.toolurl, lti.name, lti.intro,
                    lti.introformat, :gradable, lti.launchcontainer, lti.instructorcustomparameters, lti.icon,
                    lti.servicesalt
                   FROM {lti} lti
                   JOIN {course_modules} cm ON (cm.instance = lti.id)
                   JOIN {modules} m ON (m.id = cm.module)
                   JOIN {context} ctx ON (ctx.instanceid = cm.id)
                  WHERE m.name = :ltimodulename
                    AND ctx.contextlevel = :contextlevel
                    AND NOT EXISTS (
                        SELECT 1
                          FROM {lti_resource_link} rl
                         WHERE rl.id = lti.id
                    )
        EOF;
        $DB->execute($sql, [
            'component' => 'mod_lti',
            'itemtype' => 'mod_lti:activityplacement', // The placement type. See mod/lti/db/lti.php where this is defined.
            'gradable' => 1, // All links owned by mod_lti are deemed gradable since they are used in an activity placement.
            'ltimodulename' => 'lti',
            'contextlevel' => CONTEXT_MODULE,
        ]);

        // Reset table sequence for the id column.
        $table = new \xmldb_table('lti_resource_link');
        $DB->get_manager()->reset_sequence($table);

    }
}
