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
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/lti/servicelib.php');

/**
 * Characterisation test: the LTI 1.1 Basic Outcomes service.
 *
 * A tool posts a signed XML message to the outcomes endpoint to replace, read or delete the grade of a user in an activity,
 * naming the result by the sourcedid it was given at launch. The endpoint script is run in process with the message as its
 * input. The tests hold the answers to a recorded response and check the gradebook.
 *
 * The script defines NO_MOODLE_COOKIES and NO_DEBUG_DISPLAY, but Moodle has already defined both by the time a test runs, so
 * those calls change nothing and no test needs its own process. The script's exception handler and the php://input stream are
 * put back after each call.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class basic_outcomes_test extends \advanced_testcase {
    use characterisation_trait;
    use service_calls_trait;

    /** @var string The consumer key of the tool. */
    private const KEY = 'outcomeskey';
    /** @var string The shared secret of the tool. */
    private const SECRET = 'outcomessecret';
    /** @var int The launch the sourcedids are for. */
    private const LAUNCH = 424242;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student whose grade is set. */
    private \stdClass $student;

    /** @var int The tool. */
    private int $typeid;

    /** @var \stdClass The graded activity. */
    private \stdClass $lti;

    /**
     * Create a course with a student, an LTI 1.1 tool that accepts grades, and a graded activity of the tool.
     */
    private function create_course_and_activity(): void {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->typeid = $this->lti_create_tool([
            'name' => 'Outcomes tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1,
            'lti_ltiversion' => 'LTI-1p0', 'lti_resourcekey' => self::KEY, 'lti_password' => self::SECRET,
            'lti_acceptgrades' => 1,
        ]);
        $this->lti = $this->create_activity('Graded activity');
    }

    /**
     * Create a graded activity of the tool.
     *
     * @param string $name the name.
     * @return \stdClass the activity.
     */
    private function create_activity(string $name): \stdClass {
        return $this->lti_create_activity($this->course->id, [
            'name' => $name, 'typeid' => $this->typeid, 'instructorchoiceacceptgrades' => 1, 'grade' => 100,
            'resourcekey' => '', 'password' => '',
        ]);
    }

    /**
     * The sourcedid of the student's result in an activity.
     *
     * @param \stdClass|null $lti the activity, the graded activity by default.
     * @return string
     */
    private function sourcedid(?\stdClass $lti = null): string {
        return $this->lti_sourcedid($lti ?? $this->lti, (int) $this->student->id, self::LAUNCH, $this->typeid);
    }

    /**
     * Post a message to the endpoint, signed as the tool signs.
     *
     * @param string $message the XML message.
     * @param string $secret the secret to sign with.
     * @return array [the answer as data, the exception the endpoint raised or null]
     */
    private function post(string $message, string $secret = self::SECRET): array {
        [$output, $thrown] = $this->lti_basic_outcomes_call($message, self::KEY, $secret);
        $answer = null;
        if ($output !== '') {
            $xml = simplexml_load_string($output);
            $status = $xml->imsx_POXHeader->imsx_POXResponseHeaderInfo->imsx_statusInfo;
            $name = $xml->imsx_POXBody->children()->getName();
            $body = $xml->imsx_POXBody->$name;
            $answer = [
                'codeMajor' => (string) $status->imsx_codeMajor,
                'severity' => (string) $status->imsx_severity,
                'description' => (string) $status->imsx_description,
                'messageRef' => (string) $status->imsx_messageRefIdentifier,
                'operationRef' => (string) $status->imsx_operationRefIdentifier,
                'body' => $name,
                'score' => isset($body->result) ? (string) $body->result->resultScore->textString : null,
            ];
        }
        return [$answer, $thrown];
    }

    /**
     * The final grade the gradebook holds for the student in an activity.
     *
     * @param \stdClass|null $lti the activity, the graded activity by default.
     * @return float|null the grade, or null if there is none.
     */
    private function gradebook_grade(?\stdClass $lti = null): ?float {
        $lti ??= $this->lti;
        $grades = grade_get_grades($this->course->id, 'mod', 'lti', $lti->id, $this->student->id);
        $grade = $grades->items[0]->grades[$this->student->id]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * A tool replaces, reads and deletes a result, and the gradebook follows.
     */
    public function test_replace_read_and_delete_a_result(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $this->assertNull($this->gradebook_grade());
        $answers = [];

        [$answers['replace'], $thrown] = $this->post($this->lti_basic_outcomes_message('replaceResult', $this->sourcedid(), 0.8));
        $this->assertNull($thrown);
        $this->assertEquals(80, $this->gradebook_grade());

        [$answers['read'], $thrown] = $this->post($this->lti_basic_outcomes_message('readResult', $this->sourcedid()));
        $this->assertNull($thrown);

        [$answers['delete'], $thrown] = $this->post($this->lti_basic_outcomes_message('deleteResult', $this->sourcedid()));
        $this->assertNull($thrown);
        $this->assertNull($this->gradebook_grade());

        [$answers['readafterdelete'], $thrown] = $this->post($this->lti_basic_outcomes_message('readResult', $this->sourcedid()));
        $this->assertNull($thrown);

        $this->lti_assert_matches_fixture('services/basic_outcomes_responses.json', $answers, []);
    }

    /**
     * A result that was never set reads as an empty score.
     */
    public function test_read_a_result_that_was_never_set(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();

        [$answer, $thrown] = $this->post($this->lti_basic_outcomes_message('readResult', $this->sourcedid()));

        $this->assertNull($thrown);
        $this->assertSame('success', $answer['codeMajor']);
        $this->assertSame('', $answer['score']);
    }

    /**
     * A sourcedid that was given for one activity cannot be used for another: the endpoint refuses it and no grade changes.
     */
    public function test_a_sourcedid_for_another_activity_is_refused(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();
        $other = $this->create_activity('Other activity');

        // The sourcedid given for the graded activity, with the activity changed to the other one.
        $sourcedid = json_decode($this->sourcedid());
        $sourcedid->data->instanceid = $other->id;

        foreach (['replaceResult' => 0.5, 'readResult' => null, 'deleteResult' => null] as $type => $score) {
            [$answer, $thrown] = $this->post($this->lti_basic_outcomes_message($type, json_encode($sourcedid), $score));
            $this->assertNull($answer);
            $this->assertInstanceOf(\Exception::class, $thrown);
            $this->assertSame('SourcedId hash not valid', $thrown->getMessage());
        }
        $this->assertNull($this->gradebook_grade($other));
        $this->assertNull($this->gradebook_grade());
    }

    /**
     * A message signed with a secret other than the tool's is refused and no grade changes.
     */
    public function test_a_message_with_a_bad_signature_is_refused(): void {
        $this->resetAfterTest();
        $this->create_course_and_activity();

        [$answer, $thrown] = $this->post(
            $this->lti_basic_outcomes_message('replaceResult', $this->sourcedid(), 0.8),
            'notthesecret'
        );

        $this->assertDebuggingCalled();
        $this->assertNull($answer);
        $this->assertSame('Message signature not valid', $thrown->getMessage());
        $this->assertNull($this->gradebook_grade());
    }
}
