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
 * Meta Llama 3.2 90B Instruct  AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meta_llama3_2_90b_instruct_v1 extends meta_llama3_8b_instruct_v1 implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'meta.llama3-2-90b-instruct-v1:0';
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        parent::add_model_settings($mform);

        // Add the cross region inference setting.
        $mform->addElement(
            'text',
            'cross_region_inference',
            get_string('settings_cross_region_inference', 'aiprovider_awsbedrock'),
        );
        $mform->setDefault('cross_region_inference', 'us.meta.llama3-2-90b-instruct-v1:0');
        $mform->setType('cross_region_inference', PARAM_TEXT);
        $mform->addHelpButton('cross_region_inference', 'settings_cross_region_inference', 'aiprovider_awsbedrock');
    }

}
