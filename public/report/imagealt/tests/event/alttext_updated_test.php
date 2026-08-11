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

namespace report_imagealt\event;

use report_imagealt\local\batch_manager;
use report_imagealt\local\manager;

/**
 * Tests for the alternative text updated event.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(alttext_updated::class)]
final class alttext_updated_test extends \advanced_testcase {
    /**
     * Writing alternative text a person entered is logged as their own work.
     */
    public function test_a_manual_update_is_logged_as_manual(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);

        $sink = $this->redirectEvents();
        (new manager())->update_occurrence(
            (int) $occurrence->id,
            'A snow covered mountain summit.',
            false,
            (int) get_admin()->id,
        );
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn(\core\event\base $event): bool => $event instanceof alttext_updated,
        ));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals($occurrence->id, $event->objectid);
        $this->assertEquals(\context_course::instance((int) $course->id)->id, $event->contextid);
        $this->assertSame(alttext_updated::SOURCE_MANUAL, $event->other['source']);
        $this->assertNull($event->other['suggestionid']);
        $this->assertFalse($event->other['decorative']);
        $this->assertStringContainsString('updated the alternative text', $event->get_description());
    }

    /**
     * Accepting an AI suggestion records that the text was machine generated, and which suggestion it came from.
     *
     * This is the reason the event exists: once the description is saved into the content, nothing else
     * distinguishes it from alternative text a person wrote.
     */
    public function test_accepting_a_suggestion_is_logged_with_its_source(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => 'A snow covered mountain summit.',
        ]);

        $sink = $this->redirectEvents();
        (new batch_manager())->accept(
            (int) $suggestion->batchid,
            [(int) $suggestion->id],
            (int) get_admin()->id,
        );
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn(\core\event\base $event): bool => $event instanceof alttext_updated,
        ));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(alttext_updated::SOURCE_ACCEPTED, $event->other['source']);
        $this->assertEquals($suggestion->id, $event->other['suggestionid']);
        $this->assertStringContainsString('applied the AI generated alternative text', $event->get_description());
    }

    /**
     * Marking an image decorative is a distinct accessibility decision, so it is recorded as one.
     */
    public function test_marking_an_image_decorative_is_recorded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'border.png']);

        $sink = $this->redirectEvents();
        (new manager())->update_occurrence((int) $occurrence->id, '', true, (int) get_admin()->id);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn(\core\event\base $event): bool => $event instanceof alttext_updated,
        ));

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->other['decorative']);
    }

    /**
     * Saving a suggestion unedited through the review form is logged as machine generated, not as the user's text.
     *
     * The single image path has to record provenance as accurately as the bulk one, or the log claims a person
     * wrote whatever they generated and saved without changing.
     */
    public function test_saving_an_unedited_suggestion_from_the_form_is_logged_as_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => 'A snow covered mountain summit.',
        ]);

        $events = $this->save_through_the_review_form(
            (int) $occurrence->id,
            (int) $course->id,
            'A snow covered mountain summit.',
        );

        $this->assertCount(1, $events);
        $this->assertSame(alttext_updated::SOURCE_ACCEPTED, $events[0]->other['source']);
        $this->assertEquals($suggestion->id, $events[0]->other['suggestionid']);
    }

    /**
     * Editing a suggestion before saving it makes the text the user's own, and it is logged as theirs.
     */
    public function test_saving_an_edited_suggestion_from_the_form_is_logged_as_manual(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => 'A snow covered mountain summit.',
        ]);

        $events = $this->save_through_the_review_form(
            (int) $occurrence->id,
            (int) $course->id,
            'Ben Nevis under snow.',
        );

        $this->assertCount(1, $events);
        $this->assertSame(alttext_updated::SOURCE_MANUAL, $events[0]->other['source']);
        $this->assertNull($events[0]->other['suggestionid']);
    }

    /**
     * Submit the review form for one occurrence and return the alternative text events it triggered.
     *
     * @param int $occurrenceid Occurrence being edited.
     * @param int $courseid Course the occurrence lives in.
     * @param string $alttext Alternative text to submit.
     * @return alttext_updated[]
     */
    private function save_through_the_review_form(int $occurrenceid, int $courseid, string $alttext): array {
        $submitdata = \report_imagealt\form\review::mock_ajax_submit([
            'id' => $occurrenceid,
            'contextid' => \context_course::instance($courseid)->id,
            'alttext' => $alttext,
            'decorative' => 0,
        ]);
        $form = new \report_imagealt\form\review(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());

        $sink = $this->redirectEvents();
        $form->process_dynamic_submission();

        return array_values(array_filter(
            $sink->get_events(),
            static fn(\core\event\base $event): bool => $event instanceof alttext_updated,
        ));
    }

    /**
     * A suggestion source without the suggestion it came from is not a usable record, so it is refused.
     */
    public function test_an_accepted_source_requires_the_suggestion(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        alttext_updated::create([
            'objectid' => 1,
            'contextid' => \context_system::instance()->id,
            'other' => ['source' => alttext_updated::SOURCE_ACCEPTED, 'suggestionid' => null, 'decorative' => false],
        ])->trigger();
    }
}
