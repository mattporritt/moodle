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

use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use report_imagealt\local\manager;

/**
 * Tests for personal data associated with a user's own profile occurrence.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
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
}
