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

namespace core_ltix;

use core\exception\moodle_exception;

/**
 * Tests for the access checks of the deep linking return page, ltix/contentitem_return.php.
 *
 * The page is run in process, as the current user, with a response that the tool signed.
 *
 * @package   core_ltix
 * @category  test
 * @copyright 2026 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class contentitem_return_test extends \advanced_testcase {
    /** @var string The tool's private key, as a PEM. */
    private string $toolprivatekey;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var int The tool. */
    private int $typeid;

    /**
     * Create a course and an LTI 1.3 tool that can sign deep linking responses.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatekey);
        $this->toolprivatekey = $privatekey;
        $publickey = openssl_pkey_get_details($key)['key'];

        $this->course = $this->getDataGenerator()->create_course();
        $this->typeid = $this->getDataGenerator()->get_plugin_generator('core_ltix')->create_tool_types([
            'name' => 'Deep linking tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1,
            'coursevisible' => 1, 'lti_ltiversion' => 'LTI-1p3', 'lti_clientid' => 'dl-client',
            'lti_initiatelogin' => 'https://tool.example.com/login',
            'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_publickeytype' => 'RSA_KEY',
            'lti_publickey' => $publickey, 'lti_contentitem' => 1,
        ]);
    }

    /**
     * Start a content selection as the current user, as the selection page does, and return its launch id.
     *
     * @return string the launch id the tool has to send back.
     */
    private function start_selection(): string {
        $config = helper::get_type_type_config($this->typeid);
        $request = helper::build_login_request(
            $this->course->id,
            0,
            null,
            $config,
            'ContentItemSelectionRequest',
            'mod_lti:activityplacement',
            0,
            'Selected title',
            'Selected text'
        );
        return json_decode($request['lti_message_hint'])->launchid;
    }

    /**
     * Sign a deep linking response that chooses a resource link, as the tool would.
     *
     * @param string $launchid the launch id the tool was given.
     * @return string the JWT.
     */
    private function tool_signs_response(string $launchid): string {
        global $CFG;
        $prefix = 'https://purl.imsglobal.org/spec/lti';
        $payload = [
            'iss' => 'dl-client', 'aud' => $CFG->wwwroot, 'iat' => time(), 'exp' => time() + 60, 'nonce' => 'response-nonce',
            "$prefix/claim/message_type" => 'LtiDeepLinkingResponse', "$prefix/claim/version" => 'LTI-1p3',
            "$prefix/claim/deployment_id" => (string) $this->typeid,
            "$prefix-dl/claim/data" => json_encode(['launchid' => $launchid]),
            "$prefix-dl/claim/content_items" => [[
                'type' => 'ltiResourceLink', 'title' => 'Chosen quiz', 'url' => 'https://tool.example.com/quiz/42',
            ]],
        ];
        return \Firebase\JWT\JWT::encode($payload, $this->toolprivatekey, 'RS256', 'tool-key');
    }

    /**
     * Run the return page in a context as the current user, with the tool's response, and return what it did.
     *
     * @param int $contextid the context the selection was made in, as the tool gets it back in the return URL.
     * @param string $launchid the launch id of the selection.
     * @return array [what the page handed to the form or null, what it threw or null]
     */
    private function post_response(int $contextid, string $launchid): array {
        global $CFG, $SESSION, $USER, $DB, $PAGE, $OUTPUT;
        $_GET = ['id' => $this->typeid, 'contextid' => $contextid, 'sesskey' => sesskey()];
        $_POST = ['JWT' => $this->tool_signs_response($launchid)];
        $_REQUEST = $_GET + $_POST;
        $workingdirectory = getcwd();
        chdir("{$CFG->dirroot}/ltix");
        $thrown = null;
        ob_start();
        try {
            include("{$CFG->dirroot}/ltix/contentitem_return.php");
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        ob_end_clean();
        chdir($workingdirectory);
        $_GET = $_POST = $_REQUEST = [];

        $returned = null;
        if (!$thrown) {
            $call = '/amd\.init\((.*?)\); M\.util\.js_complete\(\'[a-z_]+\/contentitem_return\'\)/s';
            if (preg_match($call, $PAGE->requires->get_end_code(), $matches)) {
                $returned = json_decode($matches[1], true);
            }
        }
        return [$returned, $thrown];
    }

    /**
     * A teacher who may add activities and course tools gets the chosen content back.
     */
    public function test_teacher_who_is_allowed_gets_the_content(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $launchid = $this->start_selection();

        [$returned, $thrown] = $this->post_response(\context_course::instance($this->course->id)->id, $launchid);

        $this->assertNull($thrown, $thrown ? $thrown->getMessage() : '');
        $this->assertIsArray($returned);
        $this->assertSame('Chosen quiz', $returned['name']);
    }

    /**
     * A student cannot return content, even with a response the tool signed.
     */
    public function test_student_is_refused(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $launchid = $this->start_selection();

        [$returned, $thrown] = $this->post_response(\context_course::instance($this->course->id)->id, $launchid);

        $this->assertInstanceOf(\required_capability_exception::class, $thrown);
        $this->assertNull($returned);
    }

    /**
     * A teacher who may manage activities but not add course tools is refused by the activity placement.
     */
    public function test_teacher_without_addcoursetool_is_refused(): void {
        $context = \context_course::instance($this->course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/ltix:addcoursetool', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $teacher->id, $context->id);
        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context));
        $launchid = $this->start_selection();

        [$returned, $thrown] = $this->post_response($context->id, $launchid);

        $this->assertInstanceOf(\required_capability_exception::class, $thrown);
        $this->assertSame(get_capability_string('moodle/ltix:addcoursetool'), $thrown->a);
        $this->assertNull($returned);
    }

    /**
     * A context with no course is refused, even for an administrator.
     */
    public function test_context_without_a_course_is_refused(): void {
        $this->setAdminUser();
        $launchid = $this->start_selection();

        [$returned, $thrown] = $this->post_response(\context_system::instance()->id, $launchid);

        $this->assertInstanceOf(moodle_exception::class, $thrown);
        $this->assertSame('invalidcontext', $thrown->errorcode);
        $this->assertNull($returned);
    }
}
