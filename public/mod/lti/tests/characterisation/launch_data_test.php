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
 * Characterisation test: the exact data Moodle sends to a tool when an activity is launched.
 *
 * Each scenario builds a tool and an activity from the generators, takes the launch data for a given user and compares it with
 * the JSON file of the same name in tests/fixtures/characterisation/launch. Only the values that change on every launch are
 * normalised (see normalise_launch_data()). Everything else, including every service parameter and claim, is compared exactly.
 *
 * Placeholders in the fixtures ({{course}}, {{instance}} and so on) are the ids of the records created by the scenario. Ids
 * that Moodle sends as numbers, not strings, are written as "{{foruser:int}}" so that the fixtures stay valid JSON.
 *
 * These files describe what Moodle does today, so that moving LTI to core_ltix can be shown not to change it. A fixture
 * changes only when the launch data is meant to change.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class launch_data_test extends \advanced_testcase {
    use characterisation_trait;

    /** @var string LTI 1.1 tool version setting. */
    private const LTI11 = 'LTI-1p0';
    /** @var string LTI 1.3 tool version setting. */
    private const LTI13 = '1.3.0';

    /** @var int Setting value: never send. */
    private const NEVER = 0;
    /** @var int Setting value: always send. */
    private const ALWAYS = 1;
    /** @var int Setting value: the teacher decides, per activity. */
    private const DELEGATE = 2;

    /** @var int Launch container: new window. */
    private const CONTAINER_WINDOW = 4;

    /** @var string The nonce the launch is asked to sign into an LTI 1.3 launch. */
    private const NONCE = 'characterisation-nonce';

    /** @var string Custom parameters that exercise the substitution variables. */
    private const CUSTOM = "rl_id=\$ResourceLink.id\nrl_title=\$ResourceLink.title\nmember_role=\$Membership.role\n" .
        "person_fullname=\$Person.name.full\nperson_email=\$Person.email.primary\nuser_username=\$User.username\n" .
        "context_id=\$Context.id\ncontext_title=\$Context.title\nfixed=abc";

    /**
     * Every scenario: the tool, the activity, who launches, and the message type.
     *
     * 'tool' is merged over the default tool of that version; null means a manually configured activity with no tool.
     * 'services' turns on the AGS, NRPS and tool settings services for the tool.
     *
     * @return array
     */
    private static function scenarios(): array {
        $reviewtype = 'LtiSubmissionReviewRequest';
        return [
            'lti11_student_all_services' => [
                'version' => self::LTI11, 'user' => 'student', 'services' => true, 'custom' => true,
                'activity' => ['resourcekey' => 'activitykey', 'password' => 'activitysecret'],
            ],
            'lti11_student_no_services' => ['version' => self::LTI11, 'user' => 'student'],
            'lti11_teacher_new_window' => [
                'version' => self::LTI11, 'user' => 'teacher', 'activity' => ['launchcontainer' => self::CONTAINER_WINDOW],
            ],
            'lti11_admin' => ['version' => self::LTI11, 'user' => 'admin'],
            'lti11_privacy_never' => [
                'version' => self::LTI11, 'user' => 'student',
                'tool' => ['lti_sendname' => self::NEVER, 'lti_sendemailaddr' => self::NEVER],
            ],
            'lti11_privacy_teacher_allows' => [
                'version' => self::LTI11, 'user' => 'student',
                'tool' => ['lti_sendname' => self::DELEGATE, 'lti_sendemailaddr' => self::DELEGATE],
                'activity' => ['instructorchoicesendname' => 1, 'instructorchoicesendemailaddr' => 1],
            ],
            'lti11_privacy_teacher_declines' => [
                'version' => self::LTI11, 'user' => 'student',
                'tool' => ['lti_sendname' => self::DELEGATE, 'lti_sendemailaddr' => self::DELEGATE],
                'activity' => ['instructorchoicesendname' => 0, 'instructorchoicesendemailaddr' => 0],
            ],
            'lti11_manual_activity' => [
                'version' => self::LTI11, 'user' => 'student', 'tool' => null,
                'activity' => [
                    'toolurl' => 'https://manual.example.com/launch', 'resourcekey' => 'manualkey',
                    'password' => 'manualsecret', 'instructorcustomparameters' => "manual=1\nrl=\$ResourceLink.id",
                ],
            ],
            'lti11_submission_review_for_user' => [
                'version' => self::LTI11, 'user' => 'teacher', 'messagetype' => $reviewtype, 'foruser' => true,
            ],
            'lti13_student_all_services' => [
                'version' => self::LTI13, 'user' => 'student', 'services' => true, 'custom' => true,
            ],
            'lti13_student_no_services' => ['version' => self::LTI13, 'user' => 'student'],
            'lti13_teacher_new_window' => [
                'version' => self::LTI13, 'user' => 'teacher', 'activity' => ['launchcontainer' => self::CONTAINER_WINDOW],
            ],
            'lti13_admin' => ['version' => self::LTI13, 'user' => 'admin'],
            'lti13_privacy_never' => [
                'version' => self::LTI13, 'user' => 'student',
                'tool' => ['lti_sendname' => self::NEVER, 'lti_sendemailaddr' => self::NEVER],
            ],
            'lti13_privacy_teacher_declines' => [
                'version' => self::LTI13, 'user' => 'student',
                'tool' => ['lti_sendname' => self::DELEGATE, 'lti_sendemailaddr' => self::DELEGATE],
                'activity' => ['instructorchoicesendname' => 0, 'instructorchoicesendemailaddr' => 0],
            ],
            'lti13_submission_review_for_user' => [
                'version' => self::LTI13, 'user' => 'teacher', 'messagetype' => $reviewtype, 'foruser' => true,
            ],
        ];
    }

    /**
     * Provide the scenario names.
     *
     * @return \Generator
     */
    public static function scenario_provider(): \Generator {
        foreach (array_keys(self::scenarios()) as $name) {
            yield $name => [$name];
        }
    }

    /**
     * The launch data for each scenario is exactly the recorded launch data.
     *
     * @param string $name the scenario.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenario_provider')]
    public function test_launch_data_is_unchanged(string $name): void {
        $this->resetAfterTest();
        [$actual, $tokens] = $this->launch_scenario($name);

        $file = __DIR__ . "/../fixtures/characterisation/launch/{$name}.json";
        $expected = json_decode(strtr(file_get_contents($file), $tokens), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(self::canonical($expected), self::canonical($actual));
    }

    /**
     * Build the scenario's records, launch, and return the normalised launch data with the placeholder values.
     *
     * @param string $name the scenario.
     * @return array [normalised launch data, placeholder => value]
     */
    private function launch_scenario(string $name): array {
        global $CFG, $PAGE;
        $scenario = self::scenarios()[$name] + [
            'tool' => [], 'activity' => [], 'services' => false, 'custom' => false, 'messagetype' => '', 'foruser' => false,
        ];
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Course One', 'shortname' => 'C1', 'idnumber' => 'CID1']);
        $student = $generator->create_user([
            'username' => 'stu1', 'firstname' => 'Sam', 'lastname' => 'Student', 'email' => 'sam@example.com', 'idnumber' => 'U1',
        ]);
        $teacher = $generator->create_user([
            'username' => 'tea1', 'firstname' => 'Tia', 'lastname' => 'Teacher', 'email' => 'tia@example.com', 'idnumber' => 'U2',
        ]);
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $activity = $scenario['activity'] + ['name' => 'Launch activity', 'intro' => 'Launch intro'];
        $typeid = 0;
        if ($scenario['tool'] !== null) {
            $tool = $scenario['tool'] + [
                'name' => 'Launch tool', 'baseurl' => 'https://tool.example.com/launch', 'state' => 1,
                'lti_ltiversion' => $scenario['version'], 'lti_resourcekey' => 'toolkey', 'lti_password' => 'toolsecret',
                'lti_sendname' => self::ALWAYS, 'lti_sendemailaddr' => self::ALWAYS, 'lti_acceptgrades' => self::ALWAYS,
                'lti_customparameters' => $scenario['custom'] ? self::CUSTOM : '',
            ];
            if ($scenario['version'] === self::LTI13) {
                $tool += [
                    'lti_clientid' => 'characterisation-client', 'lti_initiatelogin' => 'https://tool.example.com/login',
                    'lti_redirectionuris' => 'https://tool.example.com/redirect', 'lti_keytype' => 'JWK_KEYSET',
                    'lti_publickeyset' => 'https://tool.example.com/jwks',
                ];
            }
            if ($scenario['services']) {
                $tool += $this->lti_service_setting('gradesynchronization', 2) + $this->lti_service_setting('memberships', 1) +
                    $this->lti_service_setting('toolsettings', 1);
            }
            $typeid = $this->lti_create_tool($tool);
            // The generator gives activities a key and secret unless told otherwise; a tool launch should use the tool's own.
            $activity += ['resourcekey' => '', 'password' => ''];
        }
        $lti = $this->lti_create_activity($course->id, ['typeid' => $typeid] + $activity);

        $users = ['student' => $student, 'teacher' => $teacher, 'admin' => get_admin()];
        $user = $users[$scenario['user']];
        $this->setUser($user);
        $PAGE->set_course($course);

        [$endpoint, $params] = $this->lti_launch_data(
            $lti,
            self::NONCE,
            $scenario['messagetype'],
            $scenario['foruser'] ? (int) $student->id : 0
        );

        $tokens = [
            '{{wwwroot}}' => $CFG->wwwroot, '{{version}}' => $CFG->version, '{{course}}' => $course->id,
            '{{context}}' => \context_course::instance($course->id)->id, '{{user}}' => $user->id,
            '{{foruser}}' => $student->id, '"{{foruser:int}}"' => (int) $student->id, '{{instance}}' => $lti->id,
            '{{type}}' => $typeid, '{{sesskey}}' => sesskey(),
        ];
        return [$this->normalise_launch_data($scenario, $lti, $endpoint, $params), $tokens];
    }

    /**
     * Reduce launch data to the stable part, after checking the parts that change on every launch.
     *
     * The OAuth nonce, timestamp and signature (LTI 1.1), the JWT times (LTI 1.3) and the random launch id inside the
     * lis_result_sourcedid are checked and then removed. The signature is recomputed here from the OAuth 1.0 rules, not by
     * Moodle code, and has to verify with the key and secret the scenario expects. Lists of AGS scopes are sorted, because
     * their order is not part of the contract.
     *
     * @param array $scenario the scenario.
     * @param \stdClass $lti the activity.
     * @param string $endpoint the launch endpoint.
     * @param array $params the launch parameters.
     * @return array ['endpoint' => string, 'params' => array]
     */
    private function normalise_launch_data(array $scenario, \stdClass $lti, string $endpoint, array $params): array {
        if ($scenario['version'] === self::LTI11) {
            // An activity key and secret win over the tool's; a manual activity has only its own.
            $key = !empty($lti->resourcekey) ? $lti->resourcekey : 'toolkey';
            $secret = !empty($lti->password) ? $lti->password : 'toolsecret';
            $this->assert_valid_oauth_signature($endpoint, $params, $key, $secret);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $params['oauth_nonce']);
            $this->assertEqualsWithDelta(time(), (int) $params['oauth_timestamp'], 60);
            unset($params['oauth_nonce'], $params['oauth_timestamp'], $params['oauth_signature']);
            $params = $this->normalise_sourcedid($params, 'lis_result_sourcedid');
            if (isset($params['custom_gradebookservices_scope'])) {
                $scopes = explode(',', $params['custom_gradebookservices_scope']);
                sort($scopes);
                $params['custom_gradebookservices_scope'] = implode(',', $scopes);
            }
        } else {
            $this->assertSame(['id_token'], array_keys($params));
            [$header, $payload] = array_map(
                fn($part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true),
                array_slice(explode('.', $params['id_token']), 0, 2)
            );
            $this->assertSame('RS256', $header['alg']);
            $this->assertNotEmpty($header['kid']);
            $this->assertSame(self::NONCE, $payload['nonce']);
            $this->assertSame(60, $payload['exp'] - $payload['iat']);
            $this->assertEqualsWithDelta(time(), $payload['iat'], 60);
            unset($payload['nonce'], $payload['iat'], $payload['exp']);
            $bo = 'https://purl.imsglobal.org/spec/lti-bo/claim/basicoutcome';
            if (isset($payload[$bo])) {
                $payload[$bo] = $this->normalise_sourcedid($payload[$bo], 'lis_result_sourcedid');
            }
            $ags = 'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint';
            if (isset($payload[$ags]['scope'])) {
                sort($payload[$ags]['scope']);
            }
            $params = $payload;
        }
        return ['endpoint' => $endpoint, 'params' => $params];
    }

    /**
     * Replace a lis_result_sourcedid JSON string with its decoded data, without the random launch id and the hash.
     *
     * @param array $params the parameters holding the sourcedid.
     * @param string $key the parameter name.
     * @return array the parameters.
     */
    private function normalise_sourcedid(array $params, string $key): array {
        if (!isset($params[$key])) {
            return $params;
        }
        $sourcedid = json_decode($params[$key], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsInt($sourcedid['data']['launchid']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sourcedid['hash']);
        unset($sourcedid['data']['launchid'], $sourcedid['hash']);
        $params[$key] = $sourcedid;
        return $params;
    }

    /**
     * Assert that an LTI 1.1 launch is signed correctly (OAuth 1.0, HMAC-SHA1, POST) with the given key and secret.
     *
     * @param string $endpoint the launch URL, without a query string.
     * @param array $params the signed parameters.
     * @param string $key the expected consumer key.
     * @param string $secret the expected consumer secret.
     */
    private function assert_valid_oauth_signature(string $endpoint, array $params, string $key, string $secret): void {
        $this->assertSame($key, $params['oauth_consumer_key']);
        $this->assertSame('HMAC-SHA1', $params['oauth_signature_method']);
        $this->assertStringNotContainsString('?', $endpoint);

        $encode = fn(string $value): string => str_replace('%7E', '~', rawurlencode($value));
        $signature = $params['oauth_signature'];
        unset($params['oauth_signature']);
        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs[] = [$encode((string) $name), $encode((string) $value)];
        }
        usort($pairs, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $normalised = implode('&', array_map(fn($pair) => $pair[0] . '=' . $pair[1], $pairs));
        $base = 'POST&' . $encode($endpoint) . '&' . $encode($normalised);
        $expected = base64_encode(hash_hmac('sha1', $base, $encode($secret) . '&', true));

        $this->assertSame($expected, $signature, 'The launch is not signed with the expected key and secret');
    }

    /**
     * Sort associative arrays by key, recursively, so that comparison ignores the order of keys but not the order of lists.
     *
     * @param mixed $value the value.
     * @return mixed the value.
     */
    private static function canonical(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map([self::class, 'canonical'], $value);
        if (!array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }
}
