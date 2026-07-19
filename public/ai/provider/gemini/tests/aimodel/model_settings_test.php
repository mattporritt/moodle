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

namespace aiprovider_gemini\aimodel;

/**
 * Test model settings metadata, rendered help text, and range validation.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_gemini\aimodel\gemini25flash
 */
final class model_settings_test extends \advanced_testcase {
    /**
     * Test that the documented temperature range is present and renders into the help string.
     */
    public function test_temperature_help_string_replacement(): void {
        $settings = (new gemini25flash())->get_model_settings();
        $this->assertSame(0, $settings['temperature']['help']['a']['min']);
        $this->assertSame(2.0, $settings['temperature']['help']['a']['max']);

        $help = get_string(
            $settings['temperature']['help']['identifier'] . '_help',
            $settings['temperature']['help']['component'],
            (object) $settings['temperature']['help']['a'],
        );
        $this->assertStringContainsString('2', $help);
    }

    /**
     * Test that a temperature value outside the documented range is rejected.
     */
    public function test_validate_model_settings_rejects_out_of_range_temperature(): void {
        $errors = (new gemini25flash())->validate_model_settings(['temperature' => '3.5']);
        $this->assertArrayHasKey('temperature', $errors);
    }

    /**
     * Test that a temperature value inside the documented range passes validation.
     */
    public function test_validate_model_settings_accepts_in_range_temperature(): void {
        $errors = (new gemini25flash())->validate_model_settings(['temperature' => '1.2']);
        $this->assertArrayNotHasKey('temperature', $errors);
    }

    /**
     * Test that stop_sequences, which has no documented numeric range, is never flagged.
     */
    public function test_stop_sequences_has_no_range_validation(): void {
        $errors = (new gemini25flash())->validate_model_settings(['stop_sequences' => 'anything']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that every help identifier used by a model's settings has both the base
     * string and the '_help' string defined, as required by core\output\help_icon.
     * Missing the base string renders as a raw "[[identifier]]" placeholder in the UI.
     */
    public function test_help_strings_are_complete(): void {
        $stringmanager = get_string_manager();
        $models = [new gemini25flash(), new gemini25flashpro(), new gemini25flashlite()];
        foreach ($models as $model) {
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
}
