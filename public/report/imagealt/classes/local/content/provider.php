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

/**
 * Contract for independently owned editable HTML content.
 *
 * Implementations retain responsibility for permission checks and safe persistence. The report never guesses how another
 * component should save its content.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider {
    /**
     * Return the stable provider key.
     *
     * @return string Stable provider key.
     */
    public function get_key(): string;

    /**
     * Enumerate content items inside a requested course, category, or system context.
     *
     * @param \context $scope Requested report scope.
     * @return iterable<content_item>
     */
    public function get_items(\context $scope): iterable;

    /**
     * Load one current content item by its provider-specific key.
     *
     * @param string $key Item key.
     * @return content_item|null
     */
    public function get_item(string $key): ?content_item;

    /**
     * Check whether a user may update the item.
     *
     * @param content_item $item Content item.
     * @param int $userid User ID.
     * @return bool
     */
    public function can_edit(content_item $item, int $userid): bool;

    /**
     * Persist replacement HTML using the owning component's semantics.
     *
     * @param content_item $item Current content item.
     * @param string $html Replacement HTML.
     */
    public function update(content_item $item, string $html): void;

    /**
     * Resolve a stored image source in the item's own file area.
     *
     * @param content_item $item Content item.
     * @param string $src Raw image source attribute.
     * @return \stored_file|null
     */
    public function resolve_file(content_item $item, string $src): ?\stored_file;

    /**
     * Return a keyset-paginated page of candidate IDs for sitewide discovery.
     *
     * Only providers whose content is not owned by a course or category (for example, user profile fields) need to
     * override this. It lets scan_manager discover and queue their items directly at site scope, the same way course and
     * category IDs are discovered for course-owned content.
     *
     * @param int $lastid Last ID already discovered.
     * @param int $limit Maximum IDs to return.
     * @return int[]
     */
    public function get_sitewide_page(int $lastid, int $limit): array;
}
