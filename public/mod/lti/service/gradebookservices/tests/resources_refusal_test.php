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
 * Tests for the refusal of unauthenticated requests by the assignment and grade service resources.
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
    /**
     * A request that fails authentication is refused with a 401 and the standard reason, without PHP errors.
     *
     * @param string $resourceclass The resource class.
     * @param string $method The HTTP method.
     * @param string $pathinfo The request path.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unauthenticated_provider')]
    public function test_execute_unauthenticated_request_is_refused(
        string $resourceclass,
        string $method,
        string $pathinfo,
    ): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $this->resetAfterTest();

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['PATH_INFO'] = $pathinfo;
        $_GET = ['type_id' => '1'];

        $response = new response();
        $response->set_accept('application/vnd.ims.lis.v2.lineitemcontainer+json');
        $response->set_content_type('application/vnd.ims.lis.v1.score+json');
        (new $resourceclass(new gradebookservices()))->execute($response);

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
            'lineitems' => [local\resources\lineitems::class, 'GET', '/1/lineitems'],
            'lineitem' => [local\resources\lineitem::class, 'GET', '/1/lineitems/1/lineitem'],
            'results' => [local\resources\results::class, 'GET', '/1/lineitems/1/lineitem/results'],
            'scores' => [local\resources\scores::class, 'POST', '/1/lineitems/1/lineitem/scores'],
        ];
    }
}
