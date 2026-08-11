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

namespace report_imagealt\reportbuilder\systemreports;

use report_imagealt\ai_availability_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../ai_availability_test_trait.php');

use context_course;
use context_system;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\external\system_report_data_exporter;
use core_reportbuilder\local\filters\category as category_filter;
use core_reportbuilder\local\filters\course_selector;
use core_reportbuilder\system_report_factory;
use report_imagealt\local\manager;
use report_imagealt\reportbuilder\local\systemreports\image_alt_text;

/**
 * Tests for the fixed image alternative text system report.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(image_alt_text::class)]
final class image_alt_text_test extends \advanced_testcase {
    use ai_availability_test_trait;

    /**
     * The report applies context scope and exports data without interactive controls.
     */
    public function test_context_scope_and_export(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $courseone = $generator->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $generator->create_course(['summary' => '<img src="https://example.com/two.jpg" alt="Mountains">']);
        (new manager())->scan_context(context_system::instance());

        $coursereport = system_report_factory::create(
            image_alt_text::class,
            context_course::instance($courseone->id),
        );
        // This test is about which courses each report can see, so both reports below ask for every status. Left
        // alone they would arrive filtered to the images that need attention, which is covered separately.
        $this->show_all_statuses($coursereport);
        $courseexport = (new system_report_data_exporter(null, [
            'report' => $coursereport,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));

        $this->assertSame(1, $courseexport->totalrowcount);
        $this->assertCount(1, $courseexport->rows);
        $this->assertNotContains(get_string('select'), $courseexport->headers);
        $this->assertTrue($coursereport->is_downloadable());

        $sitereport = system_report_factory::create(image_alt_text::class, context_system::instance());
        $this->assertSame(
            course_selector::class,
            $sitereport->get_filter('occurrence:course')->get_filter_class(),
        );
        $this->assertSame(
            category_filter::class,
            $sitereport->get_filter('occurrence:category')->get_filter_class(),
        );
        $this->show_all_statuses($sitereport);
        $siteexport = (new system_report_data_exporter(null, [
            'report' => $sitereport,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));
        $this->assertSame(2, $siteexport->totalrowcount);
    }

    /**
     * The report opens showing the images that need attention rather than every image it holds, so the work is not
     * buried in the compliant majority on a site whose content is mostly fine.
     */
    public function test_the_report_opens_filtered_to_images_needing_attention(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="https://example.com/missing.jpg">'
                . '<img src="https://example.com/placeholder.jpg" alt="image">'
                . '<img src="https://example.com/fine.jpg" alt="A mountain summit under snow">'
                . '<img src="https://example.com/divider.jpg" alt="" role="presentation">',
        ]);
        (new manager())->scan_context(context_system::instance());

        $report = system_report_factory::create(image_alt_text::class, context_course::instance($course->id));
        $export = (new system_report_data_exporter(null, [
            'report' => $report,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));

        // The missing one and the placeholder one, not the well described one or the decorative one.
        $this->assertSame(2, $export->totalrowcount);

        // It is a filter rather than a restriction, so the rest are still reachable by asking for them.
        $this->show_all_statuses($report);
        $widened = (new system_report_data_exporter(null, [
            'report' => $report,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));
        $this->assertSame(4, $widened->totalrowcount);
    }

    /**
     * An empty table says which kind of empty it is. Because the report opens filtered, a course whose images are all
     * fine shows nothing, and Report Builder's default "Nothing to display" reads as a fault rather than as the good
     * outcome it actually is.
     */
    public function test_an_empty_table_explains_itself(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $compliant = $generator->create_course(['summary' => '<img src="https://example.com/a.jpg" alt="A snowy summit at dawn">']);
        $empty = $generator->create_course();
        (new manager())->scan_context(context_system::instance());

        // Images here, just none needing attention: the filter is what emptied the table, so it says so.
        $report = system_report_factory::create(image_alt_text::class, context_course::instance($compliant->id));
        $this->assertSame(
            get_string('nomatchingimages', 'report_imagealt'),
            (string) $report->get_default_no_results_notice(),
        );

        // Nothing indexed here at all, which is a different thing to tell the user.
        $report = system_report_factory::create(image_alt_text::class, context_course::instance($empty->id));
        $this->assertSame(
            get_string('noimages', 'report_imagealt'),
            (string) $report->get_default_no_results_notice(),
        );
    }

    /**
     * A filter choice of this user's own is never overridden, so somebody who has widened the report once does not
     * find it narrowed again on their next visit.
     */
    public function test_an_existing_filter_choice_is_left_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        (new manager())->scan_context(context_system::instance());
        $context = context_course::instance($course->id);

        $report = system_report_factory::create(image_alt_text::class, $context);
        $report->set_filter_values(['occurrence:status_values' => ['present']]);

        // Opening it again is what re-runs the default.
        $reopened = system_report_factory::create(image_alt_text::class, $context);

        $this->assertSame(
            ['occurrence:status_values' => ['present']],
            $reopened->get_filter_values(),
        );
    }

    /**
     * Ask a report for every alternative text status, undoing the default the report opens with.
     *
     * @param \core_reportbuilder\system_report $report The report to widen.
     */
    private function show_all_statuses(\core_reportbuilder\system_report $report): void {
        $report->set_filter_values(['occurrence:status_values' => [
            'missing', 'potentiallypoor', 'broken', 'decorative', 'present',
        ]]);
    }

    /**
     * Report access does not grant edit actions or visibility in another course.
     */
    public function test_view_and_edit_permissions_are_separate(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $courseone = $generator->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $coursetwo = $generator->create_course(['summary' => '<img src="https://example.com/two.jpg">']);
        $contextone = context_course::instance($courseone->id);
        $contexttwo = context_course::instance($coursetwo->id);
        (new manager())->scan_context(context_system::instance());

        $user = $generator->create_user();
        $roleid = create_role('Image report viewer', 'imagereportviewer', '');
        assign_capability('report/imagealt:view', CAP_ALLOW, $roleid, $contextone->id);
        role_assign($roleid, $user->id, $contextone->id);
        $this->setUser($user);

        $report = system_report_factory::create(image_alt_text::class, $contextone);
        $occurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['courseid' => $courseone->id],
            '*',
            MUST_EXIST,
        );
        $this->assertNull($report->get_checkbox_toggleall(false, $occurrence));
        // Read off the rendered row rather than by handing the column a record it did not build: a column maps a row
        // by its own generated SQL aliases, so a record passed in by hand comes back as nulls and an assertion that
        // the actions cell is empty holds however the callback behaves.
        $export = (new system_report_data_exporter(null, [
            'report' => $report,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));
        $this->assertStringNotContainsString(
            'report-imagealt-edit',
            implode("\n", $export->rows[0]['columns']),
        );

        $this->expectException(report_access_exception::class);
        system_report_factory::create(image_alt_text::class, $contexttwo);
    }

    /**
     * An image is only selectable for generation while there is nothing outstanding to generate for it.
     *
     * Offering it again would pay for a second description of an image whose description is already generating or
     * already waiting to be reviewed.
     */
    public function test_images_with_outstanding_suggestions_cannot_be_selected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $this->getDataGenerator()->get_plugin_generator('report_imagealt')
            ->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        // Selection is only offered where a provider can serve it, so one is reported before the report is built.
        $this->stub_ai_availability(true);
        $report = system_report_factory::create(image_alt_text::class, context_course::instance($course->id));

        // The row shape the report builds for this callback from its own base fields, so the suggestion state can
        // be varied without regenerating descriptions.
        $row = static fn(?string $suggestionstatus): \stdClass => (object) [
            'id' => $occurrence->id,
            'providerkey' => $occurrence->providerkey,
            'itemkey' => $occurrence->itemkey,
            'aieligible' => $occurrence->aieligible,
            'analysisstate' => $occurrence->analysisstate,
            'status' => $occurrence->status,
            'filename' => $occurrence->filename,
            'src' => $occurrence->src,
            'latestsuggestionstatus' => $suggestionstatus,
        ];

        $this->assertNotNull($report->get_checkbox_toggleall(false, $row(null)));
        foreach (['queued', 'processing', 'ready'] as $outstanding) {
            $this->assertNull($report->get_checkbox_toggleall(false, $row($outstanding)), $outstanding);
        }
        // Anything the user has already dealt with, or that failed and can be retried, leaves the image selectable.
        foreach (['accepted', 'discarded', 'cancelled', 'stale', 'failed'] as $resolved) {
            $this->assertNotNull($report->get_checkbox_toggleall(false, $row($resolved)), $resolved);
        }
    }

    /**
     * A decorative image is meant to have no alternative text, so it is not offered for generation: applying a
     * description to it would undo the decision to hide it from screen readers.
     */
    public function test_decorative_images_cannot_be_selected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $decorative = $generator->create_image([
            'courseid' => $course->id,
            'filename' => 'divider.png',
            'decorative' => true,
        ]);
        $describable = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $this->stub_ai_availability(true);
        $report = system_report_factory::create(image_alt_text::class, context_course::instance($course->id));

        $this->assertNull($report->get_checkbox_toggleall(false, $decorative));
        // An image whose alternative text is merely already good stays selectable: rewriting that is a reasonable
        // thing to ask for, unlike describing an image that must stay undescribed.
        $this->assertNotNull($report->get_checkbox_toggleall(false, $describable));
    }

    /**
     * A broken image is in the worklist from the first view, offers no way to describe something that is not there,
     * and points at the content instead so the missing file can be dealt with.
     */
    public function test_a_broken_image_is_reported_but_not_describable(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="@@PLUGINFILE@@/gone.png">',
            'summaryformat' => FORMAT_HTML,
        ]);
        (new manager())->scan_context(context_system::instance());
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->stub_ai_availability(true);

        $report = system_report_factory::create(image_alt_text::class, context_course::instance($course->id));

        // Broken is work, and the most urgent kind, so the view the report opens with has to include it.
        $export = (new system_report_data_exporter(null, [
            'report' => $report,
            'page' => 0,
            'perpage' => 20,
        ]))->export($PAGE->get_renderer('core_reportbuilder'));
        $this->assertSame(1, $export->totalrowcount);

        // Nothing to describe, so nothing is offered to describe it with.
        $this->assertNull($report->get_checkbox_toggleall(false, $occurrence));

        // Asserted against the row the report actually renders rather than by calling the column callbacks with a
        // raw record: a column maps a row by its own generated SQL aliases, so a record passed in by hand comes back
        // as nulls and every assertion on it passes for the wrong reason.
        $rendered = implode("\n", $export->rows[0]['columns']);

        // The preview says the image is missing rather than leaving a blank where a picture would be.
        $this->assertStringContainsString(get_string('brokenimage', 'report_imagealt'), $rendered);
        // The one useful action: a way to the content, which is where the fault actually is.
        $this->assertStringContainsString(get_string('brokenimagefix', 'report_imagealt'), $rendered);
        $this->assertStringNotContainsString('report-imagealt-edit', $rendered);
        // And it is reported as broken rather than as alternative text somebody forgot to write.
        $this->assertStringContainsString(get_string('status_broken', 'report_imagealt'), $rendered);
    }

    /**
     * Each checkbox is named after the image it selects. Labelled "Select" like the rest of Report Builder's rows,
     * a screen reader user got a column of identically named checkboxes with no way to tell them apart.
     */
    public function test_selection_checkboxes_name_their_image(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $occurrence = $this->getDataGenerator()->get_plugin_generator('report_imagealt')->create_image([
            'courseid' => $this->getDataGenerator()->create_course()->id,
            'filename' => 'summit.png',
        ]);
        $this->stub_ai_availability(true);
        $report = system_report_factory::create(
            image_alt_text::class,
            context_course::instance((int) $occurrence->courseid),
        );

        $checkbox = $report->get_checkbox_toggleall(false, $occurrence);

        $exported = $checkbox->export_for_template($this->createMock(\renderer_base::class));
        $this->assertStringContainsString('summit.png', $exported->label);
    }

    /**
     * Selection exists only to send images for AI description, so a site with no provider is not offered any: not
     * per row, and not the select-all header either.
     */
    public function test_images_cannot_be_selected_without_a_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->get_plugin_generator('report_imagealt')
            ->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $this->stub_ai_availability(false);

        $report = system_report_factory::create(image_alt_text::class, context_course::instance($course->id));

        // Report Builder renders its select-all header for any report that registered the callback, whatever each row
        // then answers, so leaving the callback unregistered is what keeps the column off the table entirely. Asking
        // for the master checkbox is how that shows: it is returned for any report that has one.
        $this->assertNull($report->get_checkbox_toggleall(true));
        // The report itself is unaffected: it still lists the image so it can be remediated by hand.
        $this->assertNotEmpty($report->get_active_columns());
    }
}
