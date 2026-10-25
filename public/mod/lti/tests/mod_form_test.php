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

namespace mod_lti;

use core_ltix\constants;

/**
 * Tests for the Select content button of the activity form.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lti_mod_form
 */
final class mod_form_test extends \advanced_testcase {
    /** @var \stdClass The course that the activity forms are for. */
    private \stdClass $course;

    /**
     * Prepares things before this test case is initialised.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/mod_form.php');
        require_once($CFG->dirroot . '/mod/lti/db/upgradelib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Sets up the page for the activity form, once, as the theme cannot change after the first form.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($this->course);
        $PAGE->set_url('/course/modedit.php', ['add' => 'lti', 'course' => $this->course->id]);
    }

    /**
     * A tool upgraded with deep linking on and no content selection URL still has the button, as on main.
     *
     * The upgrade stores no URL when the URL is empty, so the flag has to decide.
     */
    public function test_select_content_button_of_an_upgraded_tool_without_a_deep_linking_url(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_lti');
        $withflag = $generator->create_tool_types([
            'name' => 'Deep linking, no URL', 'baseurl' => 'https://example.com/launch',
            'coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED, 'lti_contentitem' => 1,
        ]);
        $withurl = $generator->create_tool_types([
            'name' => 'Deep linking, URL', 'baseurl' => 'https://example.com/launch2',
            'coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED, 'lti_contentitem' => 1,
            'lti_toolurl_ContentItemSelectionRequest' => 'https://example.com/select',
        ]);
        $without = $generator->create_tool_types([
            'name' => 'No deep linking', 'baseurl' => 'https://example.com/launch3',
            'coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED, 'lti_contentitem' => 0,
        ]);

        (new lti_migration_upgrade_helper())->create_default_placements();

        // The upgrade stored the flag for the first tool, and no URL.
        $placementconfig = \core_ltix\local\placement\placement_repository::get_placement_config_by_placement_type(
            $withflag,
            'mod_lti:activityplacement'
        );
        $this->assertEquals(1, $placementconfig->supports_deep_linking);
        $this->assertFalse(isset($placementconfig->deep_linking_url));

        $this->assertTrue($this->has_select_content_button($withflag));
        $this->assertTrue($this->has_select_content_button($withurl));
        $this->assertFalse($this->has_select_content_button($without));
    }

    /**
     * The flag decides when it is stored, and the URL when it is not, for placement config written without the flag.
     */
    public function test_select_content_button_follows_the_flag_before_the_url(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_ltix');
        $placementtypeid = $this->get_activity_placement_type_id();
        $typeid = $generator->create_tool_types([
            'name' => 'Tool', 'baseurl' => 'https://example.com/launch', 'state' => constants::LTI_TOOL_STATE_CONFIGURED,
            'coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);
        $placement = $generator->create_tool_placements([
            'toolid' => $typeid, 'placementtypeid' => $placementtypeid, 'config_deep_linking_url' => 'https://example.com/select',
        ]);
        // Only a URL, as written before the flag existed.
        $this->assertTrue($this->has_select_content_button($typeid));

        // The flag is off, so the URL does not matter.
        \core_ltix\local\placement\placement_repository::insert_or_update_placement_config((object) [
            'placementid' => $placement->id, 'name' => 'supports_deep_linking', 'value' => 0,
        ]);
        $this->assertFalse($this->has_select_content_button($typeid));

        // The flag is on.
        \core_ltix\local\placement\placement_repository::insert_or_update_placement_config((object) [
            'placementid' => $placement->id, 'name' => 'supports_deep_linking', 'value' => 1,
        ]);
        $this->assertTrue($this->has_select_content_button($typeid));
    }

    /**
     * The id of the activity placement type.
     *
     * @return int
     */
    private function get_activity_placement_type_id(): int {
        global $DB;
        return (int) $DB->get_field('lti_placement_type', 'id', ['type' => 'mod_lti:activityplacement'], MUST_EXIST);
    }

    /**
     * Whether the form for adding an activity of a tool has the Select content button.
     *
     * @param int $typeid The tool ID.
     * @return bool
     */
    private function has_select_content_button(int $typeid): bool {
        $_GET['typeid'] = $typeid;
        $current = (object) ['typeid' => $typeid, 'course' => $this->course->id, 'section' => 0, 'add' => 'lti', 'return' => 0,
            'update' => 0, 'coursemodule' => 0, 'instance' => 0];
        $form = new \mod_lti_mod_form($current, 0, null, $this->course);
        unset($_GET['typeid']);

        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);

        return $mform->elementExists('selectcontentgroup');
    }
}
