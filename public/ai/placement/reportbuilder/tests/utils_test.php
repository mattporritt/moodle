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

namespace aiplacement_reportbuilder;

use core_ai\ai_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../../tests/ai_test_trait.php');

/**
 * Report placement utils test.
 *
 * @package    aiplacement_reportbuilder
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class utils_test extends \advanced_testcase {
    use ai_test_trait;

    /** @var \stdClass A user who manages the course. */
    private \stdClass $manager;

    /** @var \stdClass A user enrolled without any role that manages content. */
    private \stdClass $student;

    /** @var \context_course Course context. */
    private \context_course $context;

    #[\Override]
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($course->id);
        $this->manager = $this->getDataGenerator()->create_user();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->manager->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($this->student->id, $course->id, 'student');
    }

    /**
     * Every requirement has to be met before an action can be requested, so each is switched on in turn and the
     * answer stays false until the last one is.
     */
    public function test_an_action_needs_every_requirement_before_it_is_available(): void {
        $actionname = 'describe_image';
        $actionclass = \core_ai\aiactions\describe_image::class;
        $this->setUser($this->student);

        // Nothing is configured yet.
        set_config('enabled', 0, 'aiplacement_reportbuilder');
        $this->assertFalse(utils::is_placement_action_available($this->context, $actionname, $actionclass));

        // The placement itself.
        set_config('enabled', 1, 'aiplacement_reportbuilder');
        $this->assertFalse(utils::is_placement_action_available($this->context, $actionname, $actionclass));

        // A provider that serves the action.
        $this->create_ai_provider([$actionname], \aiprovider_openai\provider::class);
        $this->assertFalse(utils::is_placement_action_available($this->context, $actionname, $actionclass));

        // A user holding the capability. A student is enrolled and can see course content, but requesting AI
        // descriptions for a whole course is not theirs to do.
        $this->setUser($this->manager);
        $this->assertTrue(utils::is_placement_action_available($this->context, $actionname, $actionclass));
    }

    /**
     * The action can be switched off for reports while it stays on elsewhere, which is the whole reason this is a
     * placement of its own: a site can allow AI descriptions while editing one image, and still withhold the button
     * that requests them for every image in a course.
     */
    public function test_the_action_can_be_switched_off_for_this_placement_alone(): void {
        $actionname = 'describe_image';
        $actionclass = \core_ai\aiactions\describe_image::class;
        $this->setUser($this->manager);
        set_config('enabled', 1, 'aiplacement_reportbuilder');
        set_config('enabled', 1, 'aiplacement_editor');
        $this->create_ai_provider([$actionname], \aiprovider_openai\provider::class);

        set_config($actionname, 0, 'aiplacement_reportbuilder');

        $this->assertFalse(utils::is_placement_action_available($this->context, $actionname, $actionclass));
        // The same action, same provider, still available through the placement that was left alone.
        $this->assertTrue(\core\di::get(\core_ai\manager::class)->is_action_enabled('aiplacement_editor', $actionclass));
    }

    /**
     * A report can request an action in the background, long after the request that asked for it, so the caller
     * names the user. Resolving the capability against whoever the running session belongs to would let a task
     * inherit permissions the requester never had.
     */
    public function test_the_capability_is_checked_against_the_named_user(): void {
        $actionname = 'describe_image';
        $actionclass = \core_ai\aiactions\describe_image::class;
        set_config('enabled', 1, 'aiplacement_reportbuilder');
        $this->create_ai_provider([$actionname], \aiprovider_openai\provider::class);
        // Nobody in particular is logged in, which is the situation a scheduled task runs in.
        $this->setAdminUser();

        $this->assertTrue(utils::is_placement_action_available(
            $this->context,
            $actionname,
            $actionclass,
            (int) $this->manager->id,
        ));
        $this->assertFalse(utils::is_placement_action_available(
            $this->context,
            $actionname,
            $actionclass,
            (int) $this->student->id,
        ));
    }

    /**
     * A disabled placement withdraws its actions whatever else is configured.
     */
    public function test_the_placement_can_be_disabled(): void {
        set_config('enabled', 0, 'aiplacement_reportbuilder');
        $this->assertFalse(utils::is_placement_available());

        set_config('enabled', 1, 'aiplacement_reportbuilder');
        $this->assertTrue(utils::is_placement_available());
    }
}
