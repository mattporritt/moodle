<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Behat data generator for the image alternative text report.
 *
 * @package    report_imagealt
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_report_imagealt_generator extends behat_generator_base {
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'images' => [
                'singular' => 'image',
                'datagenerator' => 'image',
                'required' => ['course'],
                'switchids' => ['course' => 'courseid'],
            ],
            'suggestions' => [
                'singular' => 'suggestion',
                'datagenerator' => 'suggestion',
                'required' => ['course', 'filename'],
                'switchids' => ['course' => 'courseid', 'user' => 'userid'],
            ],
        ];
    }
}
