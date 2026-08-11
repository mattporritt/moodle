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

namespace report_imagealt\local\content;

use context_course;
use context_coursecat;
use moodle_url;
use report_imagealt\local\scope;

/**
 * Editable course-category, course, and section descriptions.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_provider extends base_provider {
    #[\Override]
    public function get_key(): string {
        return 'core_course';
    }

    #[\Override]
    public function get_items(\context $scope): iterable {
        global $DB;

        $categoryids = scope::get_category_ids($scope);
        if ($scope->contextlevel !== CONTEXT_COURSE) {
            $categories = null;
            if ($categoryids === null) {
                $categories = $DB->get_recordset_select('course_categories', 'description <> :empty', ['empty' => '']);
            } else if ($categoryids) {
                [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'category');
                $categories = $DB->get_recordset_select('course_categories', "id {$insql} AND description <> :empty", $params + [
                    'empty' => '',
                ]);
            }
            if ($categories) {
                try {
                    foreach ($categories as $category) {
                        yield $this->build_category_item($category);
                    }
                } finally {
                    $categories->close();
                }
            }
        }

        [$coursesql, $courseparams] = scope::get_course_condition('id', $scope, 'course');
        $courses = $DB->get_recordset_select(
            'course',
            "{$coursesql} AND summary <> :empty",
            $courseparams + ['empty' => ''],
        );
        try {
            foreach ($courses as $course) {
                yield $this->build_course_item($course);
            }
        } finally {
            $courses->close();
        }

        [$sectionsql, $sectionparams] = scope::get_course_condition('course', $scope, 'section');
        $sections = $DB->get_recordset_select(
            'course_sections',
            "{$sectionsql} AND summary <> :empty",
            $sectionparams + ['empty' => ''],
        );
        try {
            foreach ($sections as $section) {
                yield $this->build_section_item($section);
            }
        } finally {
            $sections->close();
        }
    }

    #[\Override]
    public function get_item(string $key): ?content_item {
        global $DB;

        $parts = explode(':', $key);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            return null;
        }
        [$type, $id] = $parts;
        $id = (int) $id;

        return match ($type) {
            'category' => ($record = $DB->get_record('course_categories', ['id' => $id]))
                ? $this->build_category_item($record) : null,
            'course' => ($record = $DB->get_record('course', ['id' => $id]))
                ? $this->build_course_item($record) : null,
            'section' => ($record = $DB->get_record('course_sections', ['id' => $id]))
                ? $this->build_section_item($record) : null,
            default => null,
        };
    }

    #[\Override]
    public function can_edit(content_item $item, int $userid): bool {
        $context = \context::instance_by_id($item->contextid);
        $capability = str_starts_with($item->key, 'category:') ? 'moodle/category:manage' : 'moodle/course:update';
        return has_capability($capability, $context, $userid);
    }

    #[\Override]
    public function update(content_item $item, string $html): void {
        global $CFG, $DB;

        [$type, $id] = explode(':', $item->key);
        $id = (int) $id;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($type === 'category') {
            $category = \core_course_category::get($id, MUST_EXIST, true);
            $category->update((object) ['id' => $id, 'description' => $html]);
        } else if ($type === 'course') {
            update_course((object) ['id' => $id, 'summary' => $html]);
        } else if ($type === 'section') {
            $section = $DB->get_record('course_sections', ['id' => $id], '*', MUST_EXIST);
            course_update_section($section->course, $section, ['summary' => $html]);
        } else {
            throw new \coding_exception('Unknown course content item type.');
        }
    }

    /**
     * Build a category description item.
     *
     * @param \stdClass $category Category record.
     * @return content_item
     */
    private function build_category_item(\stdClass $category): content_item {
        $context = context_coursecat::instance($category->id);
        return new content_item(
            key: "category:{$category->id}",
            contextid: $context->id,
            courseid: null,
            categoryid: (int) $category->id,
            component: 'core_course',
            contenttype: get_string('category'),
            itemname: $category->name,
            fieldname: 'description',
            html: (string) $category->description,
            editurl: new moodle_url('/course/editcategory.php', ['id' => $category->id]),
            filecontextid: $context->id,
            filecomponent: 'coursecat',
            filearea: 'description',
            fileitemid: 0,
            formatfield: 'descriptionformat',
        );
    }

    /**
     * Build a course summary item.
     *
     * @param \stdClass $course Course record.
     * @return content_item
     */
    private function build_course_item(\stdClass $course): content_item {
        $context = context_course::instance($course->id);
        return new content_item(
            key: "course:{$course->id}",
            contextid: $context->id,
            courseid: (int) $course->id,
            categoryid: (int) $course->category,
            component: 'core_course',
            contenttype: get_string('course'),
            itemname: $course->fullname,
            fieldname: 'summary',
            html: (string) $course->summary,
            editurl: new moodle_url('/course/edit.php', ['id' => $course->id]),
            filecontextid: $context->id,
            filecomponent: 'course',
            filearea: 'summary',
            fileitemid: 0,
            formatfield: 'summaryformat',
        );
    }

    /**
     * Build a section summary item.
     *
     * @param \stdClass $section Course section record.
     * @return content_item
     */
    private function build_section_item(\stdClass $section): content_item {
        global $DB;

        $course = $DB->get_record('course', ['id' => $section->course], 'id, category, format', MUST_EXIST);
        $context = context_course::instance($course->id);
        $name = $section->name ?: get_section_name($course, $section);
        return new content_item(
            key: "section:{$section->id}",
            contextid: $context->id,
            courseid: (int) $course->id,
            categoryid: (int) $course->category,
            component: 'core_course',
            contenttype: get_string('section'),
            itemname: $name,
            fieldname: 'summary',
            html: (string) $section->summary,
            editurl: new moodle_url('/course/editsection.php', ['id' => $section->id]),
            filecontextid: $context->id,
            filecomponent: 'course',
            filearea: 'section',
            fileitemid: (int) $section->id,
            formatfield: 'summaryformat',
        );
    }
}
