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

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * Amazon Nova Canvas AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amazon_nova_canvas_v1 extends base implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'amazon.nova-canvas-v1:0';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return get_string("model_{$this->get_model_name()}", 'aiprovider_awsbedrock');
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        // Specifies how strongly the generated image should adhere to the prompt.
        // Use a lower value to introduce more randomness in the generation.
        // Min: 1.1, Max: 10, Default: 6.5.
        $mform->addElement(
            'text',
            'cfgScale',
            get_string('settings_cfg_scale', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('cfgScale', 6.5);
        $mform->setType('cfgScale', PARAM_FLOAT);
        $mform->addHelpButton(
            elementname: 'cfgScale',
            identifier: 'settings_cfg_scale',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 1.1, 'max' => 10, 'default' => 6.5]
        );

        // Determines the initial noise setting for the generation process.
        // Changing the seed value while leaving all other parameters the same will
        // produce a totally new image that still adheres to your prompt, dimensions, and other settings.
        // Min: 0, Max: 858993459, Default: 12.
        $mform->addElement(
            'text',
            'seed',
            get_string('settings_seed_img', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('seed', 12);
        $mform->setType('seed', PARAM_INT);
        $mform->addHelpButton(
            elementname: 'seed',
            identifier: 'settings_seed_img',
            component: 'aiprovider_awsbedrock',
            a: ['min' => 0, 'max' => 858993459, 'default' => 12]
        );
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_IMAGE;
    }
}
