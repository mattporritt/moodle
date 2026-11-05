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

namespace ltiservice_gradebookservices;

use ltiservice_gradebookservices\local\service\gradebookservices;
use mod_lti\local\ltiservice\response;

/**
 * Tests for the refusal of unauthenticated and disallowed requests by the assignment and grade service resources.
 *
 * @package    ltiservice_gradebookservices
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(local\resources\lineitems::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\resources\lineitem::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\resources\results::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\resources\scores::class)]
final class resources_refusal_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    /**
     * Run a resource against a simulated request and return the response.
     *
     * @param string $resourceclass The resource class.
     * @param string $method The HTTP method.
     * @param string $pathinfo The request path.
     * @param string $accept The Accept header value.
     * @param string $contenttype The Content-Type header value.
     * @param int|null $typeid The type_id request parameter, if any.
     * @param string|null $authorization The Authorization header value, if any.
     * @return response
     */
    private function execute_resource(
        string $resourceclass,
        string $method,
        string $pathinfo,
        string $accept,
        string $contenttype,
        ?int $typeid,
        ?string $authorization,
    ): response {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['PATH_INFO'] = $pathinfo;
        $_GET = $typeid === null ? [] : ['type_id' => (string) $typeid];
        if ($authorization !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        }

        $response = new response();
        $response->set_accept($accept);
        $response->set_content_type($contenttype);
        (new $resourceclass(new gradebookservices()))->execute($response);
        return $response;
    }

    /**
     * A request that fails authentication is refused with a 401 and the standard reason, without PHP errors.
     *
     * @param string $resourceclass The resource class.
     * @param string $method The HTTP method.
     * @param string $pathinfo The request path.
     * @param string|null $authorization The Authorization header value, if any.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unauthenticated_provider')]
    public function test_execute_unauthenticated_request_is_refused(
        string $resourceclass,
        string $method,
        string $pathinfo,
        ?string $authorization,
    ): void {
        $this->resetAfterTest();

        $response = $this->execute_resource(
            $resourceclass,
            $method,
            $pathinfo,
            'application/vnd.ims.lis.v2.lineitemcontainer+json',
            'application/vnd.ims.lis.v1.score+json',
            1,
            $authorization,
        );

        $this->assertSame(401, $response->get_code());
        $this->assertSame('Unauthorized', $response->get_reason());
    }

    /**
     * Data provider for the unauthenticated request test.
     *
     * @return array
     */
    public static function unauthenticated_provider(): array {
        $resources = [
            'lineitems' => [local\resources\lineitems::class, 'GET', '/1/lineitems'],
            'lineitem' => [local\resources\lineitem::class, 'GET', '/1/lineitems/1/lineitem'],
            'results' => [local\resources\results::class, 'GET', '/1/lineitems/1/lineitem/results'],
            'scores' => [local\resources\scores::class, 'POST', '/1/lineitems/1/lineitem/scores'],
        ];
        $cases = [];
        foreach ($resources as $name => $resource) {
            $cases["$name without a token"] = [...$resource, null];
            $cases["$name with an invalid bearer token"] = [...$resource, 'Bearer notarealtoken'];
        }
        return $cases;
    }

    /**
     * A request with a valid token that is not permitted to proceed is refused with the intended code and reason.
     *
     * @param string $resourceclass The resource class.
     * @param string $method The HTTP method.
     * @param string $pathtemplate The request path, with %d standing for the course id.
     * @param string $accept The Accept header value.
     * @param string $contenttype The Content-Type header value.
     * @param string $scope The scope the token is granted.
     * @param bool $othercourse Whether the tool is restricted to a different course than the requested one.
     * @param int $code The expected response code.
     * @param string $reason The expected response reason.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused_provider')]
    public function test_execute_authenticated_request_is_refused(
        string $resourceclass,
        string $method,
        string $pathtemplate,
        string $accept,
        string $contenttype,
        string $scope,
        bool $othercourse,
        int $code,
        string $reason,
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $toolcourse = $othercourse ? $this->getDataGenerator()->create_course()->id : SITEID;
        $typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Test tool',
            'baseurl' => 'https://example.com/tool',
            'course' => $toolcourse,
            'ltiservice_gradesynchronization' => gradebookservices::GRADEBOOKSERVICES_FULL,
        ]);
        $token = lti_new_access_token($typeid, [$scope]);

        $response = $this->execute_resource(
            $resourceclass,
            $method,
            sprintf($pathtemplate, $course->id),
            $accept,
            $contenttype,
            $typeid,
            'Bearer ' . $token->token,
        );

        $this->assertSame($code, $response->get_code());
        $this->assertSame($reason, $response->get_reason());
    }

    /**
     * Data provider for the authenticated refusal test.
     *
     * @return array
     */
    public static function refused_provider(): array {
        $container = 'application/vnd.ims.lis.v2.lineitemcontainer+json';
        $lineitem = 'application/vnd.ims.lis.v2.lineitem+json';
        $score = 'application/vnd.ims.lis.v1.score+json';
        $lineitemscope = gradebookservices::SCOPE_GRADEBOOKSERVICES_LINEITEM;
        $scorescope = gradebookservices::SCOPE_GRADEBOOKSERVICES_SCORE;
        $resultscope = gradebookservices::SCOPE_GRADEBOOKSERVICES_RESULT_READ;
        return [
            'lineitems unsupported content type' => [
                local\resources\lineitems::class, 'GET', '/%d/lineitems', 'application/json', '', $lineitemscope,
                false, 400, 'No context or unsupported content type',
            ],
            'lineitems tool not allowed in course' => [
                local\resources\lineitems::class, 'GET', '/%d/lineitems', $container, '', $lineitemscope,
                true, 403, 'Not allowed in context',
            ],
            'lineitem tool not allowed in course' => [
                local\resources\lineitem::class, 'GET', '/%d/lineitems/1/lineitem', $lineitem, '', $lineitemscope,
                true, 403, 'Not allowed in context',
            ],
            'results tool not allowed in course' => [
                local\resources\results::class, 'GET', '/%d/lineitems/1/lineitem/results', '', '', $resultscope,
                true, 403, 'Not allowed in context',
            ],
            'scores unsupported content type' => [
                local\resources\scores::class, 'POST', '/%d/lineitems/1/lineitem/scores', '', 'application/json',
                $scorescope, false, 400, 'No context or unsupported content type',
            ],
            'scores tool not allowed in course' => [
                local\resources\scores::class, 'POST', '/%d/lineitems/1/lineitem/scores', '', $score,
                $scorescope, true, 403, 'Not allowed in context',
            ],
        ];
    }
}
