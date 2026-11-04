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
 * Characterisation test: what the assignment and grade service (AGS) refuses.
 *
 * A tool calls the service for the course in the address. These tests make the calls in process with a bearer token and check
 * what is refused: a token without the scope, and a line item or link that does not exist. Where main answers wrongly, the test
 * is skipped and names the issue that switches it on.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class ags_refusals_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var string The prefix of the AGS scopes. */
    private const SCOPE = 'https://purl.imsglobal.org/spec/lti-ags/scope/';

    /** @var \stdClass The course the tool calls for. */
    private \stdClass $course;

    /** @var \stdClass The student. */
    private \stdClass $student;

    /** @var \stdClass The activity in the course. */
    private \stdClass $lti;

    /** @var int The tool. */
    private int $typeid;

    /**
     * Create a course with a student, a tool and a graded activity of the tool.
     */
    private function create_course(): void {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->typeid = $this->lti_create_tool([
            'name' => 'Grading tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1, 'lti_ltiversion' => '1.3.0',
            'lti_clientid' => 'ags-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://tool.example.com/jwks', 'lti_acceptgrades' => 1,
        ] + $this->lti_service_setting('gradesynchronization', 2));
        $record = ['typeid' => $this->typeid, 'instructorchoiceacceptgrades' => 1, 'grade' => 100, 'resourcekey' => '',
            'password' => ''];
        $this->lti = $this->lti_create_activity($this->course->id, ['name' => 'Activity'] + $record);
    }

    /**
     * The grade item of the line item of an activity.
     *
     * @param \stdClass $lti the activity.
     * @return int
     */
    private function grade_item(\stdClass $lti): int {
        global $DB;
        return (int) $DB->get_field($this->lti_gradebookservices_table(), 'gradeitemid', ['ltilinkid' => $lti->id], MUST_EXIST);
    }

    /**
     * A call to one of the AGS endpoints.
     *
     * @param string $endpoint lineitems, lineitem, results or scores.
     * @param string $method the method.
     * @param string $path the path after services.php.
     * @param string[] $scopes the scopes of the token.
     * @param string $body the request body.
     * @param array $query query parameters.
     * @return \mod_lti\local\ltiservice\response
     */
    private function call(string $endpoint, string $method, string $path, array $scopes, string $body = '', array $query = []) {
        $types = [
            'lineitems' => ['GET' => 'application/vnd.ims.lis.v2.lineitemcontainer+json',
                'POST' => 'application/vnd.ims.lis.v2.lineitem+json'],
            'lineitem' => ['GET' => 'application/vnd.ims.lis.v2.lineitem+json'],
            'results' => ['GET' => 'application/vnd.ims.lis.v2.resultcontainer+json'],
            'scores' => ['POST' => 'application/vnd.ims.lis.v1.score+json'],
        ];
        // The resources raise a deprecation when they refuse a call, which passes no message to an exception (as MDL-90293 for
        // NRPS). It is not what these tests are about.
        set_error_handler(
            fn($number, $message) => str_contains($message, 'Exception::__construct(): Passing null to parameter #1'),
            E_DEPRECATED
        );
        try {
            return $this->lti_service_call(
                "ltiservice_gradebookservices\\local\\resources\\{$endpoint}",
                \ltiservice_gradebookservices\local\service\gradebookservices::class,
                $this->typeid,
                $method,
                $path,
                $scopes,
                $types[$endpoint][$method],
                $body,
                $query
            );
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The body that creates a line item for a link.
     *
     * @param string $label the label.
     * @param int|string $link the resource link id.
     * @return string
     */
    private function new_line_item(string $label, int|string $link): string {
        return json_encode(['scoreMaximum' => 10, 'label' => $label, 'resourceLinkId' => (string) $link]);
    }

    /**
     * The body of a score for the student.
     *
     * @return string
     */
    private function score(): string {
        return json_encode(['userId' => (string) $this->student->id, 'scoreGiven' => 5, 'scoreMaximum' => 100,
            'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => date('c')]);
    }

    /**
     * The final grade the gradebook holds for the student in an activity.
     *
     * @param \stdClass $course the course.
     * @param \stdClass $lti the activity.
     * @return float|null
     */
    private function gradebook_grade(\stdClass $course, \stdClass $lti): ?float {
        $grades = grade_get_grades($course->id, 'mod', 'lti', $lti->id, $this->student->id);
        $grade = $grades->items[0]->grades[$this->student->id]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * The calls that need a scope, with the scopes that are enough: endpoint, method, path template and scopes.
     *
     * Path templates have {course} and {item}.
     *
     * @return array[]
     */
    public static function scoped_calls(): array {
        return [
            'list line items' => ['lineitems', 'GET', '/{course}/lineitems', ['lineitem.readonly', 'lineitem']],
            'create a line item' => ['lineitems', 'POST', '/{course}/lineitems', ['lineitem']],
            'read a line item' => ['lineitem', 'GET', '/{course}/lineitems/{item}/lineitem', ['lineitem.readonly', 'lineitem']],
            'read results' => ['results', 'GET', '/{course}/lineitems/{item}/lineitem/results', ['result.readonly']],
            'post a score' => ['scores', 'POST', '/{course}/lineitems/{item}/lineitem/scores', ['score']],
        ];
    }

    /**
     * A token that does not have the scope of the endpoint is refused, with nothing changed, and one that has it is not.
     *
     * @param string $endpoint the endpoint.
     * @param string $method the method.
     * @param string $path the path template.
     * @param string[] $scopes the scopes that are enough, without the prefix.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scoped_calls')]
    public function test_a_token_without_the_scope_is_refused(string $endpoint, string $method, string $path, array $scopes): void {
        $this->resetAfterTest();
        $this->create_course();
        $path = strtr($path, ['{course}' => $this->course->id, '{item}' => $this->grade_item($this->lti)]);
        $body = match ($endpoint) {
            'scores' => $this->score(),
            'lineitems' => $this->new_line_item('New', $this->lti->id),
            default => '',
        };
        $itemsbefore = count($this->lti_line_items($this->course->id));
        // The scopes of every other AGS endpoint, and of the other services, but not this one's.
        $others = array_diff(
            array_map(fn($scope) => self::SCOPE . $scope, ['lineitem', 'lineitem.readonly', 'result.readonly', 'score']),
            array_map(fn($scope) => self::SCOPE . $scope, $scopes)
        );
        $others[] = 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly';

        $refused = $this->call($endpoint, $method, $path, $others, $body);
        $this->assertSame(401, $refused->get_code());
        $this->assertEmpty($refused->get_body());
        $this->assertSame($itemsbefore, count($this->lti_line_items($this->course->id)));
        $this->assertNull($this->gradebook_grade($this->course, $this->lti));

        foreach ($scopes as $scope) {
            $allowed = $this->call($endpoint, $method, $path, [self::SCOPE . $scope], $body);
            $this->assertContains($allowed->get_code(), [200, 201], $scope);
        }
    }

    /**
     * The calls for a line item, with the scope each needs: endpoint, method, path template and body.
     *
     * @return array[]
     */
    public static function line_item_calls(): array {
        return [
            'read a line item' => ['lineitem', 'GET', '/{course}/lineitems/{item}/lineitem', 'lineitem.readonly', false],
            'read results' => ['results', 'GET', '/{course}/lineitems/{item}/lineitem/results', 'result.readonly', false],
            'post a score' => ['scores', 'POST', '/{course}/lineitems/{item}/lineitem/scores', 'score', true],
        ];
    }

    /**
     * A line item that does not exist gives a 404 and changes nothing.
     *
     * @param string $endpoint the endpoint.
     * @param string $method the method.
     * @param string $path the path template.
     * @param string $scope the scope the call needs, without the prefix.
     * @param bool $writes whether the call writes.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('line_item_calls')]
    public function test_an_unknown_line_item_gives_a_404(
        string $endpoint,
        string $method,
        string $path,
        string $scope,
        bool $writes
    ): void {
        $this->resetAfterTest();
        $this->create_course();
        $path = strtr($path, ['{course}' => $this->course->id, '{item}' => $this->grade_item($this->lti) + 100000]);

        $response = $this->call($endpoint, $method, $path, [self::SCOPE . $scope], $writes ? $this->score() : '');

        $this->assertSame(404, $response->get_code());
        $this->assertNull($this->gradebook_grade($this->course, $this->lti));
    }

    /**
     * A line item cannot be created for a link that does not exist: the call gives a 404 and no line item is added.
     */
    public function test_a_line_item_for_an_unknown_link_gives_a_404(): void {
        $this->resetAfterTest();
        $this->create_course();
        $this->markTestSkipped(
            'Skipped until MDL-90480 is fixed: main answers 403 for a link that does not exist, not 404.'
        );

        $before = $this->lti_line_items($this->course->id);
        $response = $this->call(
            'lineitems',
            'POST',
            "/{$this->course->id}/lineitems",
            [self::SCOPE . 'lineitem'],
            $this->new_line_item('For nothing', $this->lti->id + 100000)
        );

        $this->assertSame(404, $response->get_code());
        $this->assertSame($before, $this->lti_line_items($this->course->id));
    }
}
