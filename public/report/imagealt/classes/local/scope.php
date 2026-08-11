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

namespace report_imagealt\local;

/**
 * Resolves course and category IDs for a requested report context.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /**
     * Return one keyset-paginated category ID page for background discovery.
     *
     * This deliberately returns scalar IDs rather than category objects. Discovery only needs stable targets and should
     * not retain a category tree or a large recordset while cron is queuing work.
     *
     * @param \context $context Requested system or category context.
     * @param int $lastid Last category ID already discovered.
     * @param int $limit Maximum IDs to return.
     * @return int[]
     */
    public static function get_category_page(\context $context, int $lastid, int $limit): array {
        global $DB;

        $params = ['lastid' => $lastid];
        $where = 'id > :lastid';
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $category = $DB->get_record(
                'course_categories',
                ['id' => $context->instanceid],
                'id, path',
                MUST_EXIST,
            );
            $where .= ' AND (id = :scopeid OR ' . $DB->sql_like('path', ':scopepath', false) . ')';
            $params['scopeid'] = $category->id;
            $params['scopepath'] = $category->path . '/%';
        } else if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return [];
        }

        return array_map('intval', array_keys($DB->get_records_select_menu(
            'course_categories',
            $where,
            $params,
            'id ASC',
            'id, id AS value',
            0,
            $limit,
        )));
    }

    /**
     * Return one keyset-paginated course ID page for background discovery.
     *
     * @param \context $context Requested system, category, or course context.
     * @param int $lastid Last course ID already discovered.
     * @param int $limit Maximum IDs to return.
     * @return int[]
     */
    public static function get_course_page(\context $context, int $lastid, int $limit): array {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            return $context->instanceid > $lastid ? [(int) $context->instanceid] : [];
        }

        $params = ['lastid' => $lastid, 'siteid' => SITEID];
        $where = 'course.id > :lastid AND course.id <> :siteid';
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $category = $DB->get_record(
                'course_categories',
                ['id' => $context->instanceid],
                'id, path',
                MUST_EXIST,
            );
            $where .= ' AND (category.id = :scopeid OR ' . $DB->sql_like('category.path', ':scopepath', false) . ')';
            $params['scopeid'] = $category->id;
            $params['scopepath'] = $category->path . '/%';
        } else if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return [];
        }

        $sql = "SELECT course.id, course.id AS value
                  FROM {course} course
                  JOIN {course_categories} category ON category.id = course.category
                 WHERE {$where}
              ORDER BY course.id ASC";
        return array_map('intval', array_keys($DB->get_records_sql_menu($sql, $params, 0, $limit)));
    }

    /**
     * Return one keyset-paginated user ID page for sitewide background discovery.
     *
     * User profile content is not scoped to a course or category, so this is only meaningful for a system-level scan.
     *
     * @param \context $context Requested report context.
     * @param int $lastid Last user ID already discovered.
     * @param int $limit Maximum IDs to return.
     * @return int[]
     */
    public static function get_user_page(\context $context, int $lastid, int $limit): array {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return [];
        }

        return array_map('intval', array_keys($DB->get_records_select_menu(
            'user',
            'id > :lastid AND deleted = 0 AND description <> :empty',
            ['lastid' => $lastid, 'empty' => ''],
            'id ASC',
            'id, id AS value',
            0,
            $limit,
        )));
    }

    /**
     * Return category IDs in scope, or null for every category.
     *
     * @param \context $context Requested context.
     * @return int[]|null
     */
    public static function get_category_ids(\context $context): ?array {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            return null;
        }
        if ($context->contextlevel === CONTEXT_COURSE) {
            return [];
        }
        if ($context->contextlevel !== CONTEXT_COURSECAT) {
            throw new \coding_exception('Image alternative text reports support only system, category, and course contexts.');
        }

        $category = $DB->get_record('course_categories', ['id' => $context->instanceid], 'id, path', MUST_EXIST);
        $pathlike = $DB->sql_like('path', ':path', false);
        return array_map('intval', array_keys($DB->get_records_select_menu(
            'course_categories',
            "id = :id OR {$pathlike}",
            ['id' => $category->id, 'path' => $category->path . '/%'],
            '',
            'id, id AS value',
        )));
    }

    /**
     * Return course IDs in scope, or null for every non-site course.
     *
     * @param \context $context Requested context.
     * @return int[]|null
     */
    public static function get_course_ids(\context $context): ?array {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            return null;
        }
        if ($context->contextlevel === CONTEXT_COURSE) {
            return [(int) $context->instanceid];
        }

        $categoryids = self::get_category_ids($context);
        if (!$categoryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'category');
        return array_map('intval', array_keys($DB->get_records_select_menu(
            'course',
            "category {$insql} AND id <> :siteid",
            $params + ['siteid' => SITEID],
            '',
            'id, id AS value',
        )));
    }

    /**
     * Build a SQL condition for an integer field in the course scope.
     *
     * @param string $field Fully qualified course ID field.
     * @param \context $context Requested context.
     * @param string $prefix Parameter prefix.
     * @return array{0: string, 1: array}
     */
    public static function get_course_condition(string $field, \context $context, string $prefix = 'scope'): array {
        global $DB;

        $courseids = self::get_course_ids($context);
        if ($courseids === null) {
            return ["{$field} <> :{$prefix}siteid", ["{$prefix}siteid" => SITEID]];
        }
        if (!$courseids) {
            return ['1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $prefix);
        return ["{$field} {$insql}", $params];
    }
}
