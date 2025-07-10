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

/**
 * LTI Constants
 *
 * @package    core_ltix
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants
{
    /** @var string Regular expression that extracts the domain from a tool URL. */
    const LTI_URL_DOMAIN_REGEX = '/(?:https?:\/\/)?(?:www\.)?([^\/]+)(?:\/|$)/i';

    /** @var int Launch container: use the default of the tool configuration. */
    const LTI_LAUNCH_CONTAINER_DEFAULT = 1;
    /** @var int Launch container: embedded, with blocks. */
    const LTI_LAUNCH_CONTAINER_EMBED = 2;
    /** @var int Launch container: embedded, without blocks. */
    const LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS = 3;
    /** @var int Launch container: new window. */
    const LTI_LAUNCH_CONTAINER_WINDOW = 4;
    /** @var int Launch container: replace the Moodle window. */
    const LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW = 5;

    /** @var int Tool state: any state, used to filter tools. */
    const LTI_TOOL_STATE_ANY = 0;
    /** @var int Tool state: configured. */
    const LTI_TOOL_STATE_CONFIGURED = 1;
    /** @var int Tool state: pending. */
    const LTI_TOOL_STATE_PENDING = 2;
    /** @var int Tool state: rejected. */
    const LTI_TOOL_STATE_REJECTED = 3;
    /** @var int Tool state: used to show the tool proxy tab. */
    const LTI_TOOL_PROXY_TAB = 4;

    // TODO: deprecate these LTI 2 consts since LTI 2.0 support is being dropped.
    /** @var int Tool proxy state: configured. */
    const LTI_TOOL_PROXY_STATE_CONFIGURED = 1;
    /** @var int Tool proxy state: pending. */
    const LTI_TOOL_PROXY_STATE_PENDING = 2;
    /** @var int Tool proxy state: accepted. */
    const LTI_TOOL_PROXY_STATE_ACCEPTED = 3;
    /** @var int Tool proxy state: rejected. */
    const LTI_TOOL_PROXY_STATE_REJECTED = 4;

    /** @var int Tool setting: never. */
    const LTI_SETTING_NEVER = 0;
    /** @var int Tool setting: always. */
    const LTI_SETTING_ALWAYS = 1;
    /** @var int Tool setting: delegate the choice to the teacher. */
    const LTI_SETTING_DELEGATE = 2;

    /** @var int Course visibility: the tool is not available in courses. */
    const LTI_COURSEVISIBLE_NO = 0;
    /** @var int Course visibility: the tool is available as a preconfigured tool. */
    const LTI_COURSEVISIBLE_PRECONFIGURED = 1;

    /** @var string LTI version 1.0 and 1.1. */
    const LTI_VERSION_1 = 'LTI-1p0';
    /** @var string LTI version 2.0. */
    const LTI_VERSION_2 = 'LTI-2p0';
    /** @var string LTI version 1.3. */
    const LTI_VERSION_1P3 = '1.3.0';

    /** @var string Public key type: an RSA key. */
    const LTI_RSA_KEY = 'RSA_KEY';
    /** @var string Public key type: a JWK key set URL. */
    const LTI_JWK_KEYSET = 'JWK_KEYSET';

    /** @var string Default organization id: the site id. */
    const LTI_DEFAULT_ORGID_SITEID = 'SITEID';
    /** @var string Default organization id: the site host. */
    const LTI_DEFAULT_ORGID_SITEHOST = 'SITEHOST';

    /** @var int Lifetime of an access token, in seconds. */
    const LTI_ACCESS_TOKEN_LIFE = 3600;

    /** @var string Standard prefix for JWT claims. */
    const LTI_JWT_CLAIM_PREFIX = 'https://purl.imsglobal.org/spec/lti';

    // TODO: maybe these can live in mod_lti if only used there..
    /** @var string Grade item type of LTI activities. */
    const LTI_ITEM_TYPE = 'mod';
    /** @var string Grade item module of LTI activities. */
    const LTI_ITEM_MODULE = 'lti';
    /** @var string Grade source of LTI activities. */
    const LTI_SOURCE = 'mod/lti';
}
