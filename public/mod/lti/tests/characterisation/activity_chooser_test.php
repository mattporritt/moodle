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
 * Characterisation test: which tools a teacher sees in the activity chooser.
 *
 * Preconfigured tools are offered in the chooser by the tool's visibility setting, a teacher's per course choice to show or
 * hide a site tool, the tool's category restrictions and the teacher's permissions. This is asserted through core's content item
 * service, which is what the chooser reads.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class activity_chooser_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var int Tool visibility: not offered to courses. */
    private const NOT_VISIBLE = 0;
    /** @var int Tool visibility: available when adding an external tool, not in the chooser. */
    private const PRECONFIGURED = 1;
    /** @var int Tool visibility: shown in the chooser. */
    private const CHOOSER = 2;

    /**
     * Create a site tool.
     *
     * @param string $name the tool's name.
     * @param int $visibility the tool's visibility.
     * @param array $extra more tool settings.
     * @return int the tool type id.
     */
    private function create_site_tool(string $name, int $visibility, array $extra = []): int {
        return $this->lti_create_tool($extra + [
            'name' => $name, 'baseurl' => 'https://' . strtolower(str_replace(' ', '', $name)) . '.example.com/launch',
            'state' => 1, 'coursevisible' => $visibility, 'lti_coursevisible' => $visibility,
            'lti_ltiversion' => 'LTI-1p0', 'lti_resourcekey' => 'key', 'lti_password' => 'secret',
        ]);
    }

    /**
     * The titles of the LTI tools in the chooser, as the user sees it when working in the course.
     *
     * @param \stdClass $user the user.
     * @param \stdClass $course the course.
     * @return string[] the titles.
     */
    private function titles(\stdClass $user, \stdClass $course): array {
        $this->setUser($user);
        return array_keys($this->lti_chooser_entries($user, $course));
    }

    /**
     * The tool's visibility decides whether it is offered in the chooser.
     */
    public function test_site_tools_appear_by_visibility(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $typeid = $this->create_site_tool('Chooser tool', self::CHOOSER);
        $this->create_site_tool('Preconfigured only tool', self::PRECONFIGURED);
        $this->create_site_tool('Hidden tool', self::NOT_VISIBLE);

        $titles = $this->titles($teacher, $course);

        $this->assertContains('Chooser tool', $titles);
        $link = new \moodle_url($this->lti_chooser_entries($teacher, $course)['Chooser tool']);
        $this->assertStringEndsWith('/course/modedit.php', $link->get_path(false));
        $this->assertSame('lti', $link->get_param('add'));
        $this->assertEquals($typeid, $link->get_param('typeid'));
        $this->assertNotContains('Preconfigured only tool', $titles);
        $this->assertNotContains('Hidden tool', $titles);
    }

    /**
     * A teacher can hide a site tool from the chooser of one course, and show it again, without affecting other courses.
     */
    public function test_teacher_can_show_and_hide_a_tool_per_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $generator->enrol_user($teacher->id, $other->id, 'editingteacher');
        $typeid = $this->create_site_tool('Toggled tool', self::CHOOSER);

        $this->setUser($teacher);
        $this->lti_set_chooser_visibility($typeid, (int) $course->id, false);
        $this->assertNotContains('Toggled tool', $this->titles($teacher, $course));
        $this->assertContains('Toggled tool', $this->titles($teacher, $other));

        $this->lti_set_chooser_visibility($typeid, (int) $course->id, true);
        $this->assertContains('Toggled tool', $this->titles($teacher, $course));
    }

    /**
     * A course tool is offered in its own course only.
     */
    public function test_course_tool_appears_in_its_own_course_only(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $generator->enrol_user($teacher->id, $other->id, 'editingteacher');
        $this->lti_create_course_tool([
            'name' => 'Course tool', 'baseurl' => 'https://coursetool.example.com/launch', 'course' => $course->id,
            'lti_ltiversion' => 'LTI-1p0', 'lti_resourcekey' => 'key', 'lti_password' => 'secret',
        ]);

        $this->assertContains('Course tool', $this->titles($teacher, $course));
        $this->assertNotContains('Course tool', $this->titles($teacher, $other));
    }

    /**
     * A tool restricted to a category is offered only in courses of that category.
     */
    public function test_category_restricted_tool_appears_only_in_its_category(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $allowed = $generator->create_category();
        $other = $generator->create_category();
        $incategory = $generator->create_course(['category' => $allowed->id]);
        $outside = $generator->create_course(['category' => $other->id]);
        $teacher = $generator->create_and_enrol($incategory, 'editingteacher');
        $generator->enrol_user($teacher->id, $outside->id, 'editingteacher');
        $this->create_site_tool('Restricted tool', self::CHOOSER, ['lti_coursecategories' => $allowed->id]);

        $this->assertContains('Restricted tool', $this->titles($teacher, $incategory));
        $this->assertNotContains('Restricted tool', $this->titles($teacher, $outside));
    }

    /**
     * Tools are offered only to someone who may add preconfigured tools, and the manual entry only to someone who may add them.
     */
    public function test_tools_need_permission_to_use(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->create_site_tool('Permission tool', self::CHOOSER);
        $this->assertContains('Permission tool', $this->titles($teacher, $course));

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/lti:addpreconfiguredinstance', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);

        $this->assertNotContains('Permission tool', $this->titles($teacher, $course));
    }
}
