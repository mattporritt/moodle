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

namespace aiprovider_deepseek\aimodel;

/**
 * Test model-specific settings metadata, rendered help text, and range validation.
 *
 * @package    aiprovider_deepseek
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_deepseek\aimodel\deepseek_chat
 * @covers     \aiprovider_deepseek\aimodel\deepseek_reasoner
 */
final class model_settings_test extends \advanced_testcase {
    /**
     * Test that deepseek-chat and deepseek-reasoner declare different documented max_tokens
     * limits for the same setting key, since reasoning tokens count towards the reasoner's output.
     */
    public function test_max_tokens_differs_per_model(): void {
        $chat = (new deepseek_chat())->get_model_settings();
        $reasoner = (new deepseek_reasoner())->get_model_settings();

        $this->assertSame(8192, $chat['max_tokens']['help']['a']['max']);
        $this->assertSame(32768, $reasoner['max_tokens']['help']['a']['max']);
    }

    /**
     * Test that the shared help string renders each model's own min/max/default placeholders.
     */
    public function test_help_string_replacement_reaches_rendered_text(): void {
        $chat = (new deepseek_chat())->get_model_settings();
        $reasoner = (new deepseek_reasoner())->get_model_settings();

        $chathelp = get_string(
            $chat['max_tokens']['help']['identifier'] . '_help',
            $chat['max_tokens']['help']['component'],
            (object) $chat['max_tokens']['help']['a'],
        );
        $reasonerhelp = get_string(
            $reasoner['max_tokens']['help']['identifier'] . '_help',
            $reasoner['max_tokens']['help']['component'],
            (object) $reasoner['max_tokens']['help']['a'],
        );

        $this->assertStringContainsString('8192', $chathelp);
        $this->assertStringContainsString('32768', $reasonerhelp);
        $this->assertNotSame($chathelp, $reasonerhelp);
    }

    /**
     * Test that submitting max_tokens above deepseek-chat's documented maximum is rejected.
     */
    public function test_validate_model_settings_rejects_out_of_range_chat(): void {
        $errors = (new deepseek_chat())->validate_model_settings(['max_tokens' => '20000']);
        $this->assertArrayHasKey('max_tokens', $errors);
    }

    /**
     * Test that the same submitted value is accepted for deepseek-reasoner.
     */
    public function test_validate_model_settings_accepts_same_value_for_reasoner(): void {
        $errors = (new deepseek_reasoner())->validate_model_settings(['max_tokens' => '20000']);
        $this->assertArrayNotHasKey('max_tokens', $errors);
    }

    /**
     * Test that deepseek-reasoner's temperature and top_p remain undocumented/unbounded,
     * since DeepSeek's API silently ignores these parameters for the reasoning model and
     * this ticket does not change that existing behaviour.
     */
    public function test_reasoner_ignores_temperature_and_top_p_range(): void {
        $errors = (new deepseek_reasoner())->validate_model_settings([
            'temperature' => '999',
            'top_p' => '999',
        ]);
        $this->assertSame([], $errors);
    }

    /**
     * Test that every help identifier used by a model's settings has both the base
     * string and the '_help' string defined, as required by core\output\help_icon.
     * Missing the base string renders as a raw "[[identifier]]" placeholder in the UI.
     */
    public function test_help_strings_are_complete(): void {
        $stringmanager = get_string_manager();
        foreach ([new deepseek_chat(), new deepseek_reasoner()] as $model) {
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
