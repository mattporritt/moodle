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
use core_ltix\helper;

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
     * The button is on the form of a tool saved with deep linking through the tool form, and only then, as for the tool form
     * options.
     */
    public function test_select_content_button_follows_the_deep_linking_option_of_the_tool_form(): void {
        $type = (object) ['state' => constants::LTI_TOOL_STATE_CONFIGURED, 'name' => 'Tool', 'baseurl' => 'http://example.com'];
        $typeid = helper::add_type($type, (object) [
            'lti_coursevisible' => constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            'lti_launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
            'lti_contentitem' => 1,
            'lti_toolurl_ContentItemSelectionRequest' => 'http://example.com/select',
        ]);
        $this->assertTrue($this->has_select_content_button($typeid));

        // The same when there is no content selection URL, as the tool URL is used then.
        helper::update_type((object) ['id' => $typeid], (object) [
            'lti_contentitem' => 1,
            'lti_toolurl_ContentItemSelectionRequest' => '',
        ]);
        $this->assertTrue($this->has_select_content_button($typeid));

        // Off.
        helper::update_type((object) ['id' => $typeid], (object) ['lti_contentitem' => 0]);
        $this->assertFalse($this->has_select_content_button($typeid));

        // Off with a content selection URL still set, which the tool form keeps.
        helper::update_type((object) ['id' => $typeid], (object) [
            'lti_contentitem' => 0,
            'lti_toolurl_ContentItemSelectionRequest' => 'http://example.com/select',
        ]);
        $this->assertFalse($this->has_select_content_button($typeid));

        // On again.
        helper::update_type((object) ['id' => $typeid], (object) ['lti_contentitem' => 1]);
        $this->assertTrue($this->has_select_content_button($typeid));

        // A tool that was never given deep linking.
        $plainid = helper::add_type((object) ['state' => constants::LTI_TOOL_STATE_CONFIGURED, 'name' => 'Plain',
            'baseurl' => 'http://example.com/plain'], (object) ['lti_coursevisible' => constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            'lti_launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT]);
        $this->assertFalse($this->has_select_content_button($plainid));
    }

    /**
     * Placement config that has only the deep linking URL, as written before the flag, still shows the button.
     */
    public function test_select_content_button_with_only_a_deep_linking_url(): void {
        global $DB;

        $typeid = helper::add_type((object) ['state' => constants::LTI_TOOL_STATE_CONFIGURED, 'name' => 'Tool',
            'baseurl' => 'http://example.com'], (object) ['lti_coursevisible' => constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            'lti_launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT]);
        $this->assertFalse($this->has_select_content_button($typeid));

        $sql = "SELECT p.id
                  FROM {lti_placement} p
                  JOIN {lti_placement_type} pt ON pt.id = p.placementtypeid
                 WHERE p.toolid = :toolid AND pt.type = :type";
        $placementid = $DB->get_field_sql($sql, ['toolid' => $typeid, 'type' => 'mod_lti:activityplacement']);
        helper::insert_or_update_placement_config((object) [
            'placementid' => $placementid,
            'name' => 'deep_linking_url',
            'value' => 'http://example.com/select',
        ]);
        $this->assertTrue($this->has_select_content_button($typeid));
    }

    /**
     * Whether the form for adding an activity of a tool has the Select content button.
     *
     * @param int $typeid The tool ID.
     * @return bool
     */
    private function has_select_content_button(int $typeid): bool {
        global $PAGE;

        $_GET['typeid'] = $typeid;
        $current = (object) ['typeid' => $typeid, 'course' => $this->course->id, 'section' => 0, 'add' => 'lti', 'return' => 0,
            'update' => 0, 'coursemodule' => 0, 'instance' => 0];
        $form = new \mod_lti_mod_form($current, 0, null, $this->course);
        unset($_GET['typeid']);

        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);

        return $mform->elementExists('selectcontentgroup');
    }
}
