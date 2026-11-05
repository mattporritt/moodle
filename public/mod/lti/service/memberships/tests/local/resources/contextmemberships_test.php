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
    /**
     * A request that fails authentication is refused with a 401 and the standard reason, without PHP errors.
     */
    public function test_execute_unauthenticated_request_is_refused(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $this->resetAfterTest();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = '/CourseSection/1/bindings/1/memberships';
        $_GET = ['type_id' => '1'];

        $response = new response();
        $response->set_accept('application/vnd.ims.lti-nrps.v2.membershipcontainer+json');
        (new contextmemberships(new memberships()))->execute($response);

        $this->assertSame(401, $response->get_code());
        $this->assertSame('Unauthorized', $response->get_reason());
    }
}
