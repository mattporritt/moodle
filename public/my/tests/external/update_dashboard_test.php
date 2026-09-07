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
 * Tests for the responsive dashboard update external function.
 *
 * @package    core_my
 * @category   test
 * @covers     \core_my\external\update_dashboard
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_dashboard_test extends \advanced_testcase {
    /**
     * A user may persist the complete canonical layout while editing.
     */
    public function test_execute_saves_layout(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $this->assertNotEmpty($payload['layout']);
        $PAGE = new \moodle_page();

        $result = update_dashboard::execute('save', false, $payload['layout']);
        $result = external_api::clean_returnvalue(update_dashboard::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $position = $DB->get_record('block_positions', [
            'blockinstanceid' => $payload['layout'][0]['id'],
            'contextid' => \context_user::instance($USER->id)->id,
        ], '*', MUST_EXIST);
        $this->assertSame($payload['layout'][0]['rows'], (int) $position->gridrows);
    }

    /**
     * Unknown actions are rejected.
     */
    public function test_execute_rejects_unknown_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('unknown', false);
    }

    /**
     * Overlapping rectangles are rejected server-side.
     */
    public function test_execute_rejects_overlapping_layout(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $this->assertGreaterThanOrEqual(2, count($payload['layout']));
        $payload['layout'][1]['column'] = $payload['layout'][0]['column'];
        $payload['layout'][1]['row'] = $payload['layout'][0]['row'];
        $PAGE = new \moodle_page();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('save', false, $payload['layout']);
    }

    /**
     * A block can be added and then removed through the same external API.
     */
    public function test_execute_adds_and_removes_block(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $result = update_dashboard::execute('add', false, [], 'online_users');
        $this->assertGreaterThan(0, $result['blockid']);
        $this->assertTrue($DB->record_exists('block_instances', ['id' => $result['blockid']]));

        $PAGE = new \moodle_page();
        update_dashboard::execute('remove', false, [], '', $result['blockid']);
        $this->assertFalse($DB->record_exists('block_instances', ['id' => $result['blockid']]));
    }

    /**
     * The first block can be added to an empty dashboard.
     */
    public function test_execute_adds_first_block_to_empty_dashboard(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        foreach ($payload['blocks'] as $block) {
            \blocks_delete_instance($DB->get_record('block_instances', ['id' => $block['id']], '*', MUST_EXIST));
        }
        $PAGE = new \moodle_page();

        $result = update_dashboard::execute('add', false, [], 'online_users');

        $this->assertGreaterThan(0, $result['blockid']);
        $position = $DB->get_record('block_positions', ['blockinstanceid' => $result['blockid']], '*', MUST_EXIST);
        $this->assertSame(0, (int) $position->gridrow);
    }

    /**
     * Reset removes a customised user dashboard so it inherits the site default.
     */
    public function test_execute_resets_dashboard(): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/my/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $page = my_copy_page($USER->id, MY_PAGE_PRIVATE);
        $this->assertNotFalse($page);

        update_dashboard::execute('reset', false);

        $this->assertFalse($DB->record_exists('my_pages', ['id' => $page->id]));
    }

    /**
     * Reset is meaningless for the site default: there is nothing above it to revert to, and
     * the site-wide "reset everyone's dashboard" admin action (my/indexsys.php) is a distinct,
     * deliberately separate code path.
     */
    public function test_execute_rejects_reset_of_site_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('reset', true);
    }

    /**
     * A layout referencing a block id that isn't part of the dashboard being saved - here,
     * simply invented - is rejected rather than silently ignored or persisted.
     */
    public function test_execute_rejects_layout_with_unknown_block(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $payload['layout'][0]['id'] = max(array_column($payload['layout'], 'id')) + 1000;
        $PAGE = new \moodle_page();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('save', false, $payload['layout']);
    }

    /**
     * The same block id may not appear twice in one layout.
     */
    public function test_execute_rejects_layout_with_duplicate_block(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $this->assertGreaterThanOrEqual(2, count($payload['layout']));
        $payload['layout'][1]['id'] = $payload['layout'][0]['id'];
        $PAGE = new \moodle_page();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('save', false, $payload['layout']);
    }

    /**
     * A layout omitting a block that still exists on the dashboard is rejected: every block
     * needs a position, and this endpoint has no way to know an omission means "remove it".
     */
    public function test_execute_rejects_layout_missing_a_block(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $this->assertNotEmpty($payload['layout']);
        array_pop($payload['layout']);
        $PAGE = new \moodle_page();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('save', false, $payload['layout']);
    }

    /**
     * Each dimension of an invalid grid rectangle is rejected: negative column/row, a size
     * smaller than the minimum, and a rectangle overflowing the grid's right edge.
     *
     * @dataProvider invalid_rectangle_provider
     * @param array $overrides Fields to override on the first layout item.
     */
    public function test_execute_rejects_invalid_rectangle(array $overrides): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = 1;
        $_POST['sesskey'] = sesskey();

        $payload = get_dashboard::execute(false);
        $this->assertNotEmpty($payload['layout']);
        $payload['layout'][0] = array_merge($payload['layout'][0], $overrides);
        $PAGE = new \moodle_page();

        $this->expectException(\invalid_parameter_exception::class);
        update_dashboard::execute('save', false, $payload['layout']);
    }

    /**
     * Data provider for test_execute_rejects_invalid_rectangle.
     *
     * @return array
     */
    public static function invalid_rectangle_provider(): array {
        return [
            'negative column' => [['column' => -1]],
            'negative row' => [['row' => -1]],
            'too few columns' => [['columns' => 0]],
            'too few rows' => [['rows' => 0]],
            'overflows the grid' => [['column' => dashboard::MAX_COLUMNS - 1, 'columns' => 2]],
        ];
    }

    /**
     * Adding a block type that either does not exist or is not addable (e.g. already added and
     * not addable more than once) is rejected rather than silently creating nothing.
     */
    public function test_execute_rejects_adding_an_unaddable_block_type(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\moodle_exception::class);
        update_dashboard::execute('add', false, [], 'thisblockdoesnotexist');
    }

    /**
     * Removing a block id that does not exist on the dashboard - or at all - is rejected the
     * same way removing a block the user has no permission over would be.
     */
    public function test_execute_rejects_removing_an_unknown_block(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\moodle_exception::class);
        update_dashboard::execute('remove', false, [], '', 999999);
    }
}
