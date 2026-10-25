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
require_once($CFG->libdir . '/gradelib.php');

/**
 * Characterisation test: what the LTI services answer to a tool.
 *
 * A tool calls the services with a bearer token. These tests make the calls in process, through the services' own resource
 * classes, and hold the answers to recorded responses: the names and roles service (NRPS) with and without personal data, the
 * assignment and grade service (AGS) line items, and a score posted by the tool arriving in the gradebook.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class services_endpoint_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var string The NRPS scope. */
    private const NRPS_SCOPE = 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly';
    /** @var string The AGS scope to read line items. */
    private const AGS_LINEITEM_READ = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly';
    /** @var string The AGS scope to post scores. */
    private const AGS_SCORE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';
    /** @var string The NRPS media type for a membership container. */
    private const NRPS_TYPE = 'application/vnd.ims.lti-nrps.v2.membershipcontainer+json';

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student who is graded. */
    private \stdClass $student;

    /** @var \stdClass The teacher. */
    private \stdClass $teacher;

    /** @var \stdClass The graded activity. */
    private \stdClass $lti;

    /**
     * Create a course with a teacher and a student, and a graded activity of a tool that has the services switched on.
     *
     * @param array $toolsettings tool settings to add or change.
     * @return int the tool type id.
     */
    private function create_course_and_tool(array $toolsettings = []): int {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['shortname' => 'C1', 'fullname' => 'Course One']);
        $this->student = $generator->create_user([
            'username' => 'stu1', 'firstname' => 'Sam', 'lastname' => 'Student', 'email' => 'sam@example.com', 'idnumber' => 'U1',
        ]);
        $this->teacher = $generator->create_user([
            'username' => 'tea1', 'firstname' => 'Tia', 'lastname' => 'Teacher', 'email' => 'tia@example.com', 'idnumber' => 'U2',
        ]);
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        $typeid = $this->lti_create_tool($toolsettings + [
            'name' => 'Service tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => '1.3.0',
            'lti_clientid' => 'service-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://tool.example.com/jwks', 'lti_sendname' => 1, 'lti_sendemailaddr' => 1,
            'lti_acceptgrades' => 1,
        ] + $this->lti_service_setting('memberships', 1) + $this->lti_service_setting('gradesynchronization', 2));
        $this->lti = $this->lti_create_activity($this->course->id, [
            'name' => 'Graded activity', 'typeid' => $typeid, 'instructorchoiceacceptgrades' => 1, 'grade' => 100,
            'resourcekey' => '', 'password' => '', 'lineitemresourceid' => 'resource-1', 'lineitemtag' => 'tag-1',
        ]);
        return $typeid;
    }

    /**
     * The placeholders of the fixtures.
     *
     * @param int $typeid the tool.
     * @return array
     */
    private function tokens(int $typeid): array {
        global $CFG, $DB;
        $gradeitem = $DB->get_field($this->lti_gradebookservices_table(), 'gradeitemid', ['ltilinkid' => $this->lti->id]);
        return [
            '{{wwwroot}}' => $CFG->wwwroot, '{{course}}' => $this->course->id, '{{type}}' => $typeid,
            '{{student}}' => $this->student->id, '{{teacher}}' => $this->teacher->id, '{{instance}}' => $this->lti->id,
            '{{gradeitem}}' => $gradeitem,
        ];
    }

    /**
     * Call the NRPS context memberships endpoint.
     *
     * @param int $typeid the tool.
     * @param string[] $scopes the scopes of the token.
     * @param array $query query parameters, such as a role filter.
     * @return \mod_lti\local\ltiservice\response
     */
    private function call_nrps(int $typeid, array $scopes = [self::NRPS_SCOPE], array $query = []) {
        return $this->lti_service_call(
            \ltiservice_memberships\local\resources\contextmemberships::class,
            \ltiservice_memberships\local\service\memberships::class,
            $typeid,
            'GET',
            "/CourseSection/{$this->course->id}/bindings/{$typeid}/memberships",
            $scopes,
            self::NRPS_TYPE,
            '',
            $query
        );
    }

    /**
     * The names and roles service lists the members of the course with their roles and the personal data the tool may have.
     */
    public function test_nrps_lists_members_with_personal_data(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();

        $response = $this->call_nrps($typeid);

        $this->assertSame(200, $response->get_code());
        $this->lti_assert_matches_fixture(
            'services/nrps_members.json',
            json_decode($response->get_body(), true),
            $this->tokens($typeid)
        );
    }

    /**
     * The names and roles service withholds names and email addresses when the tool's privacy settings say so.
     */
    public function test_nrps_withholds_personal_data_by_the_privacy_settings(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool(['lti_sendname' => 0, 'lti_sendemailaddr' => 0]);

        $response = $this->call_nrps($typeid);

        $this->assertSame(200, $response->get_code());
        $this->lti_assert_matches_fixture(
            'services/nrps_members_private.json',
            json_decode($response->get_body(), true),
            $this->tokens($typeid)
        );
    }

    /**
     * The privacy settings for names and for email addresses are applied separately.
     */
    public function test_nrps_applies_name_and_email_privacy_separately(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();

        // The generator gives the email setting the value of the name setting, so set them apart afterwards.
        $this->set_privacy_settings($typeid, sendname: 0, sendemailaddr: 1);
        $member = json_decode($this->call_nrps($typeid)->get_body(), true)['members'][0];
        $this->assertSame('sam@example.com', $member['email']);
        $this->assertArrayNotHasKey('name', $member);
        $this->assertArrayNotHasKey('given_name', $member);
        $this->assertArrayNotHasKey('family_name', $member);

        $this->set_privacy_settings($typeid, sendname: 1, sendemailaddr: 0);
        $member = json_decode($this->call_nrps($typeid)->get_body(), true)['members'][0];
        $this->assertSame('Sam Student', $member['name']);
        $this->assertArrayNotHasKey('email', $member);
    }

    /**
     * Change the privacy settings of a tool.
     *
     * @param int $typeid the tool.
     * @param int $sendname whether to send names.
     * @param int $sendemailaddr whether to send email addresses.
     */
    private function set_privacy_settings(int $typeid, int $sendname, int $sendemailaddr): void {
        global $DB;
        $DB->set_field('lti_types_config', 'value', $sendname, ['typeid' => $typeid, 'name' => 'sendname']);
        $DB->set_field('lti_types_config', 'value', $sendemailaddr, ['typeid' => $typeid, 'name' => 'sendemailaddr']);
    }

    /**
     * The names and roles service can be limited to learners.
     */
    public function test_nrps_can_be_limited_to_a_role(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();

        $response = $this->call_nrps($typeid, [self::NRPS_SCOPE], ['role' => 'Learner']);

        $members = json_decode($response->get_body(), true)['members'];
        $this->assertSame([(string) $this->student->id], array_column($members, 'user_id'));
    }

    /**
     * A token without the scope, or for a tool without the service, is refused.
     */
    public function test_nrps_refuses_a_token_without_the_scope(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();

        $response = $this->call_nrps($typeid, [self::AGS_LINEITEM_READ]);

        $this->assertSame(401, $response->get_code());
        $this->assertEmpty($response->get_body());
    }

    /**
     * The assignment and grade service lists the line items of the course.
     */
    public function test_ags_lists_line_items(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();
        $this->lti_add_standalone_line_item((int) $this->course->id, $typeid, 'Standalone item', 55, 'resource-2', 'tag-2');

        $response = $this->lti_service_call(
            \ltiservice_gradebookservices\local\resources\lineitems::class,
            \ltiservice_gradebookservices\local\service\gradebookservices::class,
            $typeid,
            'GET',
            "/{$this->course->id}/lineitems",
            [self::AGS_LINEITEM_READ],
            'application/vnd.ims.lis.v2.lineitemcontainer+json'
        );

        $this->assertSame(200, $response->get_code());
        $items = json_decode($response->get_body(), true);
        usort($items, fn($a, $b) => $a['label'] <=> $b['label']);
        $tokens = $this->tokens($typeid) + ['{{standalone}}' => $this->standalone_grade_item_id()];
        $this->lti_assert_matches_fixture('services/ags_line_items.json', $items, $tokens);
    }

    /**
     * The grade item id of the standalone line item.
     *
     * @return int
     */
    private function standalone_grade_item_id(): int {
        global $DB;
        $table = $this->lti_gradebookservices_table();
        return (int) $DB->get_field($table, 'gradeitemid', ['resourceid' => 'resource-2'], MUST_EXIST);
    }

    /**
     * A score posted by the tool arrives in the gradebook as the student's grade.
     */
    public function test_ags_score_arrives_in_the_gradebook(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();
        $gradeitem = $this->tokens($typeid)['{{gradeitem}}'];
        $this->assertEmpty($this->gradebook_grade($this->student->id));

        $score = [
            'userId' => (string) $this->student->id, 'scoreGiven' => 80, 'scoreMaximum' => 100,
            'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => date('c'),
        ];
        $response = $this->lti_service_call(
            \ltiservice_gradebookservices\local\resources\scores::class,
            \ltiservice_gradebookservices\local\service\gradebookservices::class,
            $typeid,
            'POST',
            "/{$this->course->id}/lineitems/{$gradeitem}/lineitem/scores",
            [self::AGS_SCORE],
            'application/vnd.ims.lis.v1.score+json',
            json_encode($score)
        );

        $this->assertSame(200, $response->get_code(), $response->get_reason() ?? '');
        $this->assertEquals(80, $this->gradebook_grade($this->student->id));
        $this->assertEmpty($this->gradebook_grade($this->teacher->id));
    }

    /**
     * A score for a user who is not in the course is refused and changes nothing.
     */
    public function test_ags_score_for_a_user_outside_the_course_is_refused(): void {
        $this->resetAfterTest();
        $typeid = $this->create_course_and_tool();
        $gradeitem = $this->tokens($typeid)['{{gradeitem}}'];
        $outsider = $this->getDataGenerator()->create_user();

        $score = ['userId' => (string) $outsider->id, 'scoreGiven' => 99, 'scoreMaximum' => 100,
            'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => date('c')];
        $response = $this->lti_service_call(
            \ltiservice_gradebookservices\local\resources\scores::class,
            \ltiservice_gradebookservices\local\service\gradebookservices::class,
            $typeid,
            'POST',
            "/{$this->course->id}/lineitems/{$gradeitem}/lineitem/scores",
            [self::AGS_SCORE],
            'application/vnd.ims.lis.v1.score+json',
            json_encode($score)
        );

        $this->assertNotSame(200, $response->get_code());
        $this->assertEmpty($this->gradebook_grade($outsider->id));
    }

    /**
     * The final grade the gradebook holds for a user in the activity.
     *
     * @param int $userid the user.
     * @return float|null the grade, or null if there is none.
     */
    private function gradebook_grade(int $userid): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'lti', $this->lti->id, $userid);
        $grade = $grades->items[0]->grades[$userid]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }
}
