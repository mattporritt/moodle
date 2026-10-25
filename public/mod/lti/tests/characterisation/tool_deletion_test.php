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

namespace mod_lti\characterisation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/characterisation_trait.php');

/**
 * Characterisation test: deleting a tool leaves no data of its own behind.
 *
 * The test finds the LTI tables that point at a tool (a typeid or toolid column) from the database itself, so that a table added
 * later, such as one for placements, is checked without this test being edited. After a tool is deleted, no such table may still
 * hold rows for it, except the tables that already did on Moodle main before the migration. Those are listed in
 * KNOWN_LEFTOVERS. They are defects to be fixed, not behaviour to keep: removing a table from the list is welcome, adding one is
 * a regression.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class tool_deletion_test extends \advanced_testcase {
    use characterisation_trait;

    /**
     * Tables that keep rows for a deleted tool on Moodle main.
     *
     * The activity table is here by design: activities of a deleted tool stay in their courses.
     */
    private const KNOWN_LEFTOVERS = [
        'lti', 'lti_coursevisible', 'lti_access_tokens', 'lti_tool_settings',
    ];

    /**
     * Tables that may hold rows for a deleted tool: the known leftovers, and the table of the line items.
     *
     * @return string[]
     */
    private function expected_leftovers(): array {
        return array_merge(self::KNOWN_LEFTOVERS, [$this->lti_gradebookservices_table()]);
    }

    /**
     * Rows that were not in the database before the tool was created, per table that points at a tool.
     *
     * @param int $typeid the tool.
     * @return int[] table name => number of rows for the tool.
     */
    private function rows_for_tool(int $typeid): array {
        global $DB;
        $rows = [];
        foreach ($DB->get_tables(false) as $table) {
            if (!preg_match('/^lti/', $table)) {
                continue;
            }
            // The tool's own table is keyed by id, the tables that point at it by typeid or toolid.
            $columns = $DB->get_columns($table);
            foreach (['id' => 'lti_types', 'typeid' => null, 'toolid' => null] as $column => $only) {
                if (isset($columns[$column]) && ($only === null || $only === $table)) {
                    $count = $DB->count_records($table, [$column => $typeid]);
                    if ($count) {
                        $rows[$table] = ($rows[$table] ?? 0) + $count;
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Give the tool the data that using it creates: a course level visibility override and an access token for a service call.
     *
     * The tables are legacy tables, so this does nothing where they no longer exist.
     *
     * @param int $typeid the tool.
     * @param int $courseid the course.
     */
    private function add_legacy_tool_data(int $typeid, int $courseid): void {
        global $DB;
        $manager = $DB->get_manager();
        if ($manager->table_exists('lti_coursevisible')) {
            $DB->insert_record('lti_coursevisible', ['typeid' => $typeid, 'courseid' => $courseid, 'coursevisible' => 1]);
        }
        if ($manager->table_exists('lti_access_tokens')) {
            $DB->insert_record('lti_access_tokens', [
                'typeid' => $typeid, 'scope' => '[]', 'token' => bin2hex(random_bytes(16)),
                'validuntil' => time() + HOURSECS, 'timecreated' => time(),
            ]);
        }
    }

    /**
     * Deleting a tool removes its type, configuration and category restrictions, and nothing new is left behind.
     */
    public function test_deleting_a_tool_leaves_no_orphan_data(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        [$course, $category] = $this->lti_course_in_category();

        $typeid = $this->lti_create_tool([
            'name' => 'Doomed tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1,
            'lti_ltiversion' => '1.3.0', 'lti_clientid' => 'doomed-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://tool.example.com/jwks', 'lti_coursecategories' => $category->id,
        ]);
        $keeper = $this->lti_create_tool([
            'name' => 'Surviving tool', 'baseurl' => 'https://other.example.com/launch', 'state' => 1,
            'lti_ltiversion' => 'LTI-1p0', 'lti_resourcekey' => 'key', 'lti_password' => 'secret',
        ]);
        $lti = $this->lti_create_activity($course->id, ['typeid' => $typeid, 'resourcekey' => '', 'password' => '']);
        $this->add_legacy_tool_data($typeid, (int) $course->id);

        $before = $this->rows_for_tool($typeid);
        $this->assertArrayHasKey('lti_types', $before);
        $this->assertArrayHasKey('lti_types_config', $before);
        $this->assertArrayHasKey('lti_types_categories', $before, 'The tool should be restricted to its category');
        $keeperbefore = $this->rows_for_tool($keeper);

        $this->lti_delete_tool($typeid);

        $this->assertFalse($DB->record_exists('lti_types', ['id' => $typeid]));
        $leftovers = array_diff(array_keys($this->rows_for_tool($typeid)), $this->expected_leftovers());
        $this->assertSame([], array_values($leftovers), 'Deleting the tool left data in: ' . implode(', ', $leftovers));

        // The activity is kept, the other tool is untouched.
        $this->assertTrue($DB->record_exists('lti', ['id' => $lti->id]));
        $this->assertSame($keeperbefore, $this->rows_for_tool($keeper));
    }
}
