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

use core_ai\ai_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../../../ai/tests/ai_test_trait.php');

/**
 * Tests for when this report will offer to write a description.
 *
 * These run against the real AI manager rather than a stubbed one, because what is being checked is that this report
 * asks it the questions a placement is required to ask. A mock would answer whatever the test told it to.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(suggestion_service::class)]
final class suggestion_service_test extends \advanced_testcase {
    use ai_test_trait;

    /** @var \context_course The context of a course holding images. */
    private \context_course $context;

    #[\Override]
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($course->id);
        $this->create_ai_provider(['describe_image'], \aiprovider_openai\provider::class);
        set_config('enabled', 1, 'aiplacement_reportbuilder');
        $this->setAdminUser();
    }

    /**
     * A provider alone is not enough. Until a site enables the report placement, this report offers no AI, so the
     * placement is the switch an admin reaches for rather than having to disable image description everywhere.
     */
    public function test_a_disabled_placement_withdraws_ai(): void {
        $this->assertTrue(suggestion_service::is_available($this->context));

        set_config('enabled', 0, 'aiplacement_reportbuilder');

        $this->assertFalse(suggestion_service::is_available($this->context));
    }

    /**
     * Image description can be switched off for reports while the same provider keeps serving it elsewhere.
     */
    public function test_the_action_can_be_switched_off_for_reports_alone(): void {
        set_config('describe_image', 0, 'aiplacement_reportbuilder');

        $this->assertFalse(suggestion_service::is_available($this->context));
    }

    /**
     * Viewing the report and spending the site's AI budget are separate permissions. A teacher who can see every
     * image in a course cannot necessarily request a description for all of them.
     */
    public function test_a_user_without_the_capability_is_not_offered_ai(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->context->instanceid, 'teacher');
        $this->setUser($user);

        $this->assertFalse(suggestion_service::is_available($this->context));
    }

    /**
     * Bulk generation runs in a scheduled task, long after the request, where the current user is whoever cron
     * belongs to. The permission that matters is the requesting user's, so callers name them.
     */
    public function test_the_named_user_is_the_one_checked(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->context->instanceid, 'teacher');
        $this->getDataGenerator()->enrol_user($manager->id, $this->context->instanceid, 'manager');
        // Nobody is logged in, which is the situation the task runs in.
        $this->setUser(null);

        $this->assertTrue(suggestion_service::is_available($this->context, (int) $manager->id));
        $this->assertFalse(suggestion_service::is_available($this->context, (int) $teacher->id));
    }
}
