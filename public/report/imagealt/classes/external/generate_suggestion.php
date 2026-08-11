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

namespace report_imagealt\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use report_imagealt\local\manager;
use report_imagealt\local\suggestion_service;

/**
 * External API to request an AI-generated alternative text suggestion for one occurrence.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generate_suggestion extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'occurrenceid' => new external_value(PARAM_INT, 'The image occurrence ID'),
        ]);
    }

    /**
     * Request a suggestion for one occurrence.
     *
     * @param int $occurrenceid The image occurrence ID.
     * @return array
     */
    public static function execute(int $occurrenceid): array {
        global $DB, $USER;

        ['occurrenceid' => $occurrenceid] = self::validate_parameters(self::execute_parameters(), [
            'occurrenceid' => $occurrenceid,
        ]);

        require_sesskey();
        $manager = new manager();
        [$occurrence, $provider, $item] = $manager->get_current_occurrence($occurrenceid);
        $context = \context::instance_by_id($item->contextid);
        self::validate_context($context);
        require_capability('report/imagealt:view', $context);
        if (!$provider->can_edit($item, (int) $USER->id)) {
            throw new \moodle_exception('cannotedit', 'report_imagealt');
        }
        // Refused before a record exists, rather than left for generation to fail on. Otherwise a site with no
        // provider accumulates one failed suggestion per attempt, each becoming the image's latest suggestion and
        // reporting itself as a failure in the report.
        if (!suggestion_service::is_available($context)) {
            throw new \moodle_exception('error:aiunavailable', 'report_imagealt');
        }

        $now = time();
        $suggestionid = $DB->insert_record('report_imagealt_suggestion', (object) [
            'occurrenceid' => $occurrence->id,
            'batchid' => null,
            'userid' => $USER->id,
            'status' => 'queued',
            'originalhash' => $occurrence->contenthash,
            'suggestion' => null,
            'errormessage' => null,
            'attempts' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $suggestion = (new suggestion_service())->generate($suggestionid);

        return [
            'suggestionid' => (int) $suggestion->id,
            'status' => $suggestion->status,
            'suggestiontext' => (string) ($suggestion->suggestion ?? ''),
            'errormessage' => (string) ($suggestion->errormessage ?? ''),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'suggestionid' => new external_value(PARAM_INT, 'The suggestion record ID'),
            'status' => new external_value(PARAM_ALPHA, 'The suggestion status'),
            'suggestiontext' => new external_value(PARAM_RAW, 'The generated alternative text, if ready'),
            'errormessage' => new external_value(PARAM_TEXT, 'An error message, if the suggestion failed'),
        ]);
    }
}
