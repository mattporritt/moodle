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

namespace aiprovider_ollama\aimodel;

/**
 * Test model settings metadata, rendered help text, and range validation.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_ollama\aimodel\llama33
 */
final class model_settings_test extends \advanced_testcase {
    /**
     * Test that mirostat's documented enum range renders into its help string.
     */
    public function test_mirostat_help_string_replacement(): void {
        $settings = (new llama33())->get_model_settings();
        $help = get_string(
            $settings['mirostat']['help']['identifier'] . '_help',
            $settings['mirostat']['help']['component'],
            (object) $settings['mirostat']['help']['a'],
        );
        $this->assertStringContainsString('2', $help);
    }

    /**
     * Test that a mirostat value outside its documented 0-2 range is rejected.
     */
    public function test_validate_model_settings_rejects_out_of_range_mirostat(): void {
        $errors = (new llama33())->validate_model_settings(['mirostat' => '5']);
        $this->assertArrayHasKey('mirostat', $errors);
    }

    /**
     * Test that temperature, which Ollama documents a default for but does not bound,
     * is never flagged by range validation.
     */
    public function test_temperature_has_no_range_validation(): void {
        $errors = (new llama33())->validate_model_settings(['temperature' => '999']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that top_p's documented 0.0-1.0 bound rejects an out-of-range value.
     */
    public function test_validate_model_settings_rejects_out_of_range_top_p(): void {
        $errors = (new llama33())->validate_model_settings(['top_p' => '1.5']);
        $this->assertArrayHasKey('top_p', $errors);
    }

    /**
     * Test that every help identifier used by a model's settings has both the base
     * string and the '_help' string defined, as required by core\output\help_icon.
     * Missing the base string renders as a raw "[[identifier]]" placeholder in the UI.
     */
    public function test_help_strings_are_complete(): void {
        $stringmanager = get_string_manager();
        $model = new llama33();
        foreach ($model->get_model_settings() as $key => $setting) {
            if (!isset($setting['help'])) {
                continue;
            }
            $identifier = $setting['help']['identifier'];
            $component = $setting['help']['component'];
            $this->assertTrue(
                $stringmanager->string_exists($identifier, $component),
                "Missing help title string '{$identifier}' for setting '{$key}' on " . get_class($model),
            );
            $this->assertTrue(
                $stringmanager->string_exists($identifier . '_help', $component),
                "Missing help body string '{$identifier}_help' for setting '{$key}' on " . get_class($model),
            );
        }
    }
}
