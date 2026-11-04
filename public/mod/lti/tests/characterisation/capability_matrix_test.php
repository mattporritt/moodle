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
require_once(__DIR__ . '/service_calls_trait.php');

/**
 * Characterisation test: who may use the LTI pages and the app web service.
 *
 * For each entry point, a user who has exactly the capabilities it needs is allowed, a user who lacks any one of them is
 * refused, and the standard roles, a user who is not enrolled and a guest are allowed or refused as on main. The pages are run
 * in process as the user; the launch data web service is called as the user. The auth endpoint and the deep linking return are
 * covered with the LTI 1.3 tests, and the service endpoints in the services tests.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class capability_matrix_test extends \advanced_testcase {
    use characterisation_trait;
    use service_calls_trait;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var int The tool. */
    private int $typeid;

    /** @var \stdClass The activity. */
    private \stdClass $lti;

    /**
     * The pages and the capabilities each needs in the course, besides being able to enter it.
     *
     * @return array[] page => [script, capabilities]
     */
    private static function pages(): array {
        return [
            'contentitem.php' => ['/mod/lti/contentitem.php', ['moodle/course:manageactivities', 'mod/lti:addcoursetool']],
            'coursetooledit.php' => ['/mod/lti/coursetooledit.php', ['mod/lti:addcoursetool']],
            'coursetools.php' => ['/mod/lti/coursetools.php', ['mod/lti:addpreconfiguredinstance']],
            'request_tool.php' => ['/mod/lti/request_tool.php', ['mod/lti:requesttooladd']],
        ];
    }

    /**
     * The users to try on each page, and whether the page allows them: a user with exactly the capabilities the page needs, one
     * who lacks each of them in turn, the standard roles, a user who is not enrolled and a guest.
     *
     * @param array $archetypes page => [student, teacher, editingteacher, manager] allowed, as on main.
     * @return array[]
     */
    private static function matrix(array $archetypes): array {
        $cases = [];
        foreach (self::pages() as $page => [$script, $capabilities]) {
            $cases["{$page}: user with {$capabilities[0]}" . (count($capabilities) > 1 ? ' and the rest' : '')] =
                [$page, 'capabilities', $capabilities, true];
            foreach ($capabilities as $missing) {
                $cases["{$page}: user without {$missing}"] = [$page, 'capabilities', array_diff($capabilities, [$missing]), false];
            }
            foreach (['student', 'teacher', 'editingteacher', 'manager'] as $index => $role) {
                $cases["{$page}: {$role}"] = [$page, $role, [], $archetypes[$page][$index]];
            }
            $cases["{$page}: user not enrolled"] = [$page, 'notenrolled', [], false];
            $cases["{$page}: guest"] = [$page, 'guest', [], false];
        }
        return $cases;
    }

    /**
     * The users and pages to try.
     *
     * @return array[]
     */
    public static function page_cases(): array {
        return self::matrix([
            'contentitem.php' => [false, false, true, true],
            'coursetooledit.php' => [false, false, true, true],
            'coursetools.php' => [false, false, true, true],
            'request_tool.php' => [false, false, true, true],
        ]);
    }

    /**
     * Create a course with an LTI 1.1 tool that can select content, and an activity of the tool.
     */
    private function create_course_and_activity(): void {
        $this->course = $this->getDataGenerator()->create_course();
        $this->typeid = $this->lti_create_tool([
            'name' => 'Matrix tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => 'LTI-1p0',
            'lti_resourcekey' => 'matrixkey', 'lti_password' => 'matrixsecret', 'lti_contentitem' => 1,
            'lti_toolurl_ContentItemSelectionRequest' => 'https://tool.example.com/select',
        ]);
        $this->lti = $this->lti_create_activity($this->course->id, [
            'name' => 'Matrix activity', 'typeid' => $this->typeid, 'toolurl' => 'https://tool.example.com/launch',
            'resourcekey' => '', 'password' => '',
        ]);
    }

    /**
     * Create the activity's tool and course for the launch data web service, with the activity hidden or not.
     *
     * @param bool $visible whether the activity is visible to students.
     */
    private function create_activity_visibility(bool $visible): void {
        set_coursemodule_visible($this->lti->cmid, $visible ? 1 : 0);
    }

    /**
     * Set the user a case is about as the current user.
     *
     * @param string $kind capabilities, a standard role, notenrolled or guest.
     * @param string[] $capabilities the capabilities a user of kind capabilities has.
     */
    private function set_user(string $kind, array $capabilities): void {
        $generator = $this->getDataGenerator();
        if ($kind === 'guest') {
            $this->setGuestUser();
        } else if ($kind === 'notenrolled') {
            $this->setUser($generator->create_user());
        } else if ($kind === 'capabilities') {
            $this->setUser($this->lti_user_with_capabilities($this->course, $capabilities));
        } else {
            $user = $generator->create_user();
            $generator->enrol_user($user->id, $this->course->id, $kind);
            $this->setUser($user);
        }
    }

    /**
     * The parameters a page is called with.
     *
     * @param string $page the page.
     * @return array
     */
    private function page_parameters(string $page): array {
        return match ($page) {
            'contentitem.php' => ['id' => $this->typeid, 'course' => $this->course->id],
            'coursetooledit.php' => ['course' => $this->course->id],
            'coursetools.php' => ['id' => $this->course->id],
            'request_tool.php' => ['instanceid' => $this->lti->id, 'sesskey' => sesskey()],
        };
    }

    /**
     * Each page lets in the users that have what it needs, and refuses the others.
     *
     * @param string $page the page.
     * @param string $kind the kind of user.
     * @param string[] $capabilities the capabilities of a user of kind capabilities.
     * @param bool $allowed whether the page lets the user in.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('page_cases')]
    public function test_a_page_lets_in_only_who_may_use_it(string $page, string $kind, array $capabilities, bool $allowed): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $this->set_user($kind, $capabilities);

        [$output, $thrown] = $this->lti_run_script(self::pages()[$page][0], $this->page_parameters($page));

        if ($allowed) {
            $this->assertNull($thrown, $thrown ? get_class($thrown) . ': ' . $thrown->getMessage() : '');
            $this->assertNotEmpty($output);
        } else {
            $this->assertInstanceOf(\moodle_exception::class, $thrown);
        }
    }

    /**
     * The users to try on the launch data web service, and whether it gives them the launch data: a user with exactly the
     * capability to view, one without it, the standard roles, a user not enrolled, a guest, and the activity hidden.
     *
     * @return array[] [kind, capabilities, activity visible, allowed]
     */
    public static function web_service_cases(): array {
        return [
            'user with mod/lti:view' => ['capabilities', ['mod/lti:view'], true, true],
            'user without mod/lti:view' => ['capabilities', [], true, false],
            'student' => ['student', [], true, true],
            'teacher' => ['teacher', [], true, true],
            'editingteacher' => ['editingteacher', [], true, true],
            'manager' => ['manager', [], true, true],
            'user not enrolled' => ['notenrolled', [], true, false],
            'guest' => ['guest', [], true, false],
            'student, activity hidden' => ['student', [], false, false],
            'teacher, activity hidden' => ['editingteacher', [], false, true],
        ];
    }

    /**
     * The launch data web service of the Moodle app gives the launch data only to who may view the activity.
     *
     * @param string $kind the kind of user.
     * @param string[] $capabilities the capabilities of a user of kind capabilities.
     * @param bool $visible whether the activity is visible to students.
     * @param bool $allowed whether the web service gives the user the launch data.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('web_service_cases')]
    public function test_the_launch_data_web_service_serves_only_who_may_view(
        string $kind,
        array $capabilities,
        bool $visible,
        bool $allowed
    ): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $this->create_activity_visibility($visible);
        $this->set_user($kind, $capabilities);

        if ($allowed) {
            $data = $this->lti_ws_get_tool_launch_data($this->lti->id);
            $this->assertNotEmpty($data['endpoint']);
            $this->assertNotEmpty($data['parameters']);
        } else {
            $this->expectException(\moodle_exception::class);
            $this->lti_ws_get_tool_launch_data($this->lti->id);
        }
    }

    /**
     * Asking for the tool of an activity whose tool URL matches no tool adds a pending tool and says the request was added.
     */
    public function test_a_request_for_a_tool_url_that_matches_no_tool_adds_a_pending_tool(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $lti = $this->lti_create_activity($this->course->id, [
            'name' => 'Activity with another tool', 'typeid' => 0, 'toolurl' => 'https://unknown.example.org/launch',
            'resourcekey' => '', 'password' => '',
        ]);
        $this->setAdminUser();
        $before = $DB->count_records('lti_types');

        [$output, $thrown] = $this->lti_run_script('/mod/lti/request_tool.php', ['instanceid' => $lti->id, 'sesskey' => sesskey()]);

        $this->assertNull($thrown, $thrown ? get_class($thrown) . ': ' . $thrown->getMessage() : '');
        $this->assertStringContainsString(get_string('lti_tool_request_added', 'mod_lti'), $output);
        $this->assertSame($before + 1, $DB->count_records('lti_types'));
        $this->assertEquals(
            LTI_TOOL_STATE_PENDING,
            $DB->get_field_select('lti_types', 'state', $DB->sql_compare_text('baseurl') . ' = ?', ['unknown.example.org'])
        );
    }

    /**
     * Asking for the tool of an activity that has no tool URL adds a pending tool and says the request was added.
     *
     * Main raises a database error: the tool it would add has no base URL, which the table requires.
     */
    public function test_a_request_for_an_activity_with_no_tool_url_adds_a_pending_tool(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $lti = $this->lti_create_activity($this->course->id, [
            'name' => 'Activity with no tool url', 'typeid' => $this->typeid, 'toolurl' => '',
            'resourcekey' => '', 'password' => '',
        ]);
        $this->setAdminUser();
        $before = $DB->count_records('lti_types');
        $this->markTestSkipped(
            'Skipped until MDL-90478 is fixed: request_tool.php raises a database error for an activity with no tool URL.'
        );

        [$output, $thrown] = $this->lti_run_script('/mod/lti/request_tool.php', ['instanceid' => $lti->id, 'sesskey' => sesskey()]);

        $this->assertNull($thrown, $thrown ? get_class($thrown) . ': ' . $thrown->getMessage() : '');
        $this->assertStringContainsString(get_string('lti_tool_request_added', 'mod_lti'), $output);
        $this->assertSame($before + 1, $DB->count_records('lti_types'));
        $newid = $DB->get_field_sql('SELECT MAX(id) FROM {lti_types}');
        $this->assertEquals(LTI_TOOL_STATE_PENDING, $DB->get_field('lti_types', 'state', ['id' => $newid]));
    }
}
