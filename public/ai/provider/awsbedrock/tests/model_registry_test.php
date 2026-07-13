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

namespace aiprovider_awsbedrock;

/**
 * Test the AWS Bedrock model registry and model deprecation/removal handling.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \aiprovider_awsbedrock\model_registry
 * @covers     \aiprovider_awsbedrock\model_definition
 */
final class model_registry_test extends \advanced_testcase {
    /**
     * Test that deprecated-but-available models are flagged with an EOL date.
     */
    public function test_deprecated_models(): void {
        $deprecated = [
            'ai21.jamba-1-5-large-v1:0' => '2026-11-26',
            'ai21.jamba-1-5-mini-v1:0' => '2026-11-26',
            'amazon.nova-canvas-v1:0' => '2026-09-30',
            'anthropic.claude-3-5-sonnet-20240620-v1:0' => '2026-07-30',
            'anthropic.claude-3-5-sonnet-20241022-v2:0' => '2026-07-30',
            'anthropic.claude-3-haiku-20240307-v1:0' => '2026-09-10',
            'anthropic.claude-sonnet-4-20250514-v1:0' => '2026-10-14',
        ];

        foreach ($deprecated as $modelname => $eol) {
            $model = model_registry::get_model($modelname);
            $this->assertNotNull($model, "Expected model {$modelname} to still be registered.");
            $this->assertTrue($model->is_deprecated(), "Expected {$modelname} to be flagged deprecated.");
            $this->assertSame($eol, $model->get_deprecation_eol());
            $this->assertStringContainsString('deprecated', $model->get_model_selector_label());
        }
    }

    /**
     * Test that currently active models are not flagged deprecated.
     */
    public function test_active_models_are_not_deprecated(): void {
        $active = [
            'amazon.nova-pro-v1:0',
            'amazon.nova-lite-v1:0',
            'amazon.nova-micro-v1:0',
            'amazon.titan-image-generator-v2:0',
            'anthropic.claude-haiku-4-5-20251001-v1:0',
            'anthropic.claude-sonnet-4-5-20250929-v1:0',
            'anthropic.claude-3-7-sonnet-20250219-v1:0',
            'anthropic.claude-sonnet-5',
        ];

        foreach ($active as $modelname) {
            $model = model_registry::get_model($modelname);
            $this->assertNotNull($model, "Expected model {$modelname} to be registered.");
            $this->assertFalse($model->is_deprecated(), "Expected {$modelname} to not be deprecated.");
        }
    }

    /**
     * Test that Stability AI's general text-to-image models have been fully removed.
     */
    public function test_removed_models(): void {
        $removed = [
            'stability.stable-image-core-v1:1',
            'stability.stable-image-ultra-v1:1',
            'stability.sd3-5-large-v1:0',
        ];

        foreach ($removed as $modelname) {
            $this->assertTrue(model_registry::is_model_removed($modelname));
            $this->assertNull(model_registry::get_model($modelname));
        }
    }
}
