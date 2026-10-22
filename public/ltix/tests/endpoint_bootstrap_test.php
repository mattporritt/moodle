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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Static checks for the bootstrap constants of the unauthenticated LTI endpoints.
 *
 * These scripts are called by external tools without a Moodle session. They must define NO_MOODLE_COOKIES (so no
 * session is started and no Set-Cookie header is sent) and NO_DEBUG_DISPLAY (so PHP notices cannot corrupt the JSON
 * response) before config.php is loaded. The constants are only read while config.php runs, and an HTTP-level request
 * cannot be made from PHPUnit, so the entry point source is asserted instead.
 *
 * @package   core_ltix
 * @copyright 2026 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class endpoint_bootstrap_test extends \advanced_testcase {
    /**
     * Provide the entry points that must not start a session or display debugging.
     *
     * @return array
     */
    public static function endpoint_provider(): array {
        return [
            'ltix token' => ['ltix/token.php'],
            'ltix certs' => ['ltix/certs.php'],
            'mod_lti token (old path)' => ['mod/lti/token.php'],
            'mod_lti certs (old path)' => ['mod/lti/certs.php'],
            'ltix service' => ['ltix/service.php'],
            'ltix services' => ['ltix/services.php'],
            'mod_lti service (old path)' => ['mod/lti/service.php'],
            'mod_lti services (old path)' => ['mod/lti/services.php'],
            'ltix openid-configuration' => ['ltix/openid-configuration.php'],
            'ltix openid-registration' => ['ltix/openid-registration.php'],
        ];
    }

    /**
     * Test the endpoint defines both constants before it loads config.php.
     *
     * @param string $path Script path relative to the public directory.
     */
    #[DataProvider('endpoint_provider')]
    public function test_endpoint_defines_constants_before_config(string $path): void {
        global $CFG;

        $file = $CFG->dirroot . '/' . $path;
        $this->assertFileExists($file);
        $source = file_get_contents($file);

        $pattern = '/require_once\(__DIR__ \. [\'"](\/\.\.)+\/config\.php[\'"]\)/';
        $loaded = preg_match($pattern, $source, $config, PREG_OFFSET_CAPTURE);
        $configpos = $loaded ? $config[0][1] : false;
        $this->assertNotFalse($configpos, "{$path} does not load config.php");

        foreach (['NO_MOODLE_COOKIES', 'NO_DEBUG_DISPLAY'] as $constant) {
            $matched = preg_match("/^define\('{$constant}', true\);$/m", $source, $matches, PREG_OFFSET_CAPTURE);
            $this->assertSame(1, $matched, "{$path} does not define {$constant}");
            $this->assertLessThan($configpos, $matches[0][1], "{$path} defines {$constant} after loading config.php");
        }
    }
}
