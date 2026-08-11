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
 * Tests for bounded context target discovery.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scope::class)]
final class scope_test extends \advanced_testcase {
    /**
     * Category and course pages use a stable ID cursor and remain inside the requested subtree.
     */
    public function test_keyset_pages_are_bounded_and_scoped(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $childone = $generator->create_category(['parent' => $parent->id]);
        $childtwo = $generator->create_category(['parent' => $parent->id]);
        $unrelated = $generator->create_category();
        $courseone = $generator->create_course(['category' => $parent->id]);
        $coursetwo = $generator->create_course(['category' => $childone->id]);
        $coursethree = $generator->create_course(['category' => $childtwo->id]);
        $generator->create_course(['category' => $unrelated->id]);
        $context = \context_coursecat::instance($parent->id);

        $firstcategories = scope::get_category_page($context, 0, 2);
        $secondcategories = scope::get_category_page($context, end($firstcategories), 2);
        $firstcourses = scope::get_course_page($context, 0, 2);
        $secondcourses = scope::get_course_page($context, end($firstcourses), 2);

        $this->assertCount(2, $firstcategories);
        $this->assertEqualsCanonicalizing(
            [$parent->id, $childone->id, $childtwo->id],
            array_merge($firstcategories, $secondcategories),
        );
        $this->assertCount(2, $firstcourses);
        $this->assertEqualsCanonicalizing(
            [$courseone->id, $coursetwo->id, $coursethree->id],
            array_merge($firstcourses, $secondcourses),
        );
    }
}
