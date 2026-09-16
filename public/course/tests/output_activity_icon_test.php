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

namespace core_course\output;

/**
 * Tests for the activity_icon renderable.
 *
 * @package    core_course
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(activity_icon::class)]
final class output_activity_icon_test extends \advanced_testcase {
    /**
     * A module with a monologo icon should use the CSS mask rendering, since it can be safely recoloured.
     */
    public function test_use_mask_for_filterable_icon(): void {
        global $PAGE;

        $this->resetAfterTest();
        $icon = activity_icon::from_modname('forum');

        $this->assertTrue($icon->use_mask($PAGE->get_renderer('core')));
    }

    /**
     * A branded module (declares mod_xxx_is_branded() === true) must keep its own colours, so it must not be
     * rendered via a CSS mask (see MDL-84630).
     */
    public function test_use_mask_false_for_branded_icon(): void {
        global $PAGE;

        $this->resetAfterTest();
        $icon = activity_icon::from_modname('h5pactivity');

        $this->assertTrue($icon->is_branded());
        $this->assertFalse($icon->use_mask($PAGE->get_renderer('core')));
    }

    /**
     * When colourize is explicitly disabled, the icon must not be rendered via a CSS mask even if it would
     * otherwise be filterable.
     */
    public function test_use_mask_false_when_colourize_disabled(): void {
        global $PAGE;

        $this->resetAfterTest();
        $icon = activity_icon::from_modname('forum')->set_colourize(false);

        $this->assertFalse($icon->use_mask($PAGE->get_renderer('core')));
    }

    /**
     * The exported template context must expose the usemask flag so the mustache template can choose between a
     * masked <div> and a plain <img>.
     */
    public function test_export_for_template_includes_usemask(): void {
        global $PAGE;

        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('core');
        $icon = activity_icon::from_modname('forum');

        $data = $icon->export_for_template($renderer);

        $this->assertArrayHasKey('usemask', $data);
        $this->assertTrue($data['usemask']);
    }
}
