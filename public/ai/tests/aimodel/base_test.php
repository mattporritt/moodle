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

namespace core_ai\aimodel;

/**
 * Test the shared model settings metadata and validation helpers on core_ai\aimodel\base.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aimodel\base
 */
final class base_test extends \advanced_testcase {
    /**
     * Get a stub model exposing settings with, and without, documented min/max metadata.
     *
     * @return base
     */
    private function get_stub_model(): base {
        return new class extends base {
            #[\Override]
            public function get_model_display_name(): string {
                return 'Stub model';
            }

            #[\Override]
            public function get_model_name(): string {
                return 'stub-model';
            }

            #[\Override]
            public function get_model_settings(): array {
                return [
                    'temperature' => self::build_setting(
                        'settings_temperature',
                        'core_ai',
                        PARAM_FLOAT,
                        'settings_temperature',
                        ['min' => 0, 'max' => 2, 'default' => 1],
                    ),
                    'stop_sequences' => self::build_setting(
                        'settings_stop_sequences',
                        'core_ai',
                        PARAM_TEXT,
                    ),
                ];
            }
        };
    }

    /**
     * Test that build_setting() produces the expected setting descriptor shape.
     */
    public function test_build_setting_shape(): void {
        $model = $this->get_stub_model();
        $settings = $model->get_model_settings();

        $this->assertSame('text', $settings['temperature']['elementtype']);
        $this->assertSame(PARAM_FLOAT, $settings['temperature']['type']);
        $this->assertSame(
            ['min' => 0, 'max' => 2, 'default' => 1],
            $settings['temperature']['help']['a'],
        );

        // A setting with no help identifier carries no help key at all.
        $this->assertArrayNotHasKey('help', $settings['stop_sequences']);
    }

    /**
     * Test that a value inside the documented range passes validation.
     */
    public function test_validate_model_settings_within_range(): void {
        $model = $this->get_stub_model();
        $errors = $model->validate_model_settings(['temperature' => '1.5']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that a value below the documented minimum is rejected.
     */
    public function test_validate_model_settings_below_min(): void {
        $model = $this->get_stub_model();
        $errors = $model->validate_model_settings(['temperature' => '-0.5']);
        $this->assertArrayHasKey('temperature', $errors);
    }

    /**
     * Test that a value above the documented maximum is rejected.
     */
    public function test_validate_model_settings_above_max(): void {
        $model = $this->get_stub_model();
        $errors = $model->validate_model_settings(['temperature' => '2.5']);
        $this->assertArrayHasKey('temperature', $errors);
    }

    /**
     * Test that a setting without documented min/max is never flagged.
     */
    public function test_validate_model_settings_no_range_defined(): void {
        $model = $this->get_stub_model();
        $errors = $model->validate_model_settings(['stop_sequences' => '999999']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that non-numeric submitted values are skipped rather than causing a fatal error.
     */
    public function test_validate_model_settings_non_numeric_value_skipped(): void {
        $model = $this->get_stub_model();
        $errors = $model->validate_model_settings(['temperature' => 'not-a-number']);
        $this->assertSame([], $errors);
    }

    /**
     * Test that empty/absent submitted values are skipped.
     */
    public function test_validate_model_settings_empty_value_skipped(): void {
        $model = $this->get_stub_model();
        $this->assertSame([], $model->validate_model_settings(['temperature' => '']));
        $this->assertSame([], $model->validate_model_settings([]));
    }
}
