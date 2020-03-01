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

/**
 * This script is used to configure and execute the course copy proccess.
 *
 * @package    core_backup
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $USER;
require_once('../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

defined('MOODLE_INTERNAL') || die();

$courseid = required_param('id', PARAM_INT);

$url = new moodle_url('/backup/copyprogress.php', array('id'=>$courseid));
$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

require_login($course, false);

// Setup the page.
$title = get_string('copyprogresstitle', 'backup');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->requires->js_call_amd('core_backup/async_backup', 'asyncCopyAllStatus');

// Build the page output.
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('copyprogressheading', 'backup'), 'copyprogressheading', 'backup');
echo $OUTPUT->container_start();
$renderer = $PAGE->get_renderer('core', 'backup');
echo $renderer->copy_progress_viewer($USER->id, $courseid);
echo $OUTPUT->container_end();

echo $OUTPUT->footer();
