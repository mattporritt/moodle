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

namespace aiprovider_anthropic\form;

use aiprovider_anthropic\aimodel\custommodel;
use aiprovider_anthropic\testcase_helper_trait;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../testcase_helper_trait.php');

/**
 * Tests for the Anthropic Claude provider action settings form.
 *
 * @package    aiprovider_anthropic
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\aiprovider_anthropic\form\action_form::class)]
#[CoversClass(\aiprovider_anthropic\form\action_generate_text_form::class)]
final class action_form_test extends \advanced_testcase {
    use testcase_helper_trait;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the model selector lists every bundled model plus the custom option last.
     */
    public function test_model_list(): void {
        $form = $this->build_form();
        $method = new \ReflectionMethod($form, 'get_model_list');
        $models = $method->invoke($form);

        $this->assertSame([
            'claude-haiku-4-5-20251001',
            'claude-opus-4-5-20251101',
            'claude-opus-4-8',
            'claude-opus-5',
            'claude-sonnet-4-5-20250929',
            'claude-sonnet-5',
            custommodel::MODEL_NAME,
        ], array_keys($models));
        $this->assertEquals(get_string('custom', 'core_form'), $models[custommodel::MODEL_NAME]);
    }

    /**
     * Test a bundled model comes back selected in the model selector.
     */
    public function test_bundled_model_selection(): void {
        $mform = $this->build_mform(['model' => 'claude-opus-5']);

        $this->assertEquals('claude-opus-5', self::get_value($mform, 'modeltemplate'));
        $this->assertEquals('claude-opus-5', self::get_value($mform, 'model'));
        $this->assertEmpty(self::get_value($mform, 'custommodel'));
    }

    /**
     * Test an unlisted stored model comes back as the custom option, with the name preserved.
     */
    public function test_custom_model_selection(): void {
        $mform = $this->build_mform(['model' => 'claude-not-bundled-yet']);

        $this->assertEquals(custommodel::MODEL_NAME, self::get_value($mform, 'modeltemplate'));
        $this->assertEquals('claude-not-bundled-yet', self::get_value($mform, 'model'));
        $this->assertEquals('claude-not-bundled-yet', self::get_value($mform, 'custommodel'));
    }

    /**
     * Test the custom model name is required when the custom option is selected.
     */
    public function test_validation_requires_custom_model_name(): void {
        $form = $this->build_form();

        $errors = $form->validation(['modeltemplate' => custommodel::MODEL_NAME, 'custommodel' => ''], []);
        $this->assertArrayHasKey('custommodel', $errors);

        $errors = $form->validation(['modeltemplate' => custommodel::MODEL_NAME, 'custommodel' => '  '], []);
        $this->assertArrayHasKey('custommodel', $errors);

        $errors = $form->validation(
            ['modeltemplate' => custommodel::MODEL_NAME, 'custommodel' => 'claude-not-bundled-yet'],
            [],
        );
        $this->assertArrayNotHasKey('custommodel', $errors);

        $errors = $form->validation(['modeltemplate' => 'claude-opus-5', 'custommodel' => ''], []);
        $this->assertArrayNotHasKey('custommodel', $errors);
    }

    /**
     * Test the selector helper fields are not stored as action settings.
     */
    public function test_get_defaults_excludes_helper_fields(): void {
        $defaults = $this->build_form()->get_defaults();

        $this->assertArrayNotHasKey('modeltemplate', $defaults);
        $this->assertArrayNotHasKey('custommodel', $defaults);
        $this->assertArrayHasKey('model', $defaults);
    }

    /**
     * Test submitting the form for one model keeps that model's own endpoint and generation
     * settings, separate from any other model's previously stored settings (MDL-89680).
     */
    public function test_get_data_stores_settings_per_model(): void {
        $modelsettings = [
            'claude-sonnet-4-5-20250929' => [
                'endpoint' => 'https://sonnet.example.com/v1/messages',
                'max_tokens' => 4096,
            ],
        ];
        // Moodleform reads the submission at construction time, so the mock submission must
        // be in place before the form is built.
        action_generate_text_form::mock_submit([
            'modeltemplate' => 'claude-opus-5',
            'model' => 'claude-opus-5',
            'custommodel' => '',
            'endpoint' => 'https://opus.example.com/v1/messages',
            'max_tokens' => '2048',
            'systeminstruction' => 'Test instruction',
            'action' => \core_ai\aiactions\generate_text::class,
            'provider' => 'aiprovider_anthropic',
            'providerid' => 1,
        ]);

        $form = $this->build_form(modelsettings: $modelsettings);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame([
            'endpoint' => 'https://opus.example.com/v1/messages',
            'max_tokens' => '2048',
        ], $data->modelsettings['claude-opus-5']);
    }

    /**
     * Test the model selector only exposes the currently selected model's stored settings to
     * the modelchooser JS, so switching models does not leak another model's values client-side.
     */
    public function test_model_selector_exposes_current_models_stored_settings(): void {
        $modelsettings = [
            'claude-opus-5' => ['endpoint' => 'https://opus.example.com/v1/messages', 'max_tokens' => 111],
            'claude-sonnet-5' => ['endpoint' => 'https://sonnet.example.com/v1/messages', 'max_tokens' => 222],
        ];
        $mform = $this->build_mform(['model' => 'claude-opus-5'], $modelsettings);

        $selector = $mform->getElement('modeltemplate');
        $stored = json_decode($selector->getAttribute('data-storedmodelsettings'), true);

        $this->assertSame(['claude-opus-5' => $modelsettings['claude-opus-5']], $stored);
    }

    /**
     * Test submitting the form for the "Custom" model option stores its settings under the
     * "custom" selector value, not the admin-entered model name, so the modelchooser JS lookup
     * by selector value finds them again (MDL-89680).
     */
    public function test_get_data_stores_custom_model_settings_under_custom_key(): void {
        action_generate_text_form::mock_submit([
            'modeltemplate' => custommodel::MODEL_NAME,
            'model' => 'my-custom-claude-model',
            'custommodel' => 'my-custom-claude-model',
            'endpoint' => 'https://custom.example.com/v1/messages',
            'max_tokens' => '3333',
            'temperature' => '0.5',
            'systeminstruction' => 'Test instruction',
            'action' => \core_ai\aiactions\generate_text::class,
            'provider' => 'aiprovider_anthropic',
            'providerid' => 1,
        ]);

        $form = $this->build_form();
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertArrayNotHasKey('my-custom-claude-model', $data->modelsettings);
        $this->assertSame([
            'endpoint' => 'https://custom.example.com/v1/messages',
            'max_tokens' => '3333',
            'temperature' => '0.5',
        ], $data->modelsettings[custommodel::MODEL_NAME]);
    }

    /**
     * Test that reselecting the "Custom" model option after saving restores its previously
     * stored settings, mirroring how a bundled model's settings are restored (MDL-89680).
     */
    public function test_model_selector_exposes_stored_custom_model_settings(): void {
        $modelsettings = [
            custommodel::MODEL_NAME => [
                'endpoint' => 'https://custom.example.com/v1/messages',
                'max_tokens' => 3333,
                'temperature' => 0.5,
            ],
            'claude-opus-5' => ['endpoint' => 'https://opus.example.com/v1/messages', 'max_tokens' => 111],
        ];
        $mform = $this->build_mform(['model' => 'my-custom-claude-model'], $modelsettings);

        $selector = $mform->getElement('modeltemplate');
        $stored = json_decode($selector->getAttribute('data-storedmodelsettings'), true);

        $this->assertSame([custommodel::MODEL_NAME => $modelsettings[custommodel::MODEL_NAME]], $stored);
    }

    /**
     * Get an element's current value, flattening the array a select element returns.
     *
     * @param \MoodleQuickForm $mform The form to read from.
     * @param string $elementname The element to read.
     * @return string|null
     */
    private static function get_value(\MoodleQuickForm $mform, string $elementname): ?string {
        $value = $mform->getElementValue($elementname);

        return is_array($value) ? reset($value) : $value;
    }

    /**
     * Build a generate_text action settings form.
     *
     * @param array $settings Action settings to configure the provider with.
     * @param array $modelsettings Stored per-model settings, keyed by model name.
     * @return action_generate_text_form
     */
    private function build_form(array $settings = [], array $modelsettings = []): action_generate_text_form {
        $provider = $this->create_provider(
            actionclass: \core_ai\aiactions\generate_text::class,
            actionconfig: $settings,
        );

        return new action_generate_text_form(customdata: [
            'actionconfig' => [
                'settings' => $provider->actionconfig[\core_ai\aiactions\generate_text::class]['settings'],
                'modelsettings' => $modelsettings,
            ],
            'actionname' => 'generate_text',
            'action' => \core_ai\aiactions\generate_text::class,
            'providerid' => 1,
            'providername' => 'aiprovider_anthropic',
        ]);
    }

    /**
     * Build a generate_text action settings form and return the underlying MoodleQuickForm.
     *
     * @param array $settings Action settings to configure the provider with.
     * @param array $modelsettings Stored per-model settings, keyed by model name.
     * @return \MoodleQuickForm
     */
    private function build_mform(array $settings = [], array $modelsettings = []): \MoodleQuickForm {
        $form = $this->build_form($settings, $modelsettings);
        // Mirrors moodleform::display(), which finalizes the definition on first render.
        $form->definition_after_data();
        $property = new \ReflectionProperty($form, '_form');

        return $property->getValue($form);
    }
}
