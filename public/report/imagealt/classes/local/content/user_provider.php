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

namespace report_imagealt\local\content;

use context_system;
use context_user;
use moodle_url;
use report_imagealt\local\scope;

/**
 * Editable user profile descriptions, discovered sitewide rather than through a course or category.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_provider extends base_provider {
    #[\Override]
    public function get_key(): string {
        return 'core_user';
    }

    #[\Override]
    public function get_items(\context $scope): iterable {
        global $DB;

        // Profile descriptions are not owned by a course or category, so only a system-level scope can see them; the
        // sitewide discovery cursor in scan_manager is the normal path that walks and queues them individually.
        if ($scope->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $users = $DB->get_recordset_select('user', 'deleted = 0 AND description <> :empty', ['empty' => '']);
        try {
            foreach ($users as $user) {
                yield $this->build_item($user);
            }
        } finally {
            $users->close();
        }
    }

    #[\Override]
    public function get_item(string $key): ?content_item {
        global $DB;

        $parts = explode(':', $key);
        if (count($parts) !== 2 || $parts[0] !== 'user' || !ctype_digit($parts[1])) {
            return null;
        }

        $user = $DB->get_record('user', ['id' => (int) $parts[1], 'deleted' => 0]);
        return $user ? $this->build_item($user) : null;
    }

    #[\Override]
    public function can_edit(content_item $item, int $userid): bool {
        $context = \context::instance_by_id($item->contextid);
        if ((int) $context->instanceid === $userid) {
            return has_capability('moodle/user:editownprofile', context_system::instance(), $userid);
        }
        return has_capability('moodle/user:editprofile', $context, $userid);
    }

    #[\Override]
    public function update(content_item $item, string $html): void {
        [, $id] = explode(':', $item->key, 2);
        \core\user::update_user((object) [
            'id' => (int) $id,
            'description' => $html,
            'descriptionformat' => FORMAT_HTML,
        ], false, false);
    }

    /**
     * Return a page of candidate user IDs for sitewide discovery.
     *
     * @param int $lastid Last user ID already discovered.
     * @param int $limit Maximum IDs to return.
     * @return int[]
     */
    public function get_sitewide_page(int $lastid, int $limit): array {
        return scope::get_user_page(context_system::instance(), $lastid, $limit);
    }

    /**
     * Build a user profile description item.
     *
     * @param \stdClass $user User record.
     * @return content_item
     */
    private function build_item(\stdClass $user): content_item {
        $context = context_user::instance($user->id);
        return new content_item(
            key: "user:{$user->id}",
            contextid: $context->id,
            courseid: null,
            categoryid: null,
            component: 'core_user',
            contenttype: get_string('user'),
            itemname: fullname($user),
            fieldname: 'description',
            html: (string) $user->description,
            editurl: new moodle_url('/user/editadvanced.php', ['id' => $user->id, 'course' => SITEID]),
            filecontextid: $context->id,
            filecomponent: 'user',
            filearea: 'profile',
            fileitemid: 0,
            formatfield: 'descriptionformat',
        );
    }
}
