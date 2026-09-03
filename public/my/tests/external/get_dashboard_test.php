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

namespace core_my\external;

use core_external\external_api;

/**
 * Tests for the responsive dashboard read external function.
 *
 * @package    core_my
 * @category   test
 * @covers     \core_my\external\get_dashboard
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_dashboard_test extends \advanced_testcase {
    /**
     * The server-rendered mount content prevents an empty page before React starts.
     */
    public function test_loading_placeholder_is_accessible_and_full(): void {
        $placeholder = \core_my\local\dashboard::get_loading_placeholder();

        $this->assertStringContainsString('core-my-dashboard-loading', $placeholder);
        $this->assertStringContainsString('role="status"', $placeholder);
        $this->assertStringContainsString('aria-busy="true"', $placeholder);
        $this->assertSame(6, substr_count($placeholder, 'core-my-dashboard-loading__tile'));
    }

    /**
     * The response contains rendered blocks, canonical layout and labels.
     */
    public function test_execute_returns_dashboard_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = get_dashboard::execute(false);
        $result = external_api::clean_returnvalue(get_dashboard::execute_returns(), $result);

        $this->assertFalse($result['sitedefault']);
        $this->assertTrue($result['canedit']);
        $this->assertFalse($result['editing']);
        $this->assertTrue($result['caneditotherscope']);
        $this->assertStringContainsString('/my/index.php', $result['urls']['ownpage']);
        $this->assertStringContainsString('/my/indexsys.php', $result['urls']['sitedefault']);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('layout', $result);
        $this->assertArrayHasKey('availableblocks', $result);
        $this->assertArrayHasKey('javascript', $result);
        $this->assertStringContainsString('block_recentlyaccesseditems/main', $result['javascript']);
        $this->assertSame('Add a block', $result['labels']['addblock']);
        $this->assertStringContainsString('arrow keys', $result['labels']['moveinstructions']);
        $this->assertStringContainsString('arrow keys', $result['labels']['resizeinstructions']);
        $this->assertSame('Editing your dashboard', $result['labels']['scopeown']);
        $this->assertSame('Editing the default dashboard for all users', $result['labels']['scopesitedefault']);
    }

    /**
     * A brand-new user's dashboard (with no customisation of their own, so it is copied
     * straight from the site's unmigrated default page) uses the default grid shape: an
     * empty column on each side, content blocks taking the next three columns, side-region
     * blocks the column after, and the first block in each stack a row taller than the rest.
     */
    public function test_execute_returns_the_default_layout_shape(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = get_dashboard::execute(false);
        $result = external_api::clean_returnvalue(get_dashboard::execute_returns(), $result);

        $blockidsbyname = [];
        foreach ($result['blocks'] as $block) {
            $blockidsbyname[$block['name']] = $block['id'];
        }
        $positionsbyid = [];
        foreach ($result['layout'] as $item) {
            $positionsbyid[$item['id']] = $item;
        }

        $expected = [
            'myoverview' => ['column' => 1, 'row' => 0, 'columns' => 3, 'rows' => 4],
            'timeline' => ['column' => 1, 'row' => 4, 'columns' => 3, 'rows' => 3],
            'calendar_month' => ['column' => 4, 'row' => 0, 'columns' => 1, 'rows' => 4],
            'recentlyaccesseditems' => ['column' => 4, 'row' => 4, 'columns' => 1, 'rows' => 3],
        ];
        foreach ($expected as $blockname => $expectedposition) {
            $this->assertArrayHasKey(
                $blockname,
                $blockidsbyname,
                "Default dashboard is missing the {$blockname} block.",
            );
            $position = $positionsbyid[$blockidsbyname[$blockname]];
            foreach ($expectedposition as $field => $value) {
                $this->assertSame($value, $position[$field], "{$blockname}.{$field} did not match the default layout.");
            }
        }
    }

    /**
     * A user who can only edit their own dashboard cannot also switch to the site default.
     */
    public function test_execute_reports_no_other_scope_without_site_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = get_dashboard::execute(false);
        $result = external_api::clean_returnvalue(get_dashboard::execute_returns(), $result);

        $this->assertTrue($result['canedit']);
        $this->assertFalse($result['caneditotherscope']);
    }

    /**
     * Block requirements are collected after the dashboard header has prepared its block content.
     */
    public function test_block_requirements_are_collected_after_header_render(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        \core_my\local\dashboard::initialise_page(false);
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $PAGE->blocks->refresh_cached_content();
        $PAGE->blocks->get_content_for_all_regions($OUTPUT);
        $javascript = $PAGE->requires->get_end_code();
        $PAGE->end_collecting_javascript_requirements();

        $this->assertStringContainsString('block_recentlyaccesseditems/main', $javascript);
    }

    /**
     * The site-default dashboard only exposes controls when edit mode is active.
     */
    public function test_execute_site_default_respects_edit_mode(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 0;

        $result = get_dashboard::execute(true);

        $this->assertTrue($result['canedit']);
        $this->assertFalse($result['editing']);
        $this->assertTrue($result['caneditotherscope']);
    }

    /**
     * Only users with the site-dashboard capability can fetch the default layout.
     */
    public function test_execute_rejects_site_default_without_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        get_dashboard::execute(true);
    }

    /**
     * User-context blocks from a legacy side region remain visible in the grid.
     */
    public function test_execute_includes_legacy_side_region_blocks(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->getDataGenerator()->create_block('comments', [
            'parentcontextid' => \context_user::instance($user->id)->id,
            'pagetypepattern' => 'my-index',
            'defaultregion' => 'side-pre',
        ]);

        $result = get_dashboard::execute(false);

        $this->assertContains('comments', array_column($result['blocks'], 'name'));
    }
}
