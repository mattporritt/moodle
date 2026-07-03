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

namespace core_comment\external;

/**
 * Unit tests for the core_comment_output_fragment_comment_list() callback.
 *
 * @package    core_comment
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::core_comment_output_fragment_comment_list
 */
final class fragment_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->usecomments = true;
    }

    /**
     * Helper: create a course and add a comment to it via the block_comments area.
     *
     * @return array [$course, $comment]
     */
    private function setup_course_with_comment(): array {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);

        $user = $generator->create_user();
        $this->setUser($user);
        $generator->enrol_user($user->id, $course->id, 'student');

        // Add a comment.
        $args = new \stdClass();
        $args->context   = $context;
        $args->course    = $course;
        $args->component = 'block_comments';
        $args->area      = 'page_comments';
        $args->itemid    = 0;
        $manager = new \core_comment\manager($args);
        $comment = $manager->add('Test fragment comment');

        return [$course, $comment];
    }

    /**
     * Test that the fragment returns HTML containing the posted comment.
     */
    public function test_fragment_returns_comment_html(): void {
        [$course] = $this->setup_course_with_comment();
        $context = \context_course::instance($course->id);

        $html = \core_comment_output_fragment_comment_list([
            'context'   => $context,
            'component' => 'block_comments',
            'itemid'    => 0,
            'area'      => 'page_comments',
            'courseid'  => $course->id,
            'page'      => 0,
        ]);

        $this->assertIsString($html);
        $this->assertStringContainsString('Test fragment comment', $html);
        $this->assertStringContainsString('comment-list', $html);
    }

    /**
     * Test that fragment output wraps comments in the expected list element.
     */
    public function test_fragment_contains_comment_list_element(): void {
        [$course] = $this->setup_course_with_comment();
        $context = \context_course::instance($course->id);

        $html = \core_comment_output_fragment_comment_list([
            'context'   => $context,
            'component' => 'block_comments',
            'itemid'    => 0,
            'area'      => 'page_comments',
            'courseid'  => $course->id,
            'page'      => 0,
        ]);

        $this->assertMatchesRegularExpression('/<ul[^>]*class="[^"]*comment-list[^"]*"/', $html);
    }

    /**
     * Test that a supplied client_id appears in element IDs within the rendered fragment.
     */
    public function test_fragment_uses_supplied_client_id(): void {
        [$course] = $this->setup_course_with_comment();
        $context = \context_course::instance($course->id);
        $clientid = 'testclientid123';

        $html = \core_comment_output_fragment_comment_list([
            'context'   => $context,
            'component' => 'block_comments',
            'itemid'    => 0,
            'area'      => 'page_comments',
            'courseid'  => $course->id,
            'page'      => 0,
            'client_id' => $clientid,
        ]);

        $this->assertStringContainsString("comment-list-{$clientid}", $html);
    }

    /**
     * Test that fragment returns an empty list when there are no comments.
     */
    public function test_fragment_empty_when_no_comments(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $this->setAdminUser();

        $html = \core_comment_output_fragment_comment_list([
            'context'   => $context,
            'component' => 'block_comments',
            'itemid'    => 0,
            'area'      => 'page_comments',
            'courseid'  => $course->id,
            'page'      => 0,
        ]);

        $this->assertIsString($html);
        $this->assertStringContainsString('comment-list', $html);
        // No comment content expected.
        $this->assertStringNotContainsString('comment-message', $html);
    }
}
