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

namespace core_availability;

/**
 * Unit tests for the capability checker class.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_checker_test extends \advanced_testcase {
    /**
     * Tests loading a class from /availability/classes.
     */
    public function test_capability_checker(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Create a course with teacher and student.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, $roleids['teacher']);
        $generator->enrol_user($student->id, $course->id, $roleids['student']);

        // Check a capability which they both have.
        $context = \context_course::instance($course->id);
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:replypost'));
        sort($result);
        $this->assertEquals(array($teacher->id, $student->id), $result);

        // And one that only teachers have.
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals(array($teacher->id), $result);

        // Check the caching is working.
        $before = $DB->perf_get_queries();
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals(array($teacher->id), $result);
        $this->assertEquals($before, $DB->perf_get_queries());
    }

    /**
     * Tests that the results cache used by get_users_by_capability() is
     * scoped per context. A capability_checker is constructed per context
     * (for example once per restricted activity), so a shared cache keyed
     * only on the capability would incorrectly return one context's users
     * for a different context checking the same capability.
     *
     * @covers \core_availability\capability_checker::get_users_by_capability
     */
    public function test_capability_checker_cache_is_scoped_per_context(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');

        // Course A: only its own teacher has the capability.
        $coursea = $generator->create_course();
        $teachera = $generator->create_user();
        $generator->enrol_user($teachera->id, $coursea->id, $roleids['teacher']);

        // Course B: only its own, different teacher has the capability.
        $courseb = $generator->create_course();
        $teacherb = $generator->create_user();
        $generator->enrol_user($teacherb->id, $courseb->id, $roleids['teacher']);

        $contexta = \context_course::instance($coursea->id);
        $contextb = \context_course::instance($courseb->id);

        // Simulate two restricted activities in different contexts, each
        // creating their own capability_checker, checking the same
        // capability within a single request.
        $checkera = new capability_checker($contexta);
        $resulta = array_keys($checkera->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([$teachera->id], $resulta);

        $checkerb = new capability_checker($contextb);
        $resultb = array_keys($checkerb->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([$teacherb->id], $resultb);
    }

    /**
     * Tests that a cached get_users_by_capability() result does not survive
     * a change to who holds the capability in that context, for example
     * when a role's permissions are edited part-way through the request.
     * Without cache invalidation, a capability_checker constructed before
     * the change (e.g. to filter one restricted activity) and reused, or a
     * new checker for the same context constructed after the change, would
     * incorrectly keep returning the pre-change user list.
     *
     * @covers \core_availability\capability_checker::get_users_by_capability
     * @covers \core_availability\capability_checker::purge_cache
     */
    public function test_capability_checker_cache_invalidated_on_permission_change(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $studentroleid = $roleids['student'];

        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, $studentroleid);
        $context = \context_course::instance($course->id);

        // Initially, students do not have this capability, so the cached
        // result is an empty list.
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([], $result);

        // Grant the capability to students in this context. This must
        // invalidate the previously cached result.
        role_change_permission($studentroleid, $context, 'mod/forum:deleteanypost', CAP_ALLOW);

        // A fresh capability_checker for the same context must reflect the
        // permission change rather than reusing the stale cached result.
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([$student->id], $result);
    }

    /**
     * Tests that a cached get_users_by_capability() result does not survive
     * a role being assigned to, or unassigned from, a user in that context.
     * A user gaining or losing a role changes who holds a capability in
     * that context just as much as editing the role's permissions does, so
     * this must invalidate the cache in the same way.
     *
     * @covers \core_availability\capability_checker::get_users_by_capability
     * @covers \core_availability\capability_checker::purge_cache
     */
    public function test_capability_checker_cache_invalidated_on_role_assignment(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $teacherroleid = $roleids['teacher'];

        $course = $generator->create_course();
        $user = $generator->create_user();
        $context = \context_course::instance($course->id);

        // Nobody holds the capability yet.
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([], $result);

        // Assigning a role that grants the capability must invalidate the
        // previously cached (empty) result.
        role_assign($teacherroleid, $user->id, $context->id);
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([$user->id], $result);

        // Removing that role must invalidate the cached result too.
        role_unassign($teacherroleid, $user->id, $context->id);
        $checker = new capability_checker($context);
        $result = array_keys($checker->get_users_by_capability('mod/forum:deleteanypost'));
        $this->assertEquals([], $result);
    }
}
