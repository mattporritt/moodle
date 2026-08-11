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

namespace report_imagealt\form;

use report_imagealt\ai_availability_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../ai_availability_test_trait.php');

use context_course;
use context_system;
use report_imagealt\local\manager;

/**
 * Tests for the occurrence review dynamic form, focused on the context levels most likely to regress: a
 * course-module occurrence (which needs a real course module context) and a user-profile occurrence (which has no
 * owning course at all).
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(review::class)]
final class review_test extends \advanced_testcase {
    use ai_availability_test_trait;

    /**
     * Saving a course-module occurrence writes the alternative text back into the activity's own content.
     */
    public function test_course_module_occurrence_lifecycle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'intro' => '<img src="https://example.com/intro.jpg">',
            'introformat' => FORMAT_HTML,
        ]);
        (new manager())->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);

        $submitdata = review::mock_ajax_submit([
            'id' => $occurrence->id,
            'contextid' => context_course::instance($course->id)->id,
            'alttext' => 'A page introduction image',
            'decorative' => 0,
        ]);
        $form = new review(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $this->assertStringContainsString(
            'alt="A page introduction image"',
            $DB->get_field('page', 'intro', ['id' => $page->id]),
        );
    }

    /**
     * Saving a user-profile occurrence (no owning course) writes back into the user's own profile description.
     */
    public function test_user_profile_occurrence_lifecycle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="https://example.com/portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        (new manager())->scan_user($user->id);
        $occurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['providerkey' => 'core_user', 'itemkeyhash' => hash('sha256', "user:{$user->id}")],
            '*',
            MUST_EXIST,
        );

        $submitdata = review::mock_ajax_submit([
            'id' => $occurrence->id,
            'contextid' => context_system::instance()->id,
            'alttext' => 'Portrait photo',
            'decorative' => 0,
        ]);
        $form = new review(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $this->assertStringContainsString(
            'alt="Portrait photo"',
            $DB->get_field('user', 'description', ['id' => $user->id]),
        );
    }

    /**
     * A user without edit access to the underlying content is rejected before the form is even built.
     */
    public function test_construction_rejects_user_without_edit_access(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        (new manager())->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $submitdata = review::mock_ajax_submit([
            'id' => $occurrence->id,
            'contextid' => context_course::instance($course->id)->id,
            'alttext' => 'Attempted edit',
            'decorative' => 0,
        ]);

        $this->expectException(\moodle_exception::class);
        new review(null, null, 'post', '', null, true, $submitdata, true);
    }

    /**
     * A return context outside the occurrence's own scope is rejected.
     */
    public function test_construction_rejects_out_of_scope_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $othercourse = $this->getDataGenerator()->create_course();
        (new manager())->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);

        $submitdata = review::mock_ajax_submit([
            'id' => $occurrence->id,
            'contextid' => context_course::instance($othercourse->id)->id,
            'alttext' => 'Attempted edit',
            'decorative' => 0,
        ]);

        $this->expectException(\moodle_exception::class);
        new review(null, null, 'post', '', null, true, $submitdata, true);
    }

    /**
     * Saving settles the description rather than leaving it waiting to be reviewed.
     *
     * Whichever way it goes, the description must not still read as outstanding afterwards: the report would report
     * work that no longer exists, and for a description written in this dialog there is no way back to it.
     *
     * @param bool $keeptext Whether the reviewer saved the description as written.
     * @param string $expected The status the description should be settled to.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('save_outcome_provider')]
    public function test_saving_settles_the_description(bool $keeptext, string $expected): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $description = 'A snow covered mountain summit.';
        $suggestion = $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => $description,
        ]);

        $submitdata = review::mock_ajax_submit([
            'id' => $occurrence->id,
            'contextid' => context_course::instance((int) $course->id)->id,
            'alttext' => $keeptext ? $description : 'Words the reviewer wrote instead.',
            'decorative' => 0,
        ]);
        $form = new review(null, null, 'post', '', null, true, $submitdata, true);
        $form->process_dynamic_submission();

        $this->assertSame(
            $expected,
            $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $suggestion->id]),
        );
    }

    /**
     * How saving settles a description, by whether the reviewer kept it.
     *
     * @return array<string, array>
     */
    public static function save_outcome_provider(): array {
        return [
            // Saved as written, so it is now the image's alternative text.
            'kept as written' => [true, 'accepted'],
            // The reviewer wrote their own, so this one never reached the image and is not waiting for anything.
            'replaced by the reviewer' => [false, 'discarded'],
        ];
    }

    /**
     * The character count describes the text the field actually opens with.
     *
     * A suggestion waiting to be reviewed pre-fills the field, so counting the image's current alternative text
     * instead left the two contradicting each other until the user typed.
     */
    public function test_character_count_matches_the_prefilled_suggestion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image([
            'courseid' => $course->id,
            'filename' => 'summit.png',
            'alt' => 'Short alt',
        ]);
        $suggestion = 'A considerably longer AI written description of the very same mountain summit image.';
        $generator->create_suggestion(['occurrenceid' => $occurrence->id, 'suggestion' => $suggestion]);

        $form = new review(null, null, 'post', '', null, true, [
            'id' => $occurrence->id,
            'contextid' => context_course::instance((int) $course->id)->id,
        ], true);
        $html = $form->render();

        $this->assertStringContainsString(
            '<span data-region="report-imagealt-currentcount">' . strlen($suggestion) . '</span>',
            $html,
        );
        // The image's own alternative text is not what the field opened with, so it is not what was counted.
        $this->assertStringNotContainsString(
            '<span data-region="report-imagealt-currentcount">' . strlen('Short alt') . '</span>',
            $html,
        );
    }

    /**
     * With no provider for image descriptions the form is still the way alternative text is written, and offers
     * nothing that would fail if it were used.
     */
    public function test_no_ai_is_offered_without_a_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        // AI eligible, so anything withheld below is withheld for want of a provider, not because of the image.
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $this->stub_unavailable_ai();

        $html = $this->render_review($occurrence, (int) $course->id);

        $this->assertStringNotContainsString('report-imagealt-generate', $html);
        $this->assertStringNotContainsString(get_string('generatealttext', 'report_imagealt'), $html);
        // The field itself, and the way to save it, are untouched.
        $this->assertStringContainsString('name="alttext"', $html);
    }

    /**
     * A description written before the provider was switched off can still be applied: the text already exists and
     * saving it asks nothing of a provider. Only writing another one is withheld.
     */
    public function test_a_description_already_written_survives_losing_the_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = 'A mountain summit under snow.';
        $generator->create_suggestion(['occurrenceid' => $occurrence->id, 'suggestion' => $suggestion]);
        $this->stub_unavailable_ai();

        $html = $this->render_review($occurrence, (int) $course->id);

        // Still offered for review, and still pre-filled into the field, so it is not lost.
        $this->assertStringContainsString(get_string('suggestionready', 'report_imagealt'), $html);
        $this->assertStringContainsString($suggestion, $html);
        // Asking for another one is not offered, because it could not be answered.
        $this->assertStringNotContainsString(get_string('regenerate', 'report_imagealt'), $html);
        // Rejecting it needs no provider, so that stays.
        $this->assertStringContainsString('report-imagealt-discard', $html);
    }

    /**
     * The suggestion region carries whether a provider can serve a request, because every state the client renders
     * into it afterwards has to make the same decision the server made here. Without it, discarding a description on
     * a site with no provider re-renders the region's idle state, which offers to write another one.
     */
    public function test_the_suggestion_region_reports_a_provider_it_can_use(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $aimanager = $this->stub_ai_availability(true);

        $html = $this->render_review($occurrence, (int) $course->id);

        $this->assertStringContainsString('data-canregenerate="1"', $html);
    }

    /**
     * The same region on a site with no provider, so the client withholds the states that need one.
     */
    public function test_the_suggestion_region_reports_no_provider_to_use(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $this->stub_unavailable_ai();

        $html = $this->render_review($occurrence, (int) $course->id);

        $this->assertStringContainsString('data-canregenerate="0"', $html);
    }

    /**
     * Report no provider able to write image descriptions.
     */
    private function stub_unavailable_ai(): void {
        $this->stub_ai_availability(false);
    }

    /**
     * Render the review form for one occurrence.
     *
     * @param \stdClass $occurrence The occurrence to review.
     * @param int $courseid The course the report was opened from.
     * @return string The rendered form.
     */
    private function render_review(\stdClass $occurrence, int $courseid): string {
        return (new review(null, null, 'post', '', null, true, [
            'id' => $occurrence->id,
            'contextid' => context_course::instance($courseid)->id,
        ], true))->render();
    }
}
