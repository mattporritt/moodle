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

namespace aiprovider_gemini;

use aiprovider_gemini\aimodel\gemini25flash;
use aiprovider_gemini\aimodel\gemini25flashlite;
use aiprovider_gemini\aimodel\gemini25flashpro;
use aiprovider_gemini\aimodel\gemini31flashlite;
use aiprovider_gemini\aimodel\gemini35flash;
use aiprovider_gemini\aimodel\imagen40generate001;

/**
 * Test Gemini model classes.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \aiprovider_gemini\aimodel\gemini25flash
 * @covers     \aiprovider_gemini\aimodel\gemini25flashlite
 * @covers     \aiprovider_gemini\aimodel\gemini25flashpro
 * @covers     \aiprovider_gemini\aimodel\imagen40generate001
 * @covers     \aiprovider_gemini\aimodel\gemini35flash
 * @covers     \aiprovider_gemini\aimodel\gemini31flashlite
 * @covers     \aiprovider_gemini\helper
 */
final class aimodel_test extends \advanced_testcase {
    /**
     * Test that the 2.5 generation models and Imagen 4.0 are flagged deprecated with an EOL date.
     */
    public function test_deprecated_models(): void {
        $flash = new gemini25flash();
        $this->assertTrue($flash->is_deprecated());
        $this->assertSame('2026-10-16', $flash->get_deprecation_eol());
        $this->assertStringContainsString('deprecated', $flash->get_model_selector_label());

        $flashlite = new gemini25flashlite();
        $this->assertTrue($flashlite->is_deprecated());
        $this->assertSame('2026-10-16', $flashlite->get_deprecation_eol());

        $pro = new gemini25flashpro();
        $this->assertTrue($pro->is_deprecated());
        $this->assertSame('2026-10-16', $pro->get_deprecation_eol());

        $imagen = new imagen40generate001();
        $this->assertTrue($imagen->is_deprecated());
        $this->assertSame('2026-08-17', $imagen->get_deprecation_eol());
    }

    /**
     * Test that the new 3.x generation models are registered and not deprecated.
     */
    public function test_new_models_are_current(): void {
        $flash = new gemini35flash();
        $this->assertSame('gemini-3.5-flash', $flash->get_model_name());
        $this->assertFalse($flash->is_deprecated());
        $this->assertSame($flash->get_model_display_name(), $flash->get_model_selector_label());

        $flashlite = new gemini31flashlite();
        $this->assertSame('gemini-3.1-flash-lite', $flashlite->get_model_name());
        $this->assertFalse($flashlite->is_deprecated());

        $classes = helper::get_model_classes();
        $this->assertContains(gemini35flash::class, $classes);
        $this->assertContains(gemini31flashlite::class, $classes);
    }
}
