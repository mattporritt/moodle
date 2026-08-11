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

/**
 * Queue image occurrence analysis.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$contextid = required_param('contextid', PARAM_INT);
$context = context::instance_by_id($contextid, MUST_EXIST);
require_login($context->contextlevel === CONTEXT_COURSE ? $context->instanceid : null);
require_capability('report/imagealt:view', $context);
require_sesskey();

// A single course is scanned inline for immediate results. Larger scopes only create a small persistent discovery cursor
// here so the request stays fast; the process_queue scheduled task walks it and scans the dirty targets it records.
$job = (new \report_imagealt\local\scan_manager())->request($context);

// A course scan already ran synchronously above, so the table below already reflects it. Category and site scopes only
// start a background discovery cursor, so say that explicitly rather than implying the table is already up to date.
$message = $job === null
    ? get_string('refreshcomplete', 'report_imagealt')
    : get_string('refreshqueued', 'report_imagealt');

redirect(
    new moodle_url('/report/imagealt/index.php', ['contextid' => $context->id]),
    $message,
);
