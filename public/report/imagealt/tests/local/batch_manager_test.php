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

use report_imagealt\ai_availability_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../ai_availability_test_trait.php');

/**
 * Tests for bulk batch control.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(batch_manager::class)]
final class batch_manager_test extends \advanced_testcase {
    use ai_availability_test_trait;

    /**
     * Create a batch with one suggestion per given status.
     *
     * @param int $userid Owning user ID.
     * @param string[] $statuses One suggestion status per item to create.
     * @param int|null $occurrenceid Occurrence every suggestion points at, for tests that need a real one.
     * @return int The batch ID.
     */
    private function create_batch(int $userid, array $statuses, ?int $occurrenceid = null): int {
        global $DB;

        $now = time();
        $batchid = $DB->insert_record('report_imagealt_batch', (object) [
            'contextid' => \context_system::instance()->id,
            'userid' => $userid,
            'status' => 'processing',
            'total' => count($statuses),
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        foreach ($statuses as $index => $status) {
            $DB->insert_record('report_imagealt_suggestion', (object) [
                'occurrenceid' => $occurrenceid ?? $index + 1,
                'batchid' => $batchid,
                'userid' => $userid,
                'status' => $status,
                'originalhash' => sha1((string) $index),
                'suggestion' => $status === 'ready' ? 'A description.' : null,
                'errormessage' => null,
                'attempts' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return $batchid;
    }

    /**
     * Create an indexed occurrence a suggestion can point at.
     *
     * @param int $courseid Course the image lives in.
     * @return int The occurrence ID.
     */
    private function create_occurrence(int $courseid): int {
        global $DB;

        $now = time();
        return $DB->insert_record('report_imagealt_occurrence', (object) [
            'providerkey' => 'course',
            'itemkey' => "course:{$courseid}",
            'itemkeyhash' => sha1("course:{$courseid}"),
            'occurrencekey' => sha1(uniqid('', true)),
            'position' => 0,
            'contextid' => \context_course::instance($courseid)->id,
            'courseid' => $courseid,
            'categoryid' => null,
            'component' => 'core_course',
            'contenttype' => 'coursesummary',
            'itemname' => 'Example course',
            'fieldname' => 'summary',
            'contenthash' => sha1('content'),
            'occurrencehash' => sha1('occurrence'),
            'src' => 'image.png',
            'status' => 'missing',
            'reason' => 'missing',
            'aieligible' => 1,
            'analysisstate' => 'ready',
            'timeanalysed' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Nothing outstanding means no summary at all, rather than a panel reporting zeroes.
     */
    public function test_get_outstanding_summary_is_empty_when_all_work_is_resolved(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $occurrenceid = $this->create_occurrence((int) $course->id);
        $this->create_batch((int) $user->id, ['accepted', 'discarded', 'failed'], $occurrenceid);

        $summary = (new batch_manager())->get_outstanding_summary(\context_system::instance(), (int) $user->id);

        $this->assertNull($summary);
    }

    /**
     * Outstanding work is counted by kind and across every batch it spans, so the report can offer one entry point.
     */
    public function test_get_outstanding_summary_counts_work_across_batches(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $occurrenceid = $this->create_occurrence((int) $course->id);

        $this->create_batch((int) $user->id, ['ready', 'accepted'], $occurrenceid);
        $newest = $this->create_batch((int) $user->id, ['ready', 'queued', 'processing'], $occurrenceid);
        // Another user's outstanding work must not be reported as this user's.
        $this->create_batch((int) $other->id, ['ready', 'queued'], $occurrenceid);

        $summary = (new batch_manager())->get_outstanding_summary(\context_system::instance(), (int) $user->id);

        $this->assertSame(2, $summary['ready']);
        $this->assertSame(2, $summary['generating']);
        $this->assertSame(2, $summary['batches']);
        $this->assertSame($newest, $summary['latestbatchid']);
    }

    /**
     * The summary follows the report's own scope, so a course report only reports that course's work.
     */
    public function test_get_outstanding_summary_respects_the_report_scope(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->create_batch((int) $user->id, ['ready'], $this->create_occurrence((int) $course->id));

        $manager = new batch_manager();
        $insidescope = $manager->get_outstanding_summary(
            \context_course::instance((int) $course->id),
            (int) $user->id,
        );
        $this->assertSame(1, $insidescope['ready']);

        $outsidescope = $manager->get_outstanding_summary(
            \context_course::instance((int) $othercourse->id),
            (int) $user->id,
        );
        $this->assertNull($outsidescope);
    }

    /**
     * Cancelling moves the counters with the items, so the summary cannot claim work is ready that was cancelled.
     */
    public function test_cancel_recounts_the_batch(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // One suggestion already generated, two still waiting when the user cancels.
        $batchid = $this->create_batch((int) $user->id, ['ready', 'queued', 'queued']);
        $DB->set_field('report_imagealt_batch', 'completed', 1, ['id' => $batchid]);

        (new batch_manager())->cancel($batchid, (int) $user->id);

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        $this->assertSame('cancelled', $batch->status);
        $this->assertEquals(1, $batch->completed);
        $this->assertEquals(2, $batch->cancelled);
        $this->assertEquals(0, $batch->failed);
        // The generated suggestion survives cancellation; only the unstarted items are skipped.
        $this->assertEquals(1, $DB->count_records('report_imagealt_suggestion', [
            'batchid' => $batchid,
            'status' => 'ready',
        ]));
    }

    /**
     * Counting treats accepted and discarded suggestions as done, and stale ones as failures.
     */
    public function test_apply_counts_groups_statuses_by_outcome(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch(
            (int) $user->id,
            ['ready', 'accepted', 'discarded', 'failed', 'stale', 'cancelled', 'queued'],
        );

        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        $counts = (new batch_manager())->apply_counts($batch);

        $this->assertEquals(3, $batch->completed);
        $this->assertEquals(2, $batch->failed);
        $this->assertEquals(1, $batch->cancelled);
        $this->assertSame(1, $counts['queued']);
    }

    /**
     * Accepting a suggestion writes it into the content the image lives in, not just onto the suggestion record.
     */
    public function test_accept_applies_the_suggestion_to_the_image(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => 'A snow covered mountain summit.',
        ]);

        $result = (new batch_manager())->accept(
            (int) $suggestion->batchid,
            [(int) $suggestion->id],
            (int) $DB->get_field('user', 'id', ['username' => 'admin']),
        );

        $this->assertEquals(1, $result['accepted']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertSame('accepted', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $suggestion->id]));
        // The alternative text is on the image in the course summary itself, which is the point of accepting.
        $summary = (string) $DB->get_field('course', 'summary', ['id' => $course->id]);
        $this->assertStringContainsString('alt="A snow covered mountain summit."', $summary);
        // Rescanned on write, so the report reflects the new text without waiting for the next scan.
        $this->assertSame(
            'A snow covered mountain summit.',
            $DB->get_field('report_imagealt_occurrence', 'alttext', ['id' => $occurrence->id]),
        );
    }

    /**
     * Accepting only touches this user's own ready suggestions in this batch, and reports what it could not apply.
     */
    public function test_accept_skips_anything_not_ready(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $user->id, ['ready', 'failed', 'accepted']);
        [$ready, $failed, $already] = array_values($DB->get_records(
            'report_imagealt_suggestion',
            ['batchid' => $batchid],
            'id ASC',
        ));

        // The occurrences these point at do not exist, so applying the ready one cannot succeed either. What is
        // being asserted here is that only it was ever attempted, and that the rest were rejected outright.
        $result = (new batch_manager())->accept($batchid, [
            (int) $ready->id,
            (int) $failed->id,
            (int) $already->id,
        ], (int) $user->id);

        $this->assertEquals(0, $result['accepted']);
        $this->assertEquals(3, $result['skipped']);
        // Only the attempted one moved: a suggestion that can no longer be applied is marked stale rather than
        // silently left as ready for the user to try again on forever.
        $this->assertSame('stale', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $ready->id]));
        $this->assertSame('failed', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $failed->id]));
        $this->assertSame('accepted', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $already->id]));
    }

    /**
     * A suggestion belonging to another batch cannot be accepted through this one.
     */
    public function test_accept_ignores_suggestions_from_another_batch(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $user->id, ['ready']);
        $otherbatchid = $this->create_batch((int) $user->id, ['ready']);
        $otherid = (int) $DB->get_field('report_imagealt_suggestion', 'id', ['batchid' => $otherbatchid]);

        $result = (new batch_manager())->accept($batchid, [$otherid], (int) $user->id);

        $this->assertEquals(0, $result['accepted']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertSame('ready', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $otherid]));
    }

    /**
     * Only the user who requested a batch can accept from it.
     */
    public function test_accept_rejects_another_user(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $owner->id, ['ready']);

        $this->expectException(\moodle_exception::class);
        (new batch_manager())->accept($batchid, [], (int) $other->id);
    }

    /**
     * Discarding rejects the suggestion and deliberately leaves the image alone.
     */
    public function test_discard_rejects_the_suggestion_without_touching_the_image(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = $generator->create_suggestion([
            'occurrenceid' => $occurrence->id,
            'suggestion' => 'A snow covered mountain summit.',
        ]);
        $before = (string) $DB->get_field('course', 'summary', ['id' => $course->id]);

        $result = (new batch_manager())->discard(
            (int) $suggestion->batchid,
            [(int) $suggestion->id],
            (int) get_admin()->id,
        );

        $this->assertEquals(1, $result['discarded']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertSame('discarded', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $suggestion->id]));
        // The whole point of discarding: the rejected description never reaches the content.
        $this->assertStringNotContainsString('A snow covered mountain summit.', $before);
        $this->assertSame($before, (string) $DB->get_field('course', 'summary', ['id' => $course->id]));
    }

    /**
     * Discarding is what lets a reviewer clear a description they disagree with off the report's outstanding count.
     */
    public function test_discard_clears_the_outstanding_summary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'summit.png']);
        $suggestion = $generator->create_suggestion(['occurrenceid' => $occurrence->id]);
        $manager = new batch_manager();
        $context = \context_system::instance();
        $userid = (int) get_admin()->id;

        $this->assertNotNull($manager->get_outstanding_summary($context, $userid));

        $manager->discard((int) $suggestion->batchid, [(int) $suggestion->id], $userid);

        $this->assertNull($manager->get_outstanding_summary($context, $userid));
    }

    /**
     * Discarding only touches this user's own ready suggestions in this batch, and reports what it could not.
     */
    public function test_discard_skips_anything_not_ready(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $user->id, ['ready', 'failed', 'accepted']);
        [$ready, $failed, $already] = array_values($DB->get_records(
            'report_imagealt_suggestion',
            ['batchid' => $batchid],
            'id ASC',
        ));

        $result = (new batch_manager())->discard(
            $batchid,
            [(int) $ready->id, (int) $failed->id, (int) $already->id],
            (int) $user->id,
        );

        $this->assertEquals(1, $result['discarded']);
        $this->assertEquals(2, $result['skipped']);
        $this->assertSame('discarded', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $ready->id]));
        // A failed suggestion is still retryable and an accepted one is already on the image, so neither is a
        // description the user is being asked to review.
        $this->assertSame('failed', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $failed->id]));
        $this->assertSame('accepted', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $already->id]));
    }

    /**
     * Discarding is refused for a batch belonging to somebody else.
     */
    public function test_discard_rejects_another_user(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $owner->id, ['ready']);

        $this->expectException(\moodle_exception::class);
        (new batch_manager())->discard($batchid, [], (int) $other->id);
    }

    /**
     * An image whose description is already generating or waiting to be reviewed is not queued a second time.
     *
     * Without this the same image can be sent for generation repeatedly, paying for a provider call each time and
     * leaving several suggestions competing to describe one image.
     */
    public function test_create_skips_images_with_an_outstanding_suggestion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $outstanding = $generator->create_image(['courseid' => $course->id, 'filename' => 'waiting.png']);
        $fresh = $generator->create_image(['courseid' => $course->id, 'filename' => 'fresh.png']);
        $userid = (int) get_admin()->id;
        $generator->create_suggestion(['occurrenceid' => $outstanding->id]);
        $this->stub_ai_availability(true);

        $batch = (new batch_manager())->create(
            \context_course::instance((int) $course->id),
            [(int) $outstanding->id, (int) $fresh->id],
            $userid,
        );

        // Only the image without outstanding work is queued, and the batch total says so rather than counting an
        // image it created no suggestion for.
        $this->assertEquals(1, $batch->total);
        $this->assertTrue(batch_manager::has_outstanding_suggestion((int) $fresh->id, $userid));
        // Another user's outstanding work is their own, and does not block this image for anybody else.
        $other = $this->getDataGenerator()->create_user();
        $this->assertFalse(batch_manager::has_outstanding_suggestion((int) $outstanding->id, (int) $other->id));
    }

    /**
     * A resolved suggestion does not block the image from being sent for generation again.
     */
    public function test_create_allows_images_whose_suggestion_is_resolved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'again.png']);
        $generator->create_suggestion(['occurrenceid' => $occurrence->id, 'status' => 'discarded']);
        $this->stub_ai_availability(true);

        $batch = (new batch_manager())->create(
            \context_course::instance((int) $course->id),
            [(int) $occurrence->id],
            (int) get_admin()->id,
        );

        $this->assertEquals(1, $batch->total);
    }


    /**
     * Several images in one content item can be accepted together.
     *
     * Each accept rewrites the whole item, so the item's content hash changes under the images that have not been
     * applied yet. Those suggestions describe images the write did not touch and must survive it.
     */
    public function test_accept_applies_every_suggestion_in_one_content_item(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $first = $generator->create_image(['courseid' => $course->id, 'filename' => 'one.png']);
        $second = $generator->create_image(['courseid' => $course->id, 'filename' => 'two.png']);
        $onesuggestion = $generator->create_suggestion([
            'occurrenceid' => $first->id,
            'suggestion' => 'The first image.',
            'batch' => 'shared',
        ]);
        $twosuggestion = $generator->create_suggestion([
            'occurrenceid' => $second->id,
            'suggestion' => 'The second image.',
            'batch' => 'shared',
        ]);

        $result = (new batch_manager())->accept(
            (int) $onesuggestion->batchid,
            [(int) $onesuggestion->id, (int) $twosuggestion->id],
            (int) $DB->get_field('user', 'id', ['username' => 'admin']),
        );

        $this->assertEquals(2, $result['accepted']);
        $this->assertEquals(0, $result['skipped']);
        $summary = (string) $DB->get_field('course', 'summary', ['id' => $course->id]);
        $this->assertStringContainsString('alt="The first image."', $summary);
        $this->assertStringContainsString('alt="The second image."', $summary);
    }

    /**
     * Only the user who requested a batch can cancel it.
     */
    public function test_cancel_rejects_another_user(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $owner->id, ['queued']);

        $this->expectException(\moodle_exception::class);
        (new batch_manager())->cancel($batchid, (int) $other->id);
    }

    /**
     * A decorative image is not described, even if it is submitted directly. Applying a description to one would
     * undo the decision to hide it from screen readers, and the report page a selection came from can be minutes old
     * by the time it is submitted.
     */
    public function test_create_skips_decorative_images(): void {
        global $DB;

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

        $batch = (new batch_manager())->create(
            \context_course::instance((int) $course->id),
            [(int) $decorative->id, (int) $describable->id],
            (int) get_admin()->id,
        );

        $this->assertSame(1, (int) $batch->total);
        $this->assertSame(
            [(int) $describable->id],
            array_map('intval', $DB->get_fieldset_select(
                'report_imagealt_suggestion',
                'occurrenceid',
                'batchid = ?',
                [$batch->id],
            )),
        );
    }

    /**
     * A site with no provider for image descriptions cannot request them. Queuing the work anyway would report a
     * batch of failures for a feature the site simply does not have switched on.
     */
    public function test_create_is_refused_without_a_provider(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('report_imagealt');
        $course = $this->getDataGenerator()->create_course();
        $occurrence = $generator->create_image(['courseid' => $course->id, 'filename' => 'unreachable.png']);

        $this->stub_ai_availability(false);

        try {
            (new batch_manager())->create(
                \context_course::instance((int) $course->id),
                [(int) $occurrence->id],
                (int) get_admin()->id,
            );
            $this->fail('Requesting descriptions without a provider should be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame(get_string('error:aiunavailable', 'report_imagealt'), $e->getMessage());
        }

        // Refused before anything was written, so no batch is left behind for the user to find and wonder about.
        $this->assertSame(0, $DB->count_records('report_imagealt_batch'));
        $this->assertSame(0, $DB->count_records('report_imagealt_suggestion'));
    }

    /**
     * Retrying is refused for the same reason: without a provider it would only reproduce the failures.
     */
    public function test_retry_failed_is_refused_without_a_provider(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = $this->create_batch((int) $user->id, ['failed']);

        $this->stub_ai_availability(false);

        try {
            (new batch_manager())->retry_failed($batchid, (int) $user->id);
            $this->fail('Retrying without a provider should be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame(get_string('error:aiunavailable', 'report_imagealt'), $e->getMessage());
        }

        // The failed suggestion is left as it was rather than being requeued to fail again.
        $this->assertSame(1, $DB->count_records('report_imagealt_suggestion', ['status' => 'failed']));
    }
}
