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
 * Serve the image behind one indexed occurrence.
 *
 * The report indexes images belonging to many components, and a pluginfile URL assembled from a file's own fields
 * is not valid for all of them: mod_page, for one, carries a revision in the URL path that is not the file's item
 * ID, and rejects a URL without it. Resolving the file through its content provider is the one path that works for
 * every provider, including any registered by another plugin, so the report serves previews itself rather than
 * linking to addresses it cannot reliably construct.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
// Present only so a replaced image gets a new address; the current file is always resolved from the occurrence.
$hash = optional_param('hash', '', PARAM_ALPHANUM);

$occurrence = $DB->get_record('report_imagealt_occurrence', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($occurrence->contextid, MUST_EXIST);
require_login($occurrence->courseid ?: null, false);
require_capability('report/imagealt:view', $context);

// Resolution also confirms the user may edit the content this image sits in, so the report never serves file
// content to somebody who could not already open that content itself.
$file = (new \report_imagealt\local\manager())->resolve_preview_file($id, (int) $USER->id);
if (!$file) {
    send_file_not_found();
}

// Cacheable for a day, because the address carries the image's content hash and so changes when the image does.
send_stored_file($file, DAYSECS, 0, false);
