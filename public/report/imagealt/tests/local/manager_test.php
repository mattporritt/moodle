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

namespace report_imagealt\local;

use context_course;
use context_coursecat;
use context_system;
use context_user;
use report_imagealt\observer;

/**
 * Tests for image occurrence indexing, scoping, and stale protection.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class manager_test extends \advanced_testcase {
    /** One-pixel PNG image. */
    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * The same stored file embedded twice is indexed as two occurrences.
     */
    public function test_scan_course_indexes_occurrences_not_files(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'summary',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lake.png',
        ], base64_decode(self::IMAGE));
        $DB->set_field(
            'course',
            'summary',
            '<img src="@@PLUGINFILE@@/lake.png"><img src="@@PLUGINFILE@@/lake.png" alt="Lake">',
            ['id' => $course->id]
        );

        (new manager())->scan_context($context);

        $records = array_values($DB->get_records('report_imagealt_occurrence', ['courseid' => $course->id], 'position'));
        $this->assertCount(2, $records);
        $this->assertNotSame($records[0]->occurrencekey, $records[1]->occurrencekey);
        $this->assertSame('missing', $records[0]->status);
        $this->assertSame('present', $records[1]->status);
        $this->assertEquals(1, $records[0]->aieligible);
        // The resolved file's own content hash, which is what the preview endpoint's address is built from.
        $this->assertSame(
            get_file_storage()->get_file($context->id, 'course', 'summary', 0, '/', 'lake.png')->get_contenthash(),
            $records[0]->previewhash,
        );
    }

    /**
     * Category scope contains descendant courses and excludes unrelated categories.
     */
    public function test_scan_category_scope(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);
        $unrelated = $generator->create_category();
        $generator->create_course(['category' => $parent->id, 'summary' => '<img src="https://example.com/one.jpg">']);
        $generator->create_course(['category' => $child->id, 'summary' => '<img src="https://example.com/two.jpg">']);
        $generator->create_course(['category' => $unrelated->id, 'summary' => '<img src="three.jpg">']);

        (new manager())->scan_context(context_coursecat::instance($parent->id));

        $this->assertEquals(2, $DB->count_records('report_imagealt_occurrence'));
        $this->assertFalse($DB->record_exists('report_imagealt_occurrence', ['categoryid' => $unrelated->id]));
    }

    /**
     * Activity introduction and explicit Page body adapters produce separate editable occurrences.
     */
    public function test_scan_activity_intro_and_page_content(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'intro' => '<img src="https://example.com/intro.jpg">',
            'introformat' => FORMAT_HTML,
            'content' => '<img src="https://example.com/content.jpg">',
            'contentformat' => FORMAT_HTML,
        ]);
        $manager = new manager();
        $manager->scan_context(context_course::instance($course->id));

        $records = $DB->get_records('report_imagealt_occurrence', ['courseid' => $course->id]);
        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(['content', 'intro'], array_column($records, 'fieldname'));

        $contentoccurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['courseid' => $course->id, 'fieldname' => 'content'],
            '*',
            MUST_EXIST,
        );
        $manager->update_occurrence($contentoccurrence->id, 'Page illustration', false, (int) $USER->id);
        $this->assertStringContainsString(
            'alt="Page illustration"',
            $DB->get_field('page', 'content', ['id' => $page->id]),
        );
    }

    /**
     * Manual remediation updates only the approved occurrence.
     */
    public function test_update_occurrence(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<p><img src="https://example.com/one.jpg"><img src="https://example.com/two.jpg" alt="Keep me"></p>',
        ]);
        $context = context_course::instance($course->id);
        $manager = new manager();
        $manager->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id, 'position' => 0], '*', MUST_EXIST);

        $manager->update_occurrence($occurrence->id, 'First image', false, (int) $USER->id);

        $summary = $DB->get_field('course', 'summary', ['id' => $course->id]);
        $this->assertStringContainsString('<img src="https://example.com/one.jpg" alt="First image">', $summary);
        $this->assertStringContainsString('<img src="https://example.com/two.jpg" alt="Keep me">', $summary);
    }

    /**
     * Newer source content cannot be overwritten by a stale report row.
     */
    public function test_update_occurrence_rejects_stale_content(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $manager = new manager();
        $manager->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $DB->set_field('course', 'summary', '<img src="https://example.com/one.jpg" alt="Newer text">', ['id' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('source content has changed');
        $manager->update_occurrence($occurrence->id, 'Older text', false, (int) $USER->id);
    }

    /**
     * Reanalysis invalidates a ready suggestion when its source changes.
     */
    public function test_reanalysis_marks_suggestion_stale(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = context_course::instance($course->id);
        $manager = new manager();
        $manager->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $suggestionid = $DB->insert_record('report_imagealt_suggestion', (object) [
            'occurrenceid' => $occurrence->id,
            'batchid' => null,
            'userid' => $USER->id,
            'status' => 'ready',
            'originalhash' => $occurrence->contenthash,
            'suggestion' => 'The old suggestion',
            'errormessage' => null,
            'attempts' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->set_field('course', 'summary', '<img src="https://example.com/one.jpg" alt="Newer text">', ['id' => $course->id]);

        $manager->scan_context($context);

        $this->assertSame(
            'stale',
            $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $suggestionid]),
        );
    }

    /**
     * Removing all images stales every cached occurrence and unpublished suggestion in one reconciliation pass.
     */
    public function test_scan_course_marks_removed_occurrences_and_suggestions_stale(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="https://example.com/one.jpg"><img src="https://example.com/two.jpg">',
        ]);
        $manager = new manager();
        $manager->scan_course(context_course::instance($course->id));

        $occurrences = $DB->get_records('report_imagealt_occurrence', ['courseid' => $course->id]);
        foreach ($occurrences as $occurrence) {
            $DB->insert_record('report_imagealt_suggestion', (object) [
                'occurrenceid' => $occurrence->id,
                'batchid' => null,
                'userid' => $USER->id,
                'status' => 'ready',
                'originalhash' => $occurrence->contenthash,
                'suggestion' => 'Suggestion',
                'errormessage' => null,
                'attempts' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        $DB->set_field('course', 'summary', '', ['id' => $course->id]);

        $manager->scan_course(context_course::instance($course->id));

        $this->assertCount(2, $occurrences);
        $this->assertSame(2, $DB->count_records('report_imagealt_occurrence', ['analysisstate' => 'stale']));
        $this->assertSame(2, $DB->count_records('report_imagealt_suggestion', ['status' => 'stale']));
    }

    /**
     * A user profile description is discovered sitewide and is only in scope at site level.
     */
    public function test_scan_user_profile_is_sitewide_only(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $manager = new manager();

        $manager->scan_user($user->id);

        $occurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['providerkey' => 'core_user', 'itemkeyhash' => hash('sha256', "user:{$user->id}")],
            '*',
            MUST_EXIST,
        );
        $this->assertSame(context_user::instance($user->id)->id, (int) $occurrence->contextid);
        $this->assertNull($occurrence->courseid);
        $this->assertTrue(manager::is_in_scope($occurrence, context_system::instance()));

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(manager::is_in_scope($occurrence, context_course::instance($course->id)));
    }

    /**
     * Editing one's own profile description is permitted; editing another user's requires the higher capability.
     */
    public function test_update_user_profile_occurrence_permissions(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $manager = new manager();
        $manager->scan_user($user->id);
        $occurrence = $DB->get_record(
            'report_imagealt_occurrence',
            ['providerkey' => 'core_user', 'itemkeyhash' => hash('sha256', "user:{$user->id}")],
            '*',
            MUST_EXIST,
        );

        $this->assertTrue($manager->can_edit_occurrence($occurrence, (int) $user->id));

        $other = $this->getDataGenerator()->create_user();
        $this->assertFalse($manager->can_edit_occurrence($occurrence, (int) $other->id));
    }

    /**
     * The preview endpoint resolves an image through its provider, and only for a user allowed to change it.
     *
     * Resolving through the provider is the whole point of serving previews here: a pluginfile address built from
     * the file's own fields is not valid for every component the report indexes.
     */
    public function test_resolve_preview_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $manager = new manager();

        $file = $manager->resolve_preview_file((int) $occurrence->id, (int) get_admin()->id);
        $this->assertNotNull($file);
        $this->assertSame('summit.png', $file->get_filename());
        $this->assertSame($occurrence->previewhash, $file->get_contenthash());

        // Somebody who cannot change the content is not served its file either.
        $other = $this->getDataGenerator()->create_user();
        $this->assertNull($manager->resolve_preview_file((int) $occurrence->id, (int) $other->id));

        // An occurrence that no longer exists is not an error, just nothing to serve.
        $this->assertNull($manager->resolve_preview_file((int) $occurrence->id + 1000, (int) get_admin()->id));
    }

    /**
     * Deleting a course removes its cached occurrences and unpublished suggestions.
     */
    public function test_course_deletion_cleanup(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $manager = new manager();
        $manager->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $DB->insert_record('report_imagealt_suggestion', (object) [
            'occurrenceid' => $occurrence->id,
            'batchid' => null,
            'userid' => $USER->id,
            'status' => 'ready',
            'originalhash' => $occurrence->contenthash,
            'suggestion' => 'Suggestion',
            'errormessage' => null,
            'attempts' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $event = \core\event\course_deleted::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => ['shortname' => $course->shortname, 'fullname' => $course->fullname],
        ]);

        observer::course_deleted($event);

        $this->assertFalse($DB->record_exists('report_imagealt_occurrence', ['courseid' => $course->id]));
        $this->assertFalse($DB->record_exists('report_imagealt_suggestion', ['occurrenceid' => $occurrence->id]));
    }

    /**
     * Scanning tells the three kinds of unresolvable source apart, because only one of them proves anything.
     */
    public function test_scanning_reports_a_missing_file_as_broken(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="@@PLUGINFILE@@/gone.png">'
                . '<img src="https://example.com/remote.png">'
                . '<img src="/theme/image.php/boost/core/1/moodlelogo">',
            'summaryformat' => FORMAT_HTML,
        ]);

        (new manager())->scan_context(context_course::instance($course->id));

        $records = array_values($DB->get_records(
            'report_imagealt_occurrence',
            ['courseid' => $course->id],
            'position',
        ));
        $this->assertCount(3, $records);

        // Claimed by the content and not there, so it is gone and no description will help.
        $this->assertSame('broken', $records[0]->status);
        $this->assertSame('broken', $records[0]->reason);
        $this->assertSame(0, (int) $records[0]->aieligible);

        // Somewhere else entirely: it may well load, and it can be fetched in order to be described.
        $this->assertSame('missing', $records[1]->status);
        $this->assertSame(1, (int) $records[1]->aieligible);

        // An ordinary address on this site that this content does not own. Nothing can be concluded from it not
        // resolving here, so it is classified on its alternative text like any other image, and not offered to AI.
        $this->assertSame('missing', $records[2]->status);
        $this->assertSame(0, (int) $records[2]->aieligible);
    }

    /**
     * A broken image is refused alternative text when asked directly, not merely withheld in the report.
     */
    public function test_update_occurrence_refuses_a_broken_image(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<img src="@@PLUGINFILE@@/gone.png">',
            'summaryformat' => FORMAT_HTML,
        ]);
        $manager = new manager();
        $manager->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:brokenimage', 'report_imagealt'));
        $manager->update_occurrence((int) $occurrence->id, 'A description', false, (int) $USER->id);
    }
}
