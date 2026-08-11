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
 * Event observers for image occurrence cleanup.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\report_imagealt\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\report_imagealt\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_category_created',
        'callback' => '\report_imagealt\observer::course_category_changed',
    ],
    [
        'eventname' => '\core\event\course_category_updated',
        'callback' => '\report_imagealt\observer::course_category_changed',
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\course_section_created',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_created',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_updated',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_deleted',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_created',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_updated',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_deleted',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\question_created',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\question_updated',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\question_deleted',
        'callback' => '\report_imagealt\observer::course_content_changed',
    ],
    [
        'eventname' => '\core\event\user_created',
        'callback' => '\report_imagealt\observer::user_changed',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\report_imagealt\observer::user_changed',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\report_imagealt\observer::user_deleted',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\report_imagealt\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\course_category_deleted',
        'callback' => '\report_imagealt\observer::course_category_deleted',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\report_imagealt\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_section_deleted',
        'callback' => '\report_imagealt\observer::course_section_deleted',
    ],
];
