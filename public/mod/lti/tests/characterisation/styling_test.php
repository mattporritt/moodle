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

/**
 * Characterisation test: the LTI styles reach the page of a default install.
 *
 * The LTI templates (the course tools page, the activity frame, the content selection dialogue and the registration pages) rely
 * on styles that are shipped with the plugin today and with the theme's compiled CSS after the migration. Either way the CSS
 * a theme serves has to contain them. The selectors are the class names the templates use, not the files that define them.
 *
 * Only Boost, the default theme, is checked: compiling the SCSS of another theme takes half a minute.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class styling_test extends \advanced_testcase {
    /**
     * The CSS served for the default theme styles the course tools page, the activity frame and the content selection dialogue.
     */
    public function test_lti_styles_are_served(): void {
        $this->resetAfterTest();
        $themename = 'boost';
        $css = \theme_config::load($themename)->get_css_content();

        foreach (['.tool-card', '.tool-card-content', '.contentitem-modal', '.ltiframe', '#choice-list'] as $selector) {
            $this->assertStringContainsString($selector, $css, "The CSS of $themename has no style for $selector");
        }
    }
}
