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
 * Characterisation test: the endpoints that tools call must not start a Moodle session.
 *
 * The token, key set, service and registration endpoints are called by other servers, so they must not set a session cookie
 * and must not print PHP debug output into a JSON response. They do that by defining NO_MOODLE_COOKIES and NO_DEBUG_DISPLAY
 * before config.php is loaded. A script cannot be run in PHPUnit with these constants, because defining them would change every
 * later test in the process, so this test reads each script and asserts that the constants are defined first.
 *
 * It asserts the declaration, not the cookie. The cookie itself needs a web request, which is in the backlog of the P7 report.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <MAILPLACEHOLDER>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class endpoint_bootstrap_test extends \advanced_testcase {
    use characterisation_trait;

    /**
     * Provide the endpoint scripts.
     *
     * @return \Generator
     */
    public static function endpoint_provider(): \Generator {
        $scripts = (new class {
            use characterisation_trait;

            /**
             * Expose the script list to the data provider.
             *
             * @return string[]
             */
            public function scripts(): array {
                return $this->lti_sessionless_endpoint_scripts();
            }
        })->scripts();
        foreach ($scripts as $script) {
            yield $script => [$script];
        }
    }

    /**
     * Each sessionless endpoint declares NO_MOODLE_COOKIES and NO_DEBUG_DISPLAY before it loads config.php.
     *
     * @param string $script the script, relative to the code root.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoint_provider')]
    public function test_endpoint_is_sessionless(string $script): void {
        global $CFG;
        $code = file_get_contents("{$CFG->dirroot}/{$script}");
        $this->assertNotFalse($code);

        $configposition = $this->position($code, '/require(_once)?\s*\(.*config\.php[\'"]\s*\)/');
        $this->assertNotNull($configposition, "$script does not load config.php");
        foreach (['NO_MOODLE_COOKIES', 'NO_DEBUG_DISPLAY'] as $constant) {
            $position = $this->position($code, "/^\s*define\s*\(\s*['\"]{$constant}['\"]\s*,\s*true\s*\)\s*;/m");
            $this->assertNotNull($position, "$script does not define $constant");
            $this->assertLessThan($configposition, $position, "$script defines $constant after loading config.php");
        }
    }

    /**
     * Find where a pattern first matches.
     *
     * @param string $code the code.
     * @param string $pattern the regular expression.
     * @return int|null the offset, or null.
     */
    private function position(string $code, string $pattern): ?int {
        return preg_match($pattern, $code, $matches, PREG_OFFSET_CAPTURE) ? $matches[0][1] : null;
    }

    /**
     * The key set endpoint publishes the platform's public keys as a JWK set, which tools fetch to verify launches.
     */
    public function test_platform_key_set_is_published(): void {
        $this->resetAfterTest();
        $keyset = $this->lti_platform_key_set();
        $this->assertNotEmpty($keyset['keys']);
        foreach ($keyset['keys'] as $key) {
            $this->assertSame('RSA', $key['kty']);
            $this->assertSame('RS256', $key['alg']);
            $this->assertSame('sig', $key['use']);
            $this->assertNotEmpty($key['kid']);
            $this->assertNotEmpty($key['n']);
            $this->assertNotEmpty($key['e']);
            $this->assertArrayNotHasKey('d', $key, 'The private exponent must never be published');
        }
    }
}
