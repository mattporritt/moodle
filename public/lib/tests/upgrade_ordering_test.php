<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core;

/**
 * Structural regression tests for upgrade step ordering in lib/db/upgrade.php.
 *
 * @package    core
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class upgrade_ordering_test extends \advanced_testcase {
    /**
     * MDL-89876: the task_adhoc.identityhash savepoint must run before any upgrade step
     * that can remove a block with existing instances, because block removal queues an
     * ad-hoc task with duplicate detection enabled (queue_adhoc_task() with
     * $checkforexisting = true), which queries that column. If a future upgrade step
     * reintroduces a block removal ahead of this savepoint, a real upgrade from an old
     * version would crash with "Unknown column 'identityhash'" before this test alerts
     * anyone to the regression.
     */
    public function test_identityhash_savepoint_precedes_block_removal_steps(): void {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/lib/db/upgrade.php');
        $this->assertNotFalse($source, 'Could not read lib/db/upgrade.php.');

        $identityhashpos = strpos($source, 'identityhash_uix');
        $this->assertNotFalse(
            $identityhashpos,
            'Could not locate the task_adhoc identityhash savepoint in upgrade.php.',
        );

        $matches = [];
        preg_match_all("/uninstall_plugin\('block',/", $source, $matches, PREG_OFFSET_CAPTURE);
        $blockremovalpositions = array_column($matches[0], 1);

        $this->assertNotEmpty(
            $blockremovalpositions,
            "Could not find any uninstall_plugin('block', ...) call in upgrade.php. " .
                'This test would otherwise pass vacuously without checking anything; update the ' .
                'search pattern if the code style changed.',
        );

        foreach ($blockremovalpositions as $position) {
            $this->assertLessThan(
                $position,
                $identityhashpos,
                'The task_adhoc identityhash savepoint must appear before every ' .
                    "uninstall_plugin('block', ...) call in upgrade.php (see MDL-89876).",
            );
        }
    }
}
