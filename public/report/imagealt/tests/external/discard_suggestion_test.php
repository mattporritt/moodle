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

namespace report_imagealt\external;

use context_course;
use core_external\external_api;
use report_imagealt\local\manager;

/**
 * Tests for the discard_suggestion external function.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(discard_suggestion::class)]
final class discard_suggestion_test extends \advanced_testcase {
    /**
     * Create a course occurrence and an unpublished 'ready' suggestion owned by the given user.
     *
     * @param int $userid Owning user ID.
     * @return int Suggestion record ID.
     */
    private function create_suggestion(int $userid): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['summary' => '<img src="one.jpg">']);
        (new manager())->scan_context(context_course::instance($course->id));
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['courseid' => $course->id], '*', MUST_EXIST);

        $now = time();
        return $DB->insert_record('report_imagealt_suggestion', (object) [
            'occurrenceid' => $occurrence->id,
            'batchid' => null,
            'userid' => $userid,
            'status' => 'ready',
            'originalhash' => $occurrence->contenthash,
            'suggestion' => 'A lake at sunset.',
            'errormessage' => null,
            'attempts' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * A user can discard their own suggestion.
     */
    public function test_execute_discards_own_suggestion(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $suggestionid = $this->create_suggestion((int) $USER->id);

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_discard_suggestion', [
            'suggestionid' => $suggestionid,
        ]);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame('discarded', $DB->get_field('report_imagealt_suggestion', 'status', ['id' => $suggestionid]));
    }

    /**
     * A user cannot discard another user's suggestion.
     */
    public function test_execute_rejects_other_users_suggestion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $owner = $this->getDataGenerator()->create_user();
        $suggestionid = $this->create_suggestion((int) $owner->id);

        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('report_imagealt_discard_suggestion', [
            'suggestionid' => $suggestionid,
        ]);

        $this->assertTrue($result['error']);
    }
}
