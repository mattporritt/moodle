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

namespace aiprovider_deepseek;

use aiprovider_deepseek\aimodel\deepseek_chat;
use aiprovider_deepseek\aimodel\deepseek_reasoner;
use aiprovider_deepseek\aimodel\deepseek_v4_flash;
use aiprovider_deepseek\aimodel\deepseek_v4_pro;

/**
 * Test Deepseek model classes.
 *
 * @package    aiprovider_deepseek
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \aiprovider_deepseek\aimodel\deepseek_chat
 * @covers     \aiprovider_deepseek\aimodel\deepseek_reasoner
 * @covers     \aiprovider_deepseek\aimodel\deepseek_v4_flash
 * @covers     \aiprovider_deepseek\aimodel\deepseek_v4_pro
 */
final class aimodel_test extends \advanced_testcase {
    /**
     * Test that the legacy chat/reasoner models are flagged deprecated with an EOL date.
     */
    public function test_legacy_models_are_deprecated(): void {
        $chat = new deepseek_chat();
        $this->assertTrue($chat->is_deprecated());
        $this->assertSame('2026-07-24', $chat->get_deprecation_eol());
        $this->assertStringContainsString('deprecated', $chat->get_model_selector_label());
        $this->assertStringContainsString('2026-07-24', $chat->get_model_selector_label());

        $reasoner = new deepseek_reasoner();
        $this->assertTrue($reasoner->is_deprecated());
        $this->assertSame('2026-07-24', $reasoner->get_deprecation_eol());
    }

    /**
     * Test that the new V4 models are registered and not deprecated.
     */
    public function test_v4_models_are_current(): void {
        $flash = new deepseek_v4_flash();
        $this->assertSame('deepseek-v4-flash', $flash->get_model_name());
        $this->assertFalse($flash->is_deprecated());
        $this->assertSame($flash->get_model_display_name(), $flash->get_model_selector_label());

        $pro = new deepseek_v4_pro();
        $this->assertSame('deepseek-v4-pro', $pro->get_model_name());
        $this->assertFalse($pro->is_deprecated());

        $classes = helper::get_model_classes();
        $this->assertContains(deepseek_v4_flash::class, $classes);
        $this->assertContains(deepseek_v4_pro::class, $classes);
    }
}
