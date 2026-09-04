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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core_my\local;

use core_external\external_api;
use core_my\external\get_dashboard;

/**
 * Tests for the cheap, layout-only dashboard read used to shape the loading placeholder.
 *
 * @package    core_my
 * @category   test
 * @covers     \core_my\local\dashboard::get_skeleton_layout
 * @covers     \core_my\local\dashboard::get_loading_placeholder
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard_test extends \advanced_testcase {
    /**
     * Once a dashboard's grid has been saved, its shape can be read back cheaply, without
     * instantiating or rendering any block, and matches the layout the full read returns.
     */
    public function test_get_skeleton_layout_matches_a_saved_grid(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;

        $result = get_dashboard::execute(false);
        $result = external_api::clean_returnvalue(get_dashboard::execute_returns(), $result);
        $PAGE = new \moodle_page();
        dashboard::save(false, $result['layout']);

        $PAGE = new \moodle_page();
        $skeleton = dashboard::get_skeleton_layout(false);

        $expected = $result['layout'];
        usort($expected, static fn(array $left, array $right): int =>
            [$left['row'], $left['column'], $left['id']] <=> [$right['row'], $right['column'], $right['id']]);
        $this->assertSame($expected, $skeleton);
    }

    /**
     * Before any grid position has ever been saved, the shape isn't cheaply knowable (deriving
     * it would mean the same block instantiation and content rendering this exists to avoid), so
     * the caller is told to fall back to a generic placeholder instead of guessing.
     */
    public function test_get_skeleton_layout_is_empty_without_a_saved_grid(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame([], dashboard::get_skeleton_layout(false));
    }

    /**
     * A block whose position predates the grid (for example a pre-migration row a backfill
     * missed) has no grid columns saved: the persisted grid is incomplete, so the real shape
     * still isn't cheaply knowable and the result stays empty rather than silently omitting
     * that block from the placeholder.
     */
    public function test_get_skeleton_layout_is_empty_when_a_block_is_missing_its_position(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;

        $result = get_dashboard::execute(false);
        $result = external_api::clean_returnvalue(get_dashboard::execute_returns(), $result);
        $PAGE = new \moodle_page();
        dashboard::save(false, $result['layout']);

        $DB->set_field(
            'block_positions',
            'gridcolumn',
            null,
            ['blockinstanceid' => $result['layout'][0]['id']],
        );

        $PAGE = new \moodle_page();
        $this->assertSame([], dashboard::get_skeleton_layout(false));
    }

    /**
     * When a real layout is knowable, the server-rendered placeholder (the very first thing the
     * browser paints, before React has run) is positioned to match it, instead of falling back
     * to the generic grid.
     */
    public function test_loading_placeholder_is_positioned_from_a_given_layout(): void {
        $placeholder = dashboard::get_loading_placeholder([
            ['id' => 1, 'column' => 1, 'row' => 0, 'columns' => 3, 'rows' => 4],
            ['id' => 2, 'column' => 4, 'row' => 0, 'columns' => 1, 'rows' => 4],
        ]);

        $this->assertStringContainsString('core-my-dashboard-loading__grid--positioned', $placeholder);
        $this->assertSame(2, substr_count($placeholder, 'core-my-dashboard-loading__tile--positioned'));
        $this->assertStringContainsString('grid-column: 2 / span 3;', $placeholder);
        $this->assertStringContainsString('grid-column: 5 / span 1;', $placeholder);
    }

    /**
     * Without a knowable layout, the placeholder falls back to the generic grid exactly as
     * before.
     */
    public function test_loading_placeholder_is_generic_without_a_layout(): void {
        $placeholder = dashboard::get_loading_placeholder();

        $this->assertStringContainsString('core-my-dashboard-loading', $placeholder);
        $this->assertStringNotContainsString('core-my-dashboard-loading__grid--positioned', $placeholder);
        $this->assertSame(6, substr_count($placeholder, 'core-my-dashboard-loading__tile'));
    }
}
