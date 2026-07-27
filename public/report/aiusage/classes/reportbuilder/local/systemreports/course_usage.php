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

namespace report_aiusage\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use core_ai\reportbuilder\local\entities\ai_action_register;
use core\reportbuilder\local\entities\context;
use core_reportbuilder\local\entities\user;

/**
 * Course-level AI usage system report.
 *
 * Reuses the core_ai reportbuilder entities used by the sitewide admin report
 * ({@see \core_ai\reportbuilder\local\systemreports\usage}), restricted to a single course, and
 * further restricted to a single user's own actions when the caller lacks the "view all" capability.
 *
 * @package    report_aiusage
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_usage extends system_report {
    #[\Override]
    protected function initialise(): void {
        $entitymain = new ai_action_register();
        $entitymainalias = $entitymain->get_table_alias('ai_action_register');

        $this->set_main_table('ai_action_register', $entitymainalias);
        $this->add_entity($entitymain);

        // Restrict to the course this report was created for.
        $this->add_base_condition_simple(
            "{$entitymainalias}.courseid",
            $this->get_parameter('courseid', 0, PARAM_INT),
        );

        // Restrict to a single user's own actions when the caller cannot view the whole course.
        $restricttouserid = $this->get_parameter('restricttouserid', 0, PARAM_INT);
        if ($restricttouserid > 0) {
            $this->add_base_condition_simple("{$entitymainalias}.userid", $restricttouserid);
        }

        // Join the 'user' entity to our main entity.
        $entityuser = new user();
        $entituseralias = $entityuser->get_table_alias('user');
        $this->add_entity($entityuser->add_join(
            "LEFT JOIN {user} {$entituseralias} ON {$entituseralias}.id = {$entitymainalias}.userid",
        ));

        // Join the 'context' entity to our main entity.
        $entitycontext = new context();
        $entitycontextalias = $entitycontext->get_table_alias('context');
        $this->add_entity($entitycontext->add_join(
            "LEFT JOIN {context} {$entitycontextalias} ON {$entitycontextalias}.id = {$entitymainalias}.contextid",
        ));

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(true, get_string('pluginname', 'report_aiusage'));
    }

    #[\Override]
    protected function can_view(): bool {
        $context = $this->get_context();

        return has_capability('report/aiusage:view', $context) || has_capability('report/aiusage:viewown', $context);
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('pluginname', 'report_aiusage');
    }

    /**
     * Adds the columns we want to display in the report.
     */
    public function add_columns(): void {
        $this->add_columns_from_entities([
            'ai_action_register:provider',
            'ai_action_register:actionname',
            'ai_action_register:timecreated',
            'ai_action_register:prompttokens',
            'ai_action_register:completiontokens',
            'ai_action_register:success',
        ]);

        // Link the context column to the closest context (e.g. the course activity the action relates to)
        // rather than just showing its name as plain text.
        $this->add_column_from_entity('context:link')
            ->set_title(new \lang_string('contextname'));

        $this->add_column_from_entity('user:fullnamewithlink');

        // Link to the full detail of the action (full prompt, full generated response, etc), shown last.
        $this->add_column_from_entity('ai_action_register:detail');

        $this->set_initial_sort_column('ai_action_register:timecreated', SORT_DESC);
    }

    /**
     * Adds the filters we want to display in the report.
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'ai_action_register:actionname',
            'ai_action_register:provider',
            'ai_action_register:timecreated',
            'ai_action_register:prompttokens',
            'ai_action_register:completiontokens',
            'ai_action_register:success',
            'context:level',
            'user:fullname',
        ]);
    }
}
