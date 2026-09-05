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
 * Class containing unit tests for the hub api class.
 *
 * @package    core
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /**
     * Clears the registration cache before each test.
     *
     * MOODLE_501_STABLE predates registration::reset_caches(), which on later branches is
     * called automatically by phpunit_util::reset_all_data() between tests. Without it, the
     * static cache in {@see registration} would leak a registration record from one test
     * into the next.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->reset_registration_cache();
    }

    /**
     * Clears the protected static registration cache in {@see registration}.
     *
     * MOODLE_501_STABLE predates registration::reset_caches(), so the cache is cleared
     * directly via reflection instead.
     */
    private function reset_registration_cache(): void {
        $property = new \ReflectionProperty(registration::class, 'registration');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Registers the site locally so {@see registration::get_secret()} returns a known value.
     *
     * @param string $secret
     */
    private function register_site(string $secret): void {
        global $DB;

        $this->reset_registration_cache();
        $DB->insert_record('registration_hubs', [
            'token' => 'sometoken',
            'hubname' => 'moodle',
            'huburl' => HUB_MOODLEORGHUBURL,
            'confirmed' => 1,
            'secret' => $secret,
            'timemodified' => time(),
        ]);
    }

    /**
     * Calls the protected api::sign_registration_update() method.
     *
     * @param string $siteurl
     * @return array
     */
    private function sign_registration_update(string $siteurl): array {
        $method = new \ReflectionMethod(api::class, 'sign_registration_update');
        $method->setAccessible(true);
        return $method->invoke(null, $siteurl);
    }

    /**
     * A registered site with a known secret signs using HMAC-SHA256 keyed on md5(secret),
     * over 'siteurl|timestamp', matching the contract agreed with the hub (MDLSITE-8480).
     */
    public function test_sign_registration_update_returns_valid_signature(): void {
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen(1700000000);
        $this->register_site('thesharedsecret');

        $result = $this->sign_registration_update('https://example.com');

        $this->assertSame($clock->time(), $result['timestamp']);
        $expected = hash_hmac('sha256', 'https://example.com|' . $clock->time(), md5('thesharedsecret'));
        $this->assertSame($expected, $result['signature']);
    }

    /**
     * A site that is not registered has no secret to sign with, and must degrade to sending
     * no signature at all rather than failing.
     */
    public function test_sign_registration_update_without_registration_returns_empty(): void {
        $this->resetAfterTest();

        $result = $this->sign_registration_update('https://example.com');

        $this->assertSame([], $result);
    }

    /**
     * An empty site URL cannot be signed meaningfully and must degrade to no signature.
     */
    public function test_sign_registration_update_without_url_returns_empty(): void {
        $this->resetAfterTest();

        $this->register_site('thesharedsecret');

        $result = $this->sign_registration_update('');

        $this->assertSame([], $result);
    }

    /**
     * Different site secrets must produce different signatures for the same URL and timestamp,
     * otherwise the signature would prove nothing about which site sent the update.
     */
    public function test_sign_registration_update_signature_depends_on_secret(): void {
        $this->resetAfterTest();

        $this->mock_clock_with_frozen(1700000000);
        $this->register_site('secretone');
        $first = $this->sign_registration_update('https://example.com');

        $this->reset_registration_cache();
        global $DB;
        $DB->delete_records('registration_hubs', ['huburl' => HUB_MOODLEORGHUBURL]);
        $this->register_site('secrettwo');
        $second = $this->sign_registration_update('https://example.com');

        $this->assertNotSame($first['signature'], $second['signature']);
    }

    /**
     * An unexpected error while signing (for example, a broken clock service) must degrade to
     * sending no signature, exactly like the anticipated empty-input cases, and must not
     * propagate the exception.
     */
    public function test_sign_registration_update_degrades_on_unexpected_error(): void {
        $this->resetAfterTest();

        $this->register_site('thesharedsecret');

        \core\di::set(\core\clock::class, new class implements \core\clock {
            /**
             * Always throws, to simulate an unexpected error inside the signing code.
             */
            public function time(): int {
                throw new \RuntimeException('clock is unavailable');
            }

            /**
             * Always throws, to simulate an unexpected error inside the signing code.
             */
            public function now(): \DateTimeImmutable {
                throw new \RuntimeException('clock is unavailable');
            }
        });

        $result = $this->sign_registration_update('https://example.com');

        $this->assertSame([], $result);
        $this->assertDebuggingCalled('Failed to sign registration update: clock is unavailable');
    }
}
