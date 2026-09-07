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
use core_my\local\dashboard;

/**
 * Tests for the mobile-safe dashboard blocks read external function.
 *
 * @package    core_my
 * @category   test
 * @covers     \core_my\external\get_dashboard_blocks
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_dashboard_blocks_test extends \advanced_testcase {
    /**
     * Blocks are returned in real row-major grid reading order, not registration order, and
     * each block carries its real grid position instead of the legacy region/weight fields.
     */
    public function test_execute_orders_blocks_by_grid_position(): void {
        global $PAGE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Establish a layout, then save it with myoverview and timeline's grid positions swapped
        // (keeping each block's own column/row span), so the response order can only come from
        // real grid position, not layout-array order or block registration order.
        $seed = get_dashboard::execute(false);
        $seed = external_api::clean_returnvalue(get_dashboard::execute_returns(), $seed);
        $blockidsbyname = [];
        foreach ($seed['blocks'] as $block) {
            $blockidsbyname[$block['name']] = $block['id'];
        }
        $positionsbyid = [];
        foreach ($seed['layout'] as $item) {
            $positionsbyid[$item['id']] = $item;
        }

        $myoverviewid = $blockidsbyname['myoverview'];
        $timelineid = $blockidsbyname['timeline'];
        $originalmyoverviewposition = $positionsbyid[$myoverviewid];
        $originaltimelineposition = $positionsbyid[$timelineid];

        $layout = [];
        foreach ($seed['layout'] as $item) {
            if ($item['id'] === $myoverviewid) {
                $item['column'] = $originaltimelineposition['column'];
                $item['row'] = $originaltimelineposition['row'];
            } else if ($item['id'] === $timelineid) {
                $item['column'] = $originalmyoverviewposition['column'];
                $item['row'] = $originalmyoverviewposition['row'];
            }
            $layout[] = $item;
        }

        $PAGE = new \moodle_page();
        dashboard::save(false, $layout);

        $PAGE = new \moodle_page();
        $result = get_dashboard_blocks::execute();
        $result = external_api::clean_returnvalue(get_dashboard_blocks::execute_returns(), $result);

        $names = array_column($result['blocks'], 'name');
        $timelineindex = array_search('timeline', $names, true);
        $myoverviewindex = array_search('myoverview', $names, true);
        $this->assertNotFalse($timelineindex);
        $this->assertNotFalse($myoverviewindex);
        $this->assertLessThan(
            $myoverviewindex,
            $timelineindex,
            'timeline now occupies myoverview\'s old, earlier grid position, so it should be listed first.',
        );

        $timeline = $result['blocks'][$timelineindex];
        $this->assertSame($originalmyoverviewposition['column'], $timeline['column']);
        $this->assertSame($originalmyoverviewposition['row'], $timeline['row']);
        $this->assertArrayNotHasKey('region', $timeline);
        $this->assertArrayNotHasKey('weight', $timeline);
    }

    /**
     * Rendered block content (title, content, footer) is present, matching what the web grid shows.
     */
    public function test_execute_returns_rendered_block_content(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = get_dashboard_blocks::execute();
        $result = external_api::clean_returnvalue(get_dashboard_blocks::execute_returns(), $result);

        $this->assertNotEmpty($result['blocks']);
        $myoverview = current(array_filter($result['blocks'], static fn($block) => $block['name'] === 'myoverview'));
        $this->assertNotFalse($myoverview, 'The default dashboard is missing the Course overview block.');
        $this->assertNotEmpty($myoverview['contents']['content']);
    }

    /**
     * There is no userid parameter to spoof - the function always returns the logged-in user's
     * own dashboard, never another user's, matching this ticket's "a user cannot read another
     * user's dashboard layout" requirement.
     */
    public function test_execute_always_returns_the_current_user_own_dashboard(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $userblockid = dashboard::add(false, 'html');
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize((object) [
            'text' => 'A block only ' . $user->username . ' should ever see',
            'format' => FORMAT_HTML,
        ])), ['id' => $userblockid]);

        $PAGE = new \moodle_page();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->setUser($otheruser);
        $otherblockid = dashboard::add(false, 'html');
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize((object) [
            'text' => 'A block only ' . $otheruser->username . ' should ever see',
            'format' => FORMAT_HTML,
        ])), ['id' => $otherblockid]);
        $this->assertNotSame($userblockid, $otherblockid);

        $PAGE = new \moodle_page();
        $result = get_dashboard_blocks::execute();
        $result = external_api::clean_returnvalue(get_dashboard_blocks::execute_returns(), $result);
        $ids = array_column($result['blocks'], 'instanceid');

        $this->assertContains($otherblockid, $ids);
        $this->assertNotContains($userblockid, $ids);
    }
}
