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

namespace aiprovider_ollama;

use aiprovider_ollama\aimodel\llama33;
use aiprovider_ollama\aimodel\qwen25;

/**
 * Test Ollama model classes.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \aiprovider_ollama\aimodel\llama33
 * @covers     \aiprovider_ollama\aimodel\qwen25
 * @covers     \aiprovider_ollama\helper
 */
final class aimodel_test extends \advanced_testcase {
    /**
     * Test that Ollama offers a couple of popular current models, none deprecated.
     */
    public function test_models_are_current(): void {
        $llama = new llama33();
        $this->assertFalse($llama->is_deprecated());

        $qwen = new qwen25();
        $this->assertSame('qwen2.5', $qwen->get_model_name());
        $this->assertFalse($qwen->is_deprecated());
        $this->assertSame($qwen->get_model_display_name(), $qwen->get_model_selector_label());

        $classes = helper::get_model_classes();
        $this->assertContains(llama33::class, $classes);
        $this->assertContains(qwen25::class, $classes);
    }
}
