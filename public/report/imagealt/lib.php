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
 * Navigation callbacks for the image alternative text report.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the report to course navigation.
 *
 * @param navigation_node $navigation Course navigation.
 * @param stdClass $course Course record.
 * @param context $context Course context.
 */
function report_imagealt_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context,
): void {
    if (!has_capability('report/imagealt:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('pluginname', 'report_imagealt'),
        new moodle_url('/report/imagealt/index.php', ['contextid' => $context->id]),
        navigation_node::TYPE_SETTING,
        null,
        'reportimagealt',
        new pix_icon('i/report', ''),
    );
}

/**
 * Add the report to course category navigation.
 *
 * @param navigation_node $navigation Category navigation.
 * @param context $context Category context.
 */
function report_imagealt_extend_navigation_category_settings(
    navigation_node $navigation,
    context $context,
): void {
    if (!has_capability('report/imagealt:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('pluginname', 'report_imagealt'),
        new moodle_url('/report/imagealt/index.php', ['contextid' => $context->id]),
        navigation_node::NODETYPE_LEAF,
        null,
        'reportimagealt',
        new pix_icon('i/report', ''),
    );
}
