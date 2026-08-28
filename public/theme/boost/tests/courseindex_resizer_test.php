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

namespace theme_boost;

/**
 * Tests for the course index drawer resizer helpers.
 *
 * @package    theme_boost
 * @copyright  2026 A K M Safat Shahin <safatshahin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(courseindex_resizer::class)]
final class courseindex_resizer_test extends \advanced_testcase {
    /**
     * Widths are clamped to the allowed range.
     *
     * @param int $width The requested width.
     * @param int $expected The expected clamped width.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('clamp_width_provider')]
    public function test_clamp_width(int $width, int $expected): void {
        $this->assertSame($expected, courseindex_resizer::clamp_width($width));
    }

    /**
     * Data provider for test_clamp_width.
     *
     * @return array[]
     */
    public static function clamp_width_provider(): array {
        return [
            'below minimum' => [100, courseindex_resizer::MIN_WIDTH],
            'at minimum' => [285, 285],
            'in range' => [400, 400],
            'at maximum' => [640, 640],
            'above maximum' => [9999, courseindex_resizer::MAX_WIDTH],
            'negative' => [-50, courseindex_resizer::MIN_WIDTH],
        ];
    }

    /**
     * A user who has never resized the drawer has no width.
     */
    public function test_get_user_width_unset(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertNull(courseindex_resizer::get_user_width());
    }

    /**
     * A saved width is returned clamped to the allowed range.
     */
    public function test_get_user_width_set(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        set_user_preference(courseindex_resizer::PREFERENCE, 400);
        $this->assertSame(400, courseindex_resizer::get_user_width());

        set_user_preference(courseindex_resizer::PREFERENCE, 100);
        $this->assertSame(courseindex_resizer::MIN_WIDTH, courseindex_resizer::get_user_width());

        set_user_preference(courseindex_resizer::PREFERENCE, 9999);
        $this->assertSame(courseindex_resizer::MAX_WIDTH, courseindex_resizer::get_user_width());
    }

    /**
     * Users who are not logged in, and guests, have no width.
     */
    public function test_get_user_width_no_login(): void {
        $this->resetAfterTest();

        $this->setUser(0);
        $this->assertNull(courseindex_resizer::get_user_width());

        $this->setGuestUser();
        $this->assertNull(courseindex_resizer::get_user_width());
    }
}
