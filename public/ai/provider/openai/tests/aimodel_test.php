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

namespace aiprovider_openai;

use aiprovider_openai\aimodel\gpt4o;
use aiprovider_openai\aimodel\gptimage1;
use aiprovider_openai\aimodel\gptimage2;
use aiprovider_openai\aimodel\o1;

/**
 * Test OpenAI model classes.
 *
 * @package    aiprovider_openai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \aiprovider_openai\aimodel\gpt4o
 * @covers     \aiprovider_openai\aimodel\o1
 * @covers     \aiprovider_openai\aimodel\gptimage1
 * @covers     \aiprovider_openai\aimodel\gptimage2
 * @covers     \aiprovider_openai\helper
 */
final class aimodel_test extends \advanced_testcase {
    /**
     * Test that deprecated-but-available models are flagged with an EOL date.
     */
    public function test_deprecated_models(): void {
        $gpt4o = new gpt4o();
        $this->assertTrue($gpt4o->is_deprecated());
        $this->assertSame('2026-10-23', $gpt4o->get_deprecation_eol());
        $this->assertStringContainsString('deprecated', $gpt4o->get_model_selector_label());

        // O1 extends gpt4o and explicitly overrides the deprecation flags with a matching EOL date.
        $o1 = new o1();
        $this->assertTrue($o1->is_deprecated());
        $this->assertSame('2026-10-23', $o1->get_deprecation_eol());

        $gptimage1 = new gptimage1();
        $this->assertTrue($gptimage1->is_deprecated());
        $this->assertSame('2026-12-01', $gptimage1->get_deprecation_eol());
    }

    /**
     * Test that gpt-image-2 is registered and not deprecated.
     */
    public function test_gptimage2_is_current(): void {
        $model = new gptimage2();
        $this->assertSame('gpt-image-2', $model->get_model_name());
        $this->assertFalse($model->is_deprecated());
        $this->assertSame($model->get_model_display_name(), $model->get_model_selector_label());
        $this->assertContains(gptimage2::class, helper::get_model_classes());
    }

    /**
     * Test that the fully removed dall-e-3 model class no longer exists and is flagged as removed.
     */
    public function test_dalle3_is_removed(): void {
        $this->assertFalse(class_exists('\aiprovider_openai\aimodel\dalle3'));
        $this->assertTrue(helper::is_model_removed('dall-e-3'));
        $this->assertNull(helper::get_model_class('dall-e-3'));
    }
}
