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
 * Fixed Report Builder report of editable image occurrences.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use report_imagealt\reportbuilder\local\systemreports\image_alt_text;

require(__DIR__ . '/../../config.php');

$contextid = required_param('contextid', PARAM_INT);
$context = context::instance_by_id($contextid, MUST_EXIST);
if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE], true)) {
    throw new moodle_exception('invalidcontext');
}

require_login($context->contextlevel === CONTEXT_COURSE ? $context->instanceid : null);
require_capability('report/imagealt:view', $context);

$url = new moodle_url('/report/imagealt/index.php', ['contextid' => $context->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_imagealt'));
$PAGE->set_heading($context->get_context_name());
// Everything AI on this page is offered only where a provider can actually serve it. The rest of the report, and
// writing alternative text by hand, works exactly the same on a site with no AI at all.
$aiavailable = \report_imagealt\local\suggestion_service::is_available($context);
if ($aiavailable) {
    $PAGE->requires->js_call_amd('report_imagealt/bulk', 'init', [
        'imagealt-bulk-form',
        (int) $USER->id,
        \core_ai\manager::get_user_policy_status((int) $USER->id),
    ]);
}
$PAGE->requires->js_call_amd('report_imagealt/report', 'init');

// A course is bounded, so its report scans inline on access, the way the gradebook recomputes on access. Without this
// a course that no observed event ever marked dirty - a restored course being the ordinary case - reports itself as
// holding no images at all, which reads exactly like the truth and which the user is given no control to correct.
if ($context->contextlevel === CONTEXT_COURSE) {
    $coursescanmanager = new \report_imagealt\local\scan_manager();
    if ($coursescanmanager->course_needs_scan($context)) {
        $coursescanmanager->request($context);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportheading', 'report_imagealt'));
echo html_writer::tag('p', get_string(
    $aiavailable ? 'reportdescription' : 'reportdescriptionnoai',
    'report_imagealt',
), ['class' => 'lead']);

// A course's report has just brought itself up to date above, so a manual refresh there would be a button with
// nothing to do. The control only serves category and site scope, where scanning is a resumable background job that
// nothing on this page can run inline.
$showscancontrol = $context->contextlevel !== CONTEXT_COURSE;

if ($showscancontrol) {
    $scanmanager = new \report_imagealt\local\scan_manager();
    if ($scanjob = $scanmanager->get_active_job($context)) {
        // This is deliberately a lightweight persisted count, not a live COUNT(*) over site content on every report request.
        echo $OUTPUT->notification(
            get_string('scanprogress', 'report_imagealt', format_float($scanjob->queued, 0)),
            'info',
            false,
        );
    }
}

// Bulk generation runs in the background and its results live on a separate page, so without this the report gives
// no sign that the user has suggestions waiting, and no route back to them once they navigate away.
$outstanding = (new \report_imagealt\local\batch_manager())->get_outstanding_summary($context, (int) $USER->id);
if ($outstanding) {
    echo $OUTPUT->render(new \report_imagealt\output\suggestion_summary(
        $outstanding['ready'],
        $outstanding['generating'],
        $outstanding['batches'],
        $outstanding['latestbatchid'],
    ));
}

$report = system_report_factory::create(image_alt_text::class, $context, '', '', 0, ['contextid' => $context->id]);
echo $report->output();

if ($showscancontrol) {
    $scanbutton = new single_button(
        new moodle_url('/report/imagealt/scan.php', ['contextid' => $context->id, 'sesskey' => sesskey()]),
        get_string('checkfornewimages', 'report_imagealt'),
        'post',
        single_button::BUTTON_SECONDARY,
    );
    echo html_writer::div($OUTPUT->render($scanbutton), 'd-flex justify-content-end mb-3');
}

// Offered only where a provider can serve it. Without one the report above has no select-all column either (see the
// system report), so the page presents nothing that would fail the moment it was used.
if ($aiavailable) {
    $formurl = new moodle_url('/report/imagealt/bulk.php');
    echo html_writer::start_tag('form', [
        'id' => 'imagealt-bulk-form',
        'method' => 'post',
        'action' => $formurl->out(false),
        'class' => 'mt-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'contextid', 'value' => $context->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'occurrenceids', 'value' => '']);
    // Same CSS-mask sparkles icon as the per-row "Generate alt text with AI" button (report-imagealt-sparkles-icon,
    // see styles.css), so the two AI entry points on this report look consistent.
    $bulkicon = html_writer::span('', 'report-imagealt-sparkles-icon me-1', ['aria-hidden' => 'true']);
    // Nothing can be selected on first paint, so the button starts disabled and report_imagealt/bulk.js enables it as
    // soon as a row is ticked. Rendering it enabled and disabling it from JS instead would briefly show an actionable
    // button that has nothing to act on.
    echo html_writer::tag('button', $bulkicon . get_string('bulkgenerate', 'report_imagealt'), [
        'type' => 'submit',
        'name' => 'generate',
        'value' => '1',
        'class' => 'btn btn-outline-primary',
        'disabled' => 'disabled',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
