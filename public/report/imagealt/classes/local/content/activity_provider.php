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

use context_module;
use moodle_url;
use report_imagealt\local\scope;

/**
 * Editable activity descriptions plus Page, Book, and Lesson body content.
 *
 * Generic module descriptions are discovered from FEATURE_MOD_INTRO. Body fields use explicit adapters so that each file
 * area, edit destination, permission, and persistence rule remains owned and reviewable.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_provider extends base_provider {
    #[\Override]
    public function get_key(): string {
        return 'core_activities';
    }

    #[\Override]
    public function get_items(\context $scope): iterable {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        $modnames = $this->get_candidate_modules($scope);
        foreach ($modnames as $modname) {
            if (!plugin_supports('mod', $modname, FEATURE_MOD_INTRO, false)) {
                continue;
            }
            [$coursesql, $params] = scope::get_course_condition('cm.course', $scope, "intro{$modname}");
            $sql = "SELECT instance.*, cm.id AS cmid, cm.course, c.category
                      FROM {{$modname}} instance
                      JOIN {course_modules} cm ON cm.instance = instance.id
                      JOIN {modules} modules ON modules.id = cm.module AND modules.name = :modname
                      JOIN {course} c ON c.id = cm.course
                     WHERE {$coursesql} AND instance.intro <> :empty";
            $records = $DB->get_recordset_sql($sql, ['modname' => $modname, 'empty' => ''] + $params);
            try {
                foreach ($records as $record) {
                    yield $this->build_intro_item($modname, $record);
                }
            } finally {
                $records->close();
            }
        }

        // Bounded workers normally scan one course. Avoid three more joins when that course does not use these explicit body
        // adapters; wider synchronous compatibility scans retain the original comprehensive behaviour.
        if (in_array('page', $modnames, true)) {
            yield from $this->get_page_items($scope);
        }
        if (in_array('book', $modnames, true)) {
            yield from $this->get_book_items($scope);
        }
        if (in_array('lesson', $modnames, true)) {
            yield from $this->get_lesson_items($scope);
        }
    }

    /**
     * Return installed module types that can occur in the requested scope.
     *
     * @param \context $scope Requested scope.
     * @return string[] Module names.
     */
    private function get_candidate_modules(\context $scope): array {
        global $DB;

        if ($scope->contextlevel !== CONTEXT_COURSE) {
            return array_keys(\core_component::get_plugin_list('mod'));
        }

        // Querying the small set used by this course prevents every course worker from probing every installed module table.
        $sql = "SELECT DISTINCT modules.name
                  FROM {course_modules} coursemodules
                  JOIN {modules} modules ON modules.id = coursemodules.module
                 WHERE coursemodules.course = :courseid";
        return array_values($DB->get_fieldset_sql($sql, ['courseid' => $scope->instanceid]));
    }

    #[\Override]
    public function get_item(string $key): ?content_item {
        $parts = explode(':', $key);
        return match ($parts[0] ?? '') {
            'intro' => $this->get_intro_item($parts),
            'page' => $this->get_page_item($parts),
            'bookchapter' => $this->get_book_item($parts),
            'lessonpage' => $this->get_lesson_item($parts),
            default => null,
        };
    }

    #[\Override]
    public function can_edit(content_item $item, int $userid): bool {
        $context = \context::instance_by_id($item->contextid);
        if (str_starts_with($item->key, 'bookchapter:')) {
            return has_capability('mod/book:edit', $context, $userid);
        }
        if (str_starts_with($item->key, 'lessonpage:')) {
            return has_capability('mod/lesson:edit', $context, $userid);
        }
        return has_capability('moodle/course:manageactivities', $context, $userid);
    }

    #[\Override]
    public function update(content_item $item, string $html): void {
        global $DB;

        $parts = explode(':', $item->key);
        $type = $parts[0] ?? '';
        $now = time();

        if ($type === 'intro' && count($parts) === 4) {
            [, $modname, $instanceid, $cmid] = $parts;
            $DB->set_field($modname, 'intro', $html, ['id' => (int) $instanceid]);
            if ($DB->get_manager()->field_exists($modname, new \xmldb_field('timemodified'))) {
                $DB->set_field($modname, 'timemodified', $now, ['id' => (int) $instanceid]);
            }
            rebuild_course_cache((int) get_coursemodule_from_id($modname, (int) $cmid, 0, false, MUST_EXIST)->course, true);
            return;
        }
        if ($type === 'page' && count($parts) === 3) {
            [, $instanceid, $cmid] = $parts;
            $page = $DB->get_record('page', ['id' => (int) $instanceid], '*', MUST_EXIST);
            $page->content = $html;
            $page->revision++;
            $page->timemodified = $now;
            $DB->update_record('page', $page);
            rebuild_course_cache((int) get_coursemodule_from_id('page', (int) $cmid, 0, false, MUST_EXIST)->course, true);
            return;
        }
        if ($type === 'bookchapter' && count($parts) === 3) {
            [, $chapterid, $cmid] = $parts;
            $DB->set_field('book_chapters', 'content', $html, ['id' => (int) $chapterid]);
            $cm = get_coursemodule_from_id('book', (int) $cmid, 0, false, MUST_EXIST);
            rebuild_course_cache((int) $cm->course, true);
            return;
        }
        if ($type === 'lessonpage' && count($parts) === 3) {
            [, $pageid, $cmid] = $parts;
            $DB->set_field('lesson_pages', 'contents', $html, ['id' => (int) $pageid]);
            $cm = get_coursemodule_from_id('lesson', (int) $cmid, 0, false, MUST_EXIST);
            rebuild_course_cache((int) $cm->course, true);
            return;
        }

        throw new \coding_exception('Unknown activity content item type.');
    }

    /**
     * Enumerate Page body fields.
     *
     * @param \context $scope Requested scope.
     * @return iterable<content_item>
     */
    private function get_page_items(\context $scope): iterable {
        global $DB;

        [$coursesql, $params] = scope::get_course_condition('cm.course', $scope, 'page');
        $sql = "SELECT page.*, cm.id AS cmid, cm.course, c.category
                  FROM {page} page
                  JOIN {course_modules} cm ON cm.instance = page.id
                  JOIN {modules} modules ON modules.id = cm.module AND modules.name = :modname
                  JOIN {course} c ON c.id = cm.course
                 WHERE {$coursesql} AND page.content <> :empty";
        $records = $DB->get_recordset_sql($sql, ['modname' => 'page', 'empty' => ''] + $params);
        try {
            foreach ($records as $record) {
                yield $this->build_page_item($record);
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Enumerate Book chapter fields.
     *
     * @param \context $scope Requested scope.
     * @return iterable<content_item>
     */
    private function get_book_items(\context $scope): iterable {
        global $DB;

        [$coursesql, $params] = scope::get_course_condition('cm.course', $scope, 'book');
        $sql = "SELECT chapter.*, book.name AS bookname, cm.id AS cmid, cm.course, c.category
                  FROM {book_chapters} chapter
                  JOIN {book} book ON book.id = chapter.bookid
                  JOIN {course_modules} cm ON cm.instance = book.id
                  JOIN {modules} modules ON modules.id = cm.module AND modules.name = :modname
                  JOIN {course} c ON c.id = cm.course
                 WHERE {$coursesql} AND chapter.content <> :empty AND chapter.hidden = 0";
        $records = $DB->get_recordset_sql($sql, ['modname' => 'book', 'empty' => ''] + $params);
        try {
            foreach ($records as $record) {
                yield $this->build_book_item($record);
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Enumerate Lesson page fields.
     *
     * @param \context $scope Requested scope.
     * @return iterable<content_item>
     */
    private function get_lesson_items(\context $scope): iterable {
        global $DB;

        [$coursesql, $params] = scope::get_course_condition('cm.course', $scope, 'lesson');
        $sql = "SELECT lessonpage.*, lesson.name AS lessonname, cm.id AS cmid, cm.course, c.category
                  FROM {lesson_pages} lessonpage
                  JOIN {lesson} lesson ON lesson.id = lessonpage.lessonid
                  JOIN {course_modules} cm ON cm.instance = lesson.id
                  JOIN {modules} modules ON modules.id = cm.module AND modules.name = :modname
                  JOIN {course} c ON c.id = cm.course
                 WHERE {$coursesql} AND lessonpage.contents <> :empty";
        $records = $DB->get_recordset_sql($sql, ['modname' => 'lesson', 'empty' => ''] + $params);
        try {
            foreach ($records as $record) {
                yield $this->build_lesson_item($record);
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Load an activity introduction item.
     *
     * @param string[] $parts Item key parts.
     * @return content_item|null
     */
    private function get_intro_item(array $parts): ?content_item {
        global $DB;

        if (count($parts) !== 4 || !ctype_alnum(str_replace('_', '', $parts[1])) || !ctype_digit($parts[2])) {
            return null;
        }
        [, $modname, $instanceid] = $parts;
        $record = $DB->get_record($modname, ['id' => (int) $instanceid]);
        if (!$record) {
            return null;
        }
        $cm = get_coursemodule_from_instance($modname, (int) $instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, category', MUST_EXIST);
        $record->cmid = $cm->id;
        $record->course = $cm->course;
        $record->category = $course->category;
        return $this->build_intro_item($modname, $record);
    }

    /**
     * Load a Page body item.
     *
     * @param string[] $parts Item key parts.
     * @return content_item|null
     */
    private function get_page_item(array $parts): ?content_item {
        global $DB;

        if (count($parts) !== 3 || !ctype_digit($parts[1])) {
            return null;
        }
        $record = $DB->get_record('page', ['id' => (int) $parts[1]]);
        if (!$record) {
            return null;
        }
        $cm = get_coursemodule_from_instance('page', $record->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, category', MUST_EXIST);
        $record->cmid = $cm->id;
        $record->course = $cm->course;
        $record->category = $course->category;
        return $this->build_page_item($record);
    }

    /**
     * Load a Book chapter item.
     *
     * @param string[] $parts Item key parts.
     * @return content_item|null
     */
    private function get_book_item(array $parts): ?content_item {
        global $DB;

        if (count($parts) !== 3 || !ctype_digit($parts[1])) {
            return null;
        }
        $chapter = $DB->get_record('book_chapters', ['id' => (int) $parts[1]]);
        if (!$chapter) {
            return null;
        }
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, category', MUST_EXIST);
        $chapter->bookname = $book->name;
        $chapter->cmid = $cm->id;
        $chapter->course = $cm->course;
        $chapter->category = $course->category;
        return $this->build_book_item($chapter);
    }

    /**
     * Load a Lesson page item.
     *
     * @param string[] $parts Item key parts.
     * @return content_item|null
     */
    private function get_lesson_item(array $parts): ?content_item {
        global $DB;

        if (count($parts) !== 3 || !ctype_digit($parts[1])) {
            return null;
        }
        $page = $DB->get_record('lesson_pages', ['id' => (int) $parts[1]]);
        if (!$page) {
            return null;
        }
        $lesson = $DB->get_record('lesson', ['id' => $page->lessonid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('lesson', $lesson->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, category', MUST_EXIST);
        $page->lessonname = $lesson->name;
        $page->cmid = $cm->id;
        $page->course = $cm->course;
        $page->category = $course->category;
        return $this->build_lesson_item($page);
    }

    /**
     * Build an activity introduction item.
     *
     * @param string $modname Module name.
     * @param \stdClass $record Activity record.
     * @return content_item
     */
    private function build_intro_item(string $modname, \stdClass $record): content_item {
        $context = context_module::instance($record->cmid);
        return new content_item(
            key: "intro:{$modname}:{$record->id}:{$record->cmid}",
            contextid: $context->id,
            courseid: (int) $record->course,
            categoryid: (int) $record->category,
            component: "mod_{$modname}",
            contenttype: get_string('modulename', "mod_{$modname}"),
            itemname: $record->name,
            fieldname: 'intro',
            html: (string) $record->intro,
            editurl: new moodle_url('/course/modedit.php', ['update' => $record->cmid]),
            filecontextid: $context->id,
            filecomponent: "mod_{$modname}",
            filearea: 'intro',
            fileitemid: 0,
            formatfield: 'introformat',
        );
    }

    /**
     * Build a Page body item.
     *
     * @param \stdClass $record Page record.
     * @return content_item
     */
    private function build_page_item(\stdClass $record): content_item {
        $context = context_module::instance($record->cmid);
        return new content_item(
            key: "page:{$record->id}:{$record->cmid}",
            contextid: $context->id,
            courseid: (int) $record->course,
            categoryid: (int) $record->category,
            component: 'mod_page',
            contenttype: get_string('modulename', 'mod_page'),
            itemname: $record->name,
            fieldname: 'content',
            html: (string) $record->content,
            editurl: new moodle_url('/course/modedit.php', ['update' => $record->cmid]),
            filecontextid: $context->id,
            filecomponent: 'mod_page',
            filearea: 'content',
            fileitemid: 0,
            formatfield: 'contentformat',
        );
    }

    /**
     * Build a Book chapter item.
     *
     * @param \stdClass $record Book chapter record.
     * @return content_item
     */
    private function build_book_item(\stdClass $record): content_item {
        $context = context_module::instance($record->cmid);
        return new content_item(
            key: "bookchapter:{$record->id}:{$record->cmid}",
            contextid: $context->id,
            courseid: (int) $record->course,
            categoryid: (int) $record->category,
            component: 'mod_book',
            contenttype: get_string('modulename', 'mod_book'),
            itemname: $record->bookname . ': ' . $record->title,
            fieldname: 'content',
            html: (string) $record->content,
            editurl: new moodle_url('/mod/book/edit.php', ['cmid' => $record->cmid, 'id' => $record->id]),
            filecontextid: $context->id,
            filecomponent: 'mod_book',
            filearea: 'chapter',
            fileitemid: (int) $record->id,
            formatfield: 'contentformat',
        );
    }

    /**
     * Build a Lesson page item.
     *
     * @param \stdClass $record Lesson page record.
     * @return content_item
     */
    private function build_lesson_item(\stdClass $record): content_item {
        $context = context_module::instance($record->cmid);
        return new content_item(
            key: "lessonpage:{$record->id}:{$record->cmid}",
            contextid: $context->id,
            courseid: (int) $record->course,
            categoryid: (int) $record->category,
            component: 'mod_lesson',
            contenttype: get_string('modulename', 'mod_lesson'),
            itemname: $record->lessonname . ': ' . $record->title,
            fieldname: 'contents',
            html: (string) $record->contents,
            editurl: new moodle_url('/mod/lesson/editpage.php', ['id' => $record->id]),
            filecontextid: $context->id,
            filecomponent: 'mod_lesson',
            filearea: 'page_contents',
            fileitemid: (int) $record->id,
            formatfield: 'contentsformat',
        );
    }
}
