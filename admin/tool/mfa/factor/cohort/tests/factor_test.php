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

namespace factor_cohort;

/**
 * Tests for cohort factor.
 *
 * @covers      \factor_cohort\factor
 * @package     factor_cohort
 * @copyright   2023 Stevani Andolo <stevani@hotmail.com.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factor_test extends \advanced_testcase {

    /**
     * Tests getting the summary condition
     *
     * @covers ::get_summary_condition
     * @covers ::get_cohorts
     */
    public function test_get_summary_condition() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'factor_cohort');
        $cohortfactor = \tool_mfa\plugininfo\factor::get_factor('cohort');

        // Create a cohort.
        $cohortid = $DB->insert_record('cohort', [
            'idnumber' => null,
            'name' => 'test',
            'contextid' => \context_system::instance()->id,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'visible' => 1,
            'component' => '',
            'theme' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Add member to created cohort.
        $DB->insert_record('cohort_members', [
            'cohortid'  => $cohortid,
            'userid'    => $USER->id,
            'timeadded' => time()
        ]);

        // Add the created cohortid into factor_cohort plugin.
        set_config('cohorts', $cohortid, 'factor_cohort');

        $selectedcohorts = get_config('factor_cohort', 'cohorts');
        $this->assertTrue(
            strpos(
                $cohortfactor->get_summary_condition(),
                $cohortfactor->get_cohorts($selectedcohorts)
            ) !== false
        );
    }
}
