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

namespace core\hub;

/**
 * Unit tests for {@see api}.
 *
 * @package    core
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \core\hub\api
 */
final class api_test extends \advanced_testcase {
    protected function tearDown(): void {
        // Registration caches the current registration record in a static property that
        // resetAfterTest() does not clear, so a confirmed registration created here would
        // otherwise leak into whichever test class runs next in the same process.
        $this->reset_registration_cache();
        parent::tearDown();
    }

    /**
     * registration caches the current registration record in a static property. Reset it
     * directly so tests are not affected by state left over from an earlier test.
     */
    private function reset_registration_cache(): void {
        $property = new \ReflectionProperty(registration::class, 'registration');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Create an unconfirmed, then confirmed, registration so api::update_registration() has a
     * token to send.
     *
     * @return void
     */
    private function register_confirmed_site(): void {
        global $DB;

        $DB->insert_record('registration_hubs', [
            'token' => 'sometoken',
            'hubname' => 'moodle',
            'huburl' => HUB_MOODLEORGHUBURL,
            'confirmed' => 1,
            'secret' => 'sometoken',
            'timemodified' => time(),
        ]);
        $this->reset_registration_cache();
    }

    /**
     * An empty response body must be treated as a failed request, not a quiet success. This is
     * what a blocked or unreachable hub URL, or a disabled hub, looks like: no exception key is
     * present to detect, because there is no decodable body at all.
     */
    public function test_call_rest_throws_on_empty_response_body(): void {
        $this->resetAfterTest();
        $this->register_confirmed_site();

        \curl::mock_response('');

        $this->expectException(\moodle_exception::class);
        api::update_registration(['url' => 'https://example.com']);
    }

    /**
     * A response body that is not valid JSON must be treated as a failed request rather than
     * silently discarded.
     */
    public function test_call_rest_throws_on_non_json_response_body(): void {
        $this->resetAfterTest();
        $this->register_confirmed_site();

        \curl::mock_response('<html>not json</html>');

        $this->expectException(\moodle_exception::class);
        api::update_registration(['url' => 'https://example.com']);
    }

    /**
     * A well-formed, decodable JSON response with no exception key must still succeed.
     */
    public function test_call_rest_succeeds_on_valid_json_response(): void {
        $this->resetAfterTest();
        $this->register_confirmed_site();

        \curl::mock_response(json_encode(['status' => 'ok']));

        // No exception means the call was accepted as successful.
        api::update_registration(['url' => 'https://example.com']);
        $this->assertTrue(true);
    }
}
