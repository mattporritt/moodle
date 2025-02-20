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
 * Claude 3 Haiku  AI model.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class anthropic_claude_3_haiku_v1 extends anthropic_claude_3_5_sonnet_v1 implements awsbedrock_base {

    #[\Override]
    public function get_model_name(): string {
        return 'anthropic.claude-3-haiku-20240307-v1:0';
    }
}
