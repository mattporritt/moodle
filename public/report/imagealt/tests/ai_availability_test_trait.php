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

namespace report_imagealt;

/**
 * Switches this report's AI features on or off for a test.
 *
 * One definition of what "this site can write image descriptions" means, so a requirement added to that answer is
 * added once here rather than found one failing test at a time.
 *
 * @package    report_imagealt
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait ai_availability_test_trait {
    /**
     * Report whether a provider able to write image descriptions exists, and whether the placement offering it is
     * switched on.
     *
     * The capability that also gates it is held by admins and course managers, so tests running as either need
     * nothing further.
     *
     * @param bool $available Whether AI image descriptions should be available.
     * @return \PHPUnit\Framework\MockObject\MockObject The manager, returned so a test that also needs to stub the
     *      response to a request can add that to the same mock.
     */
    private function stub_ai_availability(bool $available): \PHPUnit\Framework\MockObject\MockObject {
        set_config('enabled', (int) $available, 'aiplacement_reportbuilder');

        $aimanager = $this->createMock(\core_ai\manager::class);
        $aimanager->method('is_action_available')->willReturn($available);
        $aimanager->method('is_action_enabled')->willReturn($available);
        $aimanager->method('is_action_enabled_in_context')->willReturn($available);
        \core\di::set(\core_ai\manager::class, fn() => $aimanager);

        return $aimanager;
    }
}
