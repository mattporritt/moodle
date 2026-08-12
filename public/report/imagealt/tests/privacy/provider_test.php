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

namespace report_imagealt\privacy;

use context_course;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use report_imagealt\ai_availability_test_trait;
use report_imagealt\local\batch_manager;
use report_imagealt\local\manager;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../ai_availability_test_trait.php');

/**
 * Tests for personal data associated with a user's own profile occurrence, and with the batches and suggestions a
 * user requests against shared course, category, or activity content.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    use ai_availability_test_trait;

    /**
     * A user's own profile occurrence is discoverable, exportable, and erasable as their personal data.
     */
    public function test_user_profile_occurrence_lifecycle(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'description' => '<p><img src="portrait.jpg"></p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        (new manager())->scan_user($user->id);
        $context = context_user::instance($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertContains((int) $context->id, array_map('intval', $contextlist->get_contextids()));

        $userlist = new \core_privacy\local\request\userlist($context, 'report_imagealt');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $user->id, $userlist->get_userids());

        $approvedcontextlist = new approved_contextlist(\core_user::get_user($user->id), 'report_imagealt', [$context->id]);
        provider::export_user_data($approvedcontextlist);
        $exported = writer::with_context($context)->get_data([get_string('pluginname', 'report_imagealt')]);
        $this->assertNotEmpty($exported->occurrences);

        provider::delete_data_for_all_users_in_context($context);
        $this->assertFalse($DB->record_exists('report_imagealt_occurrence', [
            'providerkey' => 'core_user',
            'itemkeyhash' => hash('sha256', "user:{$user->id}"),
        ]));
    }

    /**
     * A requester's batch and suggestion in a shared course context are discoverable, exportable, and erasable as
     * their personal data, while the occurrence itself, which describes shared content rather than the requester,
     * is left in place.
     */
    public function test_batch_and_suggestion_lifecycle_in_a_course_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        global $USER;
        $requester = (int) $USER->id;
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = context_course::instance($course->id);
        (new manager())->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->stub_ai_availability(true);
        $batch = (new batch_manager())->create($context, [$occurrence->id], $requester);
        $suggestion = $DB->get_record('report_imagealt_suggestion', ['batchid' => $batch->id], '*', MUST_EXIST);

        $contextlist = provider::get_contexts_for_userid($requester);
        $this->assertContains((int) $context->id, array_map('intval', $contextlist->get_contextids()));

        $userlist = new \core_privacy\local\request\userlist($context, 'report_imagealt');
        provider::get_users_in_context($userlist);
        $this->assertContains($requester, $userlist->get_userids());

        $approvedcontextlist = new approved_contextlist(\core_user::get_user($requester), 'report_imagealt', [$context->id]);
        provider::export_user_data($approvedcontextlist);
        $exported = writer::with_context($context)->get_data([get_string('pluginname', 'report_imagealt')]);
        $this->assertNotEmpty($exported->batches);
        $this->assertNotEmpty($exported->suggestions);

        provider::delete_data_for_users(new approved_userlist($context, 'report_imagealt', [$requester]));

        $this->assertFalse($DB->record_exists('report_imagealt_batch', ['id' => $batch->id]));
        $this->assertFalse($DB->record_exists('report_imagealt_suggestion', ['id' => $suggestion->id]));
        // The occurrence describes the course's own content, not the requester, so erasing the requester's data
        // must not remove it.
        $this->assertTrue($DB->record_exists('report_imagealt_occurrence', ['id' => $occurrence->id]));
    }

    /**
     * The same lifecycle, erased through delete_data_for_user() (a contextlist-based erasure request) instead of
     * delete_data_for_users(), since the provider implements both independently.
     */
    public function test_batch_and_suggestion_erasure_through_delete_data_for_user(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $requester = (int) $USER->id;
        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="https://example.com/one.jpg">']);
        $context = context_course::instance($course->id);
        (new manager())->scan_context($context);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->stub_ai_availability(true);
        $batch = (new batch_manager())->create($context, [$occurrence->id], $requester);

        $contextlist = new approved_contextlist(\core_user::get_user($requester), 'report_imagealt', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('report_imagealt_batch', ['id' => $batch->id]));
        $this->assertFalse($DB->record_exists('report_imagealt_suggestion', ['batchid' => $batch->id]));
    }
}
