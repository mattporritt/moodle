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

namespace mod_lti;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Tests that the global LTI constants removed by MDL-84415 are still defined, with their old values.
 *
 * Third party code includes mod/lti/locallib.php (or servicelib.php, which includes it) and uses these constants.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class deprecated_constants_test extends \advanced_testcase {
    /**
     * Each global constant is still defined and has the value it had in Moodle 5.3.
     *
     * @param string $name The constant name.
     * @param mixed $expected The value in Moodle 5.3.
     */
    #[DataProvider('old_constants_provider')]
    public function test_global_constant_keeps_old_value(string $name, mixed $expected): void {
        $this->assertTrue(defined($name), "$name is not defined");
        $this->assertSame($expected, constant($name));
    }

    /**
     * Each global constant that has a \core_ltix\constants replacement has the same value as it.
     *
     * @param string $name The constant name.
     * @param mixed $expected The value in Moodle 5.3, not used here.
     */
    #[DataProvider('old_constants_provider')]
    public function test_global_constant_matches_class_constant(string $name, mixed $expected): void {
        $classconstant = \core_ltix\constants::class . '::' . $name;
        if (!defined($classconstant)) {
            $this->markTestSkipped("$name has no \\core_ltix\\constants replacement");
        }
        $this->assertSame(constant($classconstant), constant($name));
    }

    /**
     * The constants of Moodle 5.3 mod/lti/locallib.php and mod/lti/servicelib.php, with their values.
     *
     * @return array
     */
    public static function old_constants_provider(): array {
        return [
            'LTI_URL_DOMAIN_REGEX' => ['LTI_URL_DOMAIN_REGEX', '/(?:https?:\/\/)?(?:www\.)?([^\/]+)(?:\/|$)/i'],
            'LTI_LAUNCH_CONTAINER_DEFAULT' => ['LTI_LAUNCH_CONTAINER_DEFAULT', 1],
            'LTI_LAUNCH_CONTAINER_EMBED' => ['LTI_LAUNCH_CONTAINER_EMBED', 2],
            'LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS' => ['LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS', 3],
            'LTI_LAUNCH_CONTAINER_WINDOW' => ['LTI_LAUNCH_CONTAINER_WINDOW', 4],
            'LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW' => ['LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW', 5],
            'LTI_TOOL_STATE_ANY' => ['LTI_TOOL_STATE_ANY', 0],
            'LTI_TOOL_STATE_CONFIGURED' => ['LTI_TOOL_STATE_CONFIGURED', 1],
            'LTI_TOOL_STATE_PENDING' => ['LTI_TOOL_STATE_PENDING', 2],
            'LTI_TOOL_STATE_REJECTED' => ['LTI_TOOL_STATE_REJECTED', 3],
            'LTI_TOOL_PROXY_TAB' => ['LTI_TOOL_PROXY_TAB', 4],
            'LTI_TOOL_PROXY_STATE_CONFIGURED' => ['LTI_TOOL_PROXY_STATE_CONFIGURED', 1],
            'LTI_TOOL_PROXY_STATE_PENDING' => ['LTI_TOOL_PROXY_STATE_PENDING', 2],
            'LTI_TOOL_PROXY_STATE_ACCEPTED' => ['LTI_TOOL_PROXY_STATE_ACCEPTED', 3],
            'LTI_TOOL_PROXY_STATE_REJECTED' => ['LTI_TOOL_PROXY_STATE_REJECTED', 4],
            'LTI_SETTING_NEVER' => ['LTI_SETTING_NEVER', 0],
            'LTI_SETTING_ALWAYS' => ['LTI_SETTING_ALWAYS', 1],
            'LTI_SETTING_DELEGATE' => ['LTI_SETTING_DELEGATE', 2],
            'LTI_COURSEVISIBLE_NO' => ['LTI_COURSEVISIBLE_NO', 0],
            'LTI_COURSEVISIBLE_PRECONFIGURED' => ['LTI_COURSEVISIBLE_PRECONFIGURED', 1],
            'LTI_COURSEVISIBLE_ACTIVITYCHOOSER' => ['LTI_COURSEVISIBLE_ACTIVITYCHOOSER', 2],
            'LTI_VERSION_1' => ['LTI_VERSION_1', 'LTI-1p0'],
            'LTI_VERSION_2' => ['LTI_VERSION_2', 'LTI-2p0'],
            'LTI_VERSION_1P3' => ['LTI_VERSION_1P3', '1.3.0'],
            'LTI_RSA_KEY' => ['LTI_RSA_KEY', 'RSA_KEY'],
            'LTI_JWK_KEYSET' => ['LTI_JWK_KEYSET', 'JWK_KEYSET'],
            'LTI_DEFAULT_ORGID_SITEID' => ['LTI_DEFAULT_ORGID_SITEID', 'SITEID'],
            'LTI_DEFAULT_ORGID_SITEHOST' => ['LTI_DEFAULT_ORGID_SITEHOST', 'SITEHOST'],
            'LTI_ACCESS_TOKEN_LIFE' => ['LTI_ACCESS_TOKEN_LIFE', 3600],
            'LTI_JWT_CLAIM_PREFIX' => ['LTI_JWT_CLAIM_PREFIX', 'https://purl.imsglobal.org/spec/lti'],
            'LTI_ITEM_TYPE' => ['LTI_ITEM_TYPE', 'mod'],
            'LTI_ITEM_MODULE' => ['LTI_ITEM_MODULE', 'lti'],
            'LTI_SOURCE' => ['LTI_SOURCE', 'mod/lti'],
        ];
    }
}
