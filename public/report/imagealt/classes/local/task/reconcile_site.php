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

namespace report_imagealt\local\task;

/**
 * Start or continue a site-wide deterministic index reconciliation.
 *
 * This scheduled task never performs AI generation. Its only role is to make the materialised report index eventually
 * consistent when a component does not emit one of the content-change events observed by this plugin.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reconcile_site extends \core\task\scheduled_task {
    /**
     * Return the translated task name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskreconcile', 'report_imagealt');
    }

    /**
     * Start or continue the daily site discovery cursor.
     */
    #[\Override]
    public function execute(): void {
        $manager = new \report_imagealt\local\scan_manager();
        $manager->cleanup();
        $manager->request(\context_system::instance());
    }
}
