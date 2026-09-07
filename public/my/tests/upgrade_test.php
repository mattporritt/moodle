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

namespace core_my;

use context_system;

/**
 * Tests for the responsive dashboard upgrade migration.
 *
 * @package    core_my
 * @category   test
 * @coversNothing
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Legacy main and side-region stacks are migrated to the six-column grid.
     */
    public function test_legacy_regions_are_migrated(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('block_positions');
        foreach (['gridrows', 'gridcolumns', 'gridrow', 'gridcolumn'] as $fieldname) {
            $field = new \xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        $page = $DB->get_record('my_pages', [
            'userid' => null,
            'name' => '__default',
            'private' => 1,
        ], '*', MUST_EXIST);
        $common = [
            'parentcontextid' => context_system::instance()->id,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $page->id,
        ];
        // Remove the site's install-time default blocks so the blocks created below are the
        // only ones on the page, making them predictably first (and therefore taller) in their
        // respective stacks.
        foreach ($DB->get_records('block_instances', $common) as $preexisting) {
            blocks_delete_instance($preexisting);
        }
        $main = $this->getDataGenerator()->create_block('html', $common + [
            'defaultregion' => 'content',
            'defaultweight' => 50,
        ]);
        $side = $this->getDataGenerator()->create_block('online_users', $common + [
            'defaultregion' => 'side-pre',
            'defaultweight' => 50,
        ]);
        $postside = $this->getDataGenerator()->create_block('calendar_month', $common + [
            'defaultregion' => 'side-post',
            'defaultweight' => 50,
        ]);

        set_config('version', 2026081800.00);
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/lib/db/upgrade.php');
        $this->assertTrue(xmldb_main_upgrade(2026081800.00));

        $mainposition = $DB->get_record('block_positions', ['blockinstanceid' => $main->id], '*', MUST_EXIST);
        $sideposition = $DB->get_record('block_positions', ['blockinstanceid' => $side->id], '*', MUST_EXIST);
        $postsideposition = $DB->get_record('block_positions', ['blockinstanceid' => $postside->id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $mainposition->gridcolumn);
        $this->assertSame(3, (int) $mainposition->gridcolumns);
        $this->assertSame(4, (int) $sideposition->gridcolumn);
        $this->assertSame(1, (int) $sideposition->gridcolumns);
        $this->assertSame(4, (int) $postsideposition->gridcolumn);
        $this->assertSame(1, (int) $postsideposition->gridcolumns);
        $this->assertTrue(
            (int) $sideposition->gridrow + (int) $sideposition->gridrows <= (int) $postsideposition->gridrow
                || (int) $postsideposition->gridrow + (int) $postsideposition->gridrows <= (int) $sideposition->gridrow,
            'Legacy side-pre and side-post blocks must not overlap after migration.',
        );
        $this->assertSame(4, (int) $mainposition->gridrows);
        $this->assertSame(4, (int) $sideposition->gridrows);
        $this->assertSame(3, (int) $postsideposition->gridrows);
    }
}
