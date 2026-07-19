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

namespace aiprovider_awsbedrock\aimodel;

/**
 * Test AWS Bedrock's existing model settings metadata, rendered help text, and range validation.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_awsbedrock\aimodel\ai21
 */
final class model_settings_test extends \advanced_testcase {
    /**
     * Get the AI21 Jamba-large model definition from the model catalog.
     *
     * @return \aiprovider_awsbedrock\model_definition
     */
    private function get_model(): \aiprovider_awsbedrock\model_definition {
        return ai21::get_models()['ai21.jamba-1-5-large-v1:0'];
    }

    /**
     * Test that the documented temperature range is present and renders into the help string.
     */
    public function test_temperature_help_string_replacement(): void {
        $settings = $this->get_model()->get_model_settings();
        $this->assertSame(0, $settings['temperature']['help']['a']['min']);
        $this->assertSame(2, $settings['temperature']['help']['a']['max']);

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
        $errors = $this->get_model()->validate_model_settings(['temperature' => '3.5']);
        $this->assertArrayHasKey('temperature', $errors);
    }

    /**
     * Test that a temperature value inside the documented range passes validation.
     */
    public function test_validate_model_settings_accepts_in_range_temperature(): void {
        $errors = $this->get_model()->validate_model_settings(['temperature' => '1.2']);
        $this->assertArrayNotHasKey('temperature', $errors);
    }

    /**
     * Test that max_tokens carries the documented AI21 Jamba bounds, shared across both
     * Jamba model sizes since AI21 documents the same limit for large and mini.
     */
    public function test_max_tokens_bounds(): void {
        $settings = ai21::get_models()['ai21.jamba-1-5-large-v1:0']->get_model_settings();
        $this->assertSame(['min' => 0, 'max' => 4096, 'default' => 4096], $settings['max_tokens']['help']['a']);
    }

    /**
     * Test that stop_sequences, which has no documented numeric range, is never flagged.
     */
    public function test_stop_has_no_range_validation(): void {
        $errors = $this->get_model()->validate_model_settings(['stop' => 'anything']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that every help identifier used by a model's settings has both the base
     * string and the '_help' string defined, as required by core\output\help_icon.
     */
    public function test_help_strings_are_complete(): void {
        $stringmanager = get_string_manager();
        foreach ($this->get_model()->get_model_settings() as $key => $setting) {
            if (!isset($setting['help'])) {
                continue;
            }
            $identifier = $setting['help']['identifier'];
            $component = $setting['help']['component'];
            $this->assertTrue(
                $stringmanager->string_exists($identifier, $component),
                "Missing help title string '{$identifier}' for setting '{$key}'",
            );
            $this->assertTrue(
                $stringmanager->string_exists($identifier . '_help', $component),
                "Missing help body string '{$identifier}_help' for setting '{$key}'",
            );
        }
    }
}
