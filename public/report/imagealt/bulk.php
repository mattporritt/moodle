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
 * Queue selected occurrences for AI suggestion generation.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$contextid = required_param('contextid', PARAM_INT);
$occurrenceids = required_param('occurrenceids', PARAM_SEQUENCE);
$context = context::instance_by_id($contextid, MUST_EXIST);
require_login($context->contextlevel === CONTEXT_COURSE ? $context->instanceid : null);
require_capability('report/imagealt:view', $context);
require_sesskey();

// Set before anything else runs, even though this endpoint only ever redirects. Deciding whether AI can serve the
// request reads provider settings, which core initialises through a form, and a form built with no page context
// raises a developer warning. The redirect on failure renders through the theme as well.
$PAGE->set_url('/report/imagealt/bulk.php');
$PAGE->set_context($context);

$ids = $occurrenceids === '' ? [] : array_map('intval', explode(',', $occurrenceids));
$batch = (new \report_imagealt\local\batch_manager())->create($context, $ids, (int) $USER->id);
// Deliberately no confirmation message: the batch page states the same thing as live, persistent status, and a
// dismissible notification repeating it on top of that reads as a second, competing status message.
redirect(new moodle_url('/report/imagealt/batch.php', ['id' => $batch->id]));
