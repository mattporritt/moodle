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

/**
 * Render a page which navigates to a target URL from the current (same-site) document.
 *
 * @package    enrol_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\output;

use core\output\same_site_navigation_page;

/**
 * Render a page which navigates to a target URL from the current (same-site) document.
 *
 * A Legacy LTI (1.1/1.2) launch arrives as a cross-site request. Redirecting straight to the
 * target resource from within that same request/redirect chain means the SameSite=Lax session
 * cookie set by complete_user_login() moments earlier is not sent when the launch is presented in
 * an iframe (Embed), so the user sees a login screen. Rendering this page first, then navigating
 * to the target URL from it, starts a fresh navigation whose initiator is this Moodle page rather
 * than the original cross-site request, so the browser treats it as same-site and includes the
 * cookie.
 *
 * This uses the shared same_site_navigation_page base also used for LTI 1.3/OIDC in
 * mod_lti\output\repost_crosssite_page. The two deliberately trigger the navigation differently:
 * this page navigates via a plain GET link, equivalent to what redirect() would have done, while
 * mod_lti's re-posts the original POST payload to itself. Turning this GET navigation into a POST
 * (for example by reusing repost_crosssite_page with an empty $post array) would risk behaving
 * differently on target resources that branch on request method, so the two keep separate
 * mustache templates for that part.
 *
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cross_site_launch_page extends same_site_navigation_page {
}
