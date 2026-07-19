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

namespace aiprovider_openai\aimodel;

/**
 * Test model-specific settings metadata, rendered help text, and range validation.
 *
 * @package    aiprovider_openai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_openai\aimodel\gpt4o
 * @covers     \aiprovider_openai\aimodel\o1
 */
final class model_settings_test extends \advanced_testcase {
    /**
     * Test that gpt-4o and o1 declare different documented ranges for the same setting key,
     * reflecting their different provider-documented output token limits.
     */
    public function test_max_completion_tokens_differs_per_model(): void {
        $gpt4o = (new gpt4o())->get_model_settings();
        $o1 = (new o1())->get_model_settings();

        $this->assertSame(16384, $gpt4o['max_completion_tokens']['help']['a']['max']);
        $this->assertSame(100000, $o1['max_completion_tokens']['help']['a']['max']);
    }

    /**
     * Test that the shared help string renders each model's own min/max placeholders.
     */
    public function test_help_string_replacement_reaches_rendered_text(): void {
        $gpt4o = (new gpt4o())->get_model_settings();
        $o1 = (new o1())->get_model_settings();

        $gpt4ohelp = get_string(
            $gpt4o['max_completion_tokens']['help']['identifier'] . '_help',
            $gpt4o['max_completion_tokens']['help']['component'],
            (object) $gpt4o['max_completion_tokens']['help']['a'],
        );
        $o1help = get_string(
            $o1['max_completion_tokens']['help']['identifier'] . '_help',
            $o1['max_completion_tokens']['help']['component'],
            (object) $o1['max_completion_tokens']['help']['a'],
        );

        $this->assertStringContainsString('16384', $gpt4ohelp);
        $this->assertStringContainsString('100000', $o1help);
        $this->assertNotSame($gpt4ohelp, $o1help);
    }

    /**
     * Test that submitting max_completion_tokens above gpt-4o's documented maximum is rejected.
     */
    public function test_validate_model_settings_rejects_out_of_range_gpt4o(): void {
        $model = new gpt4o();
        $errors = $model->validate_model_settings(['max_completion_tokens' => '20000']);
        $this->assertArrayHasKey('max_completion_tokens', $errors);
    }

    /**
     * Test that the same submitted value is accepted for o1, since its documented maximum is higher.
     */
    public function test_validate_model_settings_accepts_same_value_for_o1(): void {
        $model = new o1();
        $errors = $model->validate_model_settings(['max_completion_tokens' => '20000']);
        $this->assertArrayNotHasKey('max_completion_tokens', $errors);
    }

    /**
     * Test that o1's top_p setting is left without documented bounds and is never flagged,
     * since OpenAI fixes this parameter for o1 and this ticket does not change that behaviour.
     */
    public function test_o1_top_p_has_no_documented_range(): void {
        $settings = (new o1())->get_model_settings();
        $this->assertArrayNotHasKey('a', $settings['top_p']['help'] ?? []);

        $errors = (new o1())->validate_model_settings(['top_p' => '999']);
        $this->assertArrayNotHasKey('top_p', $errors);
    }

    /**
     * Test that every help identifier used by a model's settings has both the base
     * string and the '_help' string defined, as required by core\output\help_icon.
     * Missing the base string renders as a raw "[[identifier]]" placeholder in the UI.
     */
    public function test_help_strings_are_complete(): void {
        $stringmanager = get_string_manager();
        foreach ([new gpt4o(), new o1()] as $model) {
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
