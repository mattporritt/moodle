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

namespace ltiservice_memberships\local\resources;

use ltiservice_memberships\local\service\memberships;
use mod_lti\local\ltiservice\response;

/**
 * Tests for the names and roles context memberships resource.
 *
 * @package    ltiservice_memberships
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(contextmemberships::class)]
final class contextmemberships_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    /**
     * Run the resource against a simulated request and return the response.
     *
     * @param int $courseid The course id in the request path.
     * @param int $typeid The tool type id in the request path.
     * @param string|null $authorization The Authorization header value, if any.
     * @return response
     */
    private function execute_resource(int $courseid, int $typeid, ?string $authorization): response {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = "/CourseSection/{$courseid}/bindings/{$typeid}/memberships";
        $_GET = [];
        if ($authorization !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        }

        $response = new response();
        $response->set_accept('application/vnd.ims.lti-nrps.v2.membershipcontainer+json');
        (new contextmemberships(new memberships()))->execute($response);
        return $response;
    }

    /**
     * A request that fails authentication is refused with a 401 and the standard reason, without PHP errors.
     *
     * @param string|null $authorization The Authorization header value, if any.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unauthenticated_provider')]
    public function test_execute_unauthenticated_request_is_refused(?string $authorization): void {
        $this->resetAfterTest();

        $response = $this->execute_resource(1, 1, $authorization);

        $this->assertSame(401, $response->get_code());
        $this->assertSame('Unauthorized', $response->get_reason());
    }

    /**
     * Data provider for the unauthenticated request test.
     *
     * @return array
     */
    public static function unauthenticated_provider(): array {
        return [
            'without a token' => [null],
            'with an invalid bearer token' => ['Bearer notarealtoken'],
        ];
    }

    /**
     * A request for a course the tool is not allowed in is refused with a 404 and the standard reason.
     */
    public function test_execute_tool_not_allowed_in_course_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Test tool',
            'baseurl' => 'https://example.com/tool',
            'course' => $othercourse->id,
            'ltiservice_memberships' => memberships::SERVICE_ENABLED,
        ]);
        $token = lti_new_access_token($typeid, [memberships::SCOPE_MEMBERSHIPS_READ]);

        $response = $this->execute_resource($course->id, $typeid, 'Bearer ' . $token->token);

        $this->assertSame(404, $response->get_code());
        $this->assertSame('Not Found', $response->get_reason());
    }
}
