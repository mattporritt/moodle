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
 * Drain dirty targets recorded by content-change event observers.
 *
 * Event observers only persist a lightweight dirty row so that ordinary site activity never places tasks on the shared ad
 * hoc queue. This scheduled task scans those targets directly within a time budget and advances any site or category
 * discovery cursor, resuming on its next run, in the same spirit as core_search's search_index_task. It never performs AI
 * generation.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process_queue extends \core\task\scheduled_task {
    /**
     * Return the translated task name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskprocessqueue', 'report_imagealt');
    }

    /**
     * Scan any dirty targets awaiting analysis within the drain budget.
     */
    #[\Override]
    public function execute(): void {
        (new \report_imagealt\local\scan_manager())->drain();
    }
}
