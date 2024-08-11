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

namespace aiprovider_awsbedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use core_ai\ratelimiter;

/**
 * Test AWS Bedrock provider methods.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\provider\awsbedrock
 */
final class provider_test extends \advanced_testcase {

    /**
     * Test get_action_list
     */
    public function test_get_action_list(): void {
        $provider = new \aiprovider_awsbedrock\provider();
        $actionlist = $provider->get_action_list();
        $this->assertIsArray($actionlist);
        $this->assertEquals(3, count($actionlist));
        $this->assertContains('core_ai\\aiactions\\generate_text', $actionlist);
        $this->assertContains('core_ai\\aiactions\\generate_image', $actionlist);
        $this->assertContains('core_ai\\aiactions\\summarise_text', $actionlist);
    }


    /**
     * Test create_http_client.
     */
    public function test_create_bedrock_client(): void {
        $this->resetAfterTest();
        set_config('apikey', '123', 'aiprovider_awsbedrock');
        set_config('apisecret', '456', 'aiprovider_awsbedrock');
        $provider = new \aiprovider_awsbedrock\provider();
        $region = 'us-east-1';
        $client = $provider->create_bedrock_client($region);

        $this->assertInstanceOf(BedrockRuntimeClient::class, $client);
    }

    /**
     * Test is_request_allowed.
     */
    public function test_is_request_allowed(): void {
        $this->resetAfterTest(true);
        ratelimiter::reset_instance(); // Reset the singleton instance.

        // Set plugin config rate limiter settings.
        set_config('enableglobalratelimit', 1, 'aiprovider_awsbedrock');
        set_config('globalratelimit', 5, 'aiprovider_awsbedrock');
        set_config('enableuserratelimit', 1, 'aiprovider_awsbedrock');
        set_config('userratelimit', 3, 'aiprovider_awsbedrock');

        $contextid = 1;
        $userid = 1;
        $prompttext = 'This is a test prompt';
        $aspectratio = 'square';
        $quality = 'hd';
        $numimages = 1;
        $style = 'vivid';

        $action = new \core_ai\aiactions\generate_image(
                contextid: $contextid,
                userid: $userid,
                prompttext: $prompttext,
                quality: $quality,
                aspectratio: $aspectratio,
                numimages: $numimages,
                style: $style
        );
        $provider = new \aiprovider_awsbedrock\provider();

        // Make 3 requests, all should be allowed.
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($provider->is_request_allowed($action));
        }

        // The 4th request for the same user should be denied.
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);
        $this->assertEquals('User rate limit exceeded', $result['errormessage']);

        // Change user id to make a request for a different user, should pass (4 requests for global rate).
        $action = new \core_ai\aiactions\generate_image(
                contextid: $contextid,
                userid: 2,
                prompttext: $prompttext,
                quality: $quality,
                aspectratio: $aspectratio,
                numimages: $numimages,
                style: $style
        );
        $this->assertTrue($provider->is_request_allowed($action));

        // Make a 5th request for the global rate limit, it should be allowed.
        $this->assertTrue($provider->is_request_allowed($action));

        // The 6th request should be denied.
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);
        $this->assertEquals('Global rate limit exceeded', $result['errormessage']);
    }
}
