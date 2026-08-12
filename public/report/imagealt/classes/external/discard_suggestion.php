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

/**
 * External API to discard an unpublished alternative text suggestion.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class discard_suggestion extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'suggestionid' => new external_value(PARAM_INT, 'The suggestion record ID'),
        ]);
    }

    /**
     * Discard one of the current user's own suggestions.
     *
     * @param int $suggestionid The suggestion record ID.
     * @return array
     */
    public static function execute(int $suggestionid): array {
        global $DB, $USER;

        ['suggestionid' => $suggestionid] = self::validate_parameters(self::execute_parameters(), [
            'suggestionid' => $suggestionid,
        ]);

        require_sesskey();

        // Only enough is read here to resolve the context the suggestion belongs to. Anything that decides whether
        // this user may act on it, including the ownership check below, happens after the session and context have
        // been validated and the capability confirmed.
        $suggestion = $DB->get_record('report_imagealt_suggestion', ['id' => $suggestionid], '*', MUST_EXIST);
        $occurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['id' => $suggestion->occurrenceid],
            '*',
            MUST_EXIST,
        );
        $context = \context::instance_by_id($occurrence->contextid);
        self::validate_context($context);
        require_capability('report/imagealt:view', $context);

        if ((int) $suggestion->userid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        $DB->set_field('report_imagealt_suggestion', 'status', 'discarded', ['id' => $suggestionid]);

        return ['success' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the suggestion was discarded'),
        ]);
    }
}
