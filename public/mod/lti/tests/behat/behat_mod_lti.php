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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for mod_lti.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_lti extends behat_base {
    /**
     * Request an LTI endpoint directly, without a browser session, and check the raw response.
     *
     * @Then /^the LTI endpoint "(?P<path_string>[^"]*)" should respond with "(?P<contenttype_string>[^"]*)" and set no cookie$/
     * @param string $path path relative to the site root
     * @param string $contenttype expected media type
     */
    public function the_lti_endpoint_should_respond_without_cookie(string $path, string $contenttype): void {
        global $CFG;

        $curl = new \curl();
        $body = (string) $curl->get("{$CFG->wwwroot}/{$path}");
        $headers = $curl->get_raw_response();
        $raw = implode("\n", $headers);

        if (preg_grep('/^set-cookie:/i', $headers)) {
            throw new \Exception("{$path} sent a Set-Cookie header:\n{$raw}");
        }
        if (!preg_grep('/^content-type:\s*' . preg_quote($contenttype, '/') . '/i', $headers)) {
            throw new \Exception("{$path} did not respond with {$contenttype}:\n{$raw}");
        }
        if (preg_match('/(Warning|Notice|Deprecated|Fatal error)<\/b>:|^(Warning|Notice|Deprecated): /m', $body)) {
            throw new \Exception("{$path} body contains PHP debug output:\n{$body}");
        }
    }
}
