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

namespace core\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Base for renderables that display a same-site page whose only purpose is to trigger a fresh
 * navigation to a target URL.
 *
 * A page which arrives as part of a cross-site request/redirect chain (for example, a launch
 * from an external tool consumer) cannot safely navigate straight to a target URL from within
 * that same chain: the SameSite=Lax session cookie set moments earlier by
 * complete_user_login() would not be sent on the follow-up request when it is presented in an
 * iframe. Rendering a page first, then triggering the navigation to the target URL from it,
 * starts a fresh navigation whose initiator is this Moodle page rather than the original
 * cross-site request, so the browser treats it as same-site and includes the cookie.
 *
 * How that navigation is triggered (for example, a link the user/JS clicks to issue a plain GET,
 * or a form re-posting the original request data) is left to subclasses, since it depends on
 * what the calling code needs preserved across the navigation.
 *
 * @package    core
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class same_site_navigation_page implements renderable, templatable {
    /** @var string URL to navigate to. */
    protected string $url;

    /**
     * Constructor.
     *
     * @param string $url the URL to navigate to.
     */
    public function __construct(string $url) {
        $this->url = $url;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data to be used by the template.
     */
    public function export_for_template(renderer_base $output) {
        $renderdata = new stdClass();
        $renderdata->url = $this->url;
        return $renderdata;
    }
}
