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

use context_system;

/**
 * Tests for fetching an image the content points at but this site does not store.
 *
 * The fetch itself needs a live URL, so it is exercised end to end by hand rather than here. What is covered here is
 * everything that decides whether a fetch is attempted at all, and that a fetched copy does not outlive its use.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(remote_image::class)]
final class remote_image_test extends \advanced_testcase {
    /**
     * Only addresses a browser would load as an image are fetched, so content cannot use this to reach anything else.
     *
     * @param string $url The source to attempt.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused_scheme_provider')]
    public function test_only_http_addresses_are_fetched(string $url): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        (new remote_image())->fetch($url, context_system::instance(), 1);
    }

    /**
     * Schemes that must never be fetched.
     *
     * @return array<string, array>
     */
    public static function refused_scheme_provider(): array {
        return [
            'file' => ['file:///etc/passwd'],
            'data' => ['data:image/png;base64,iVBORw0KGgo='],
            'ftp' => ['ftp://example.com/summit.png'],
            'no scheme at all' => ['summit.png'],
        ];
    }

    /**
     * A fetched copy is removed on request, so somebody else's image is kept only while it is being described.
     */
    public function test_a_fetched_copy_can_be_removed(): void {
        $this->resetAfterTest();
        $context = context_system::instance();

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'report_imagealt',
            'filearea' => remote_image::FILE_AREA,
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'fetched.png',
        ], 'not really a png');
        $this->assertTrue($this->area_has_files($context, 7));

        (new remote_image())->delete_for($context, 7);

        $this->assertFalse($this->area_has_files($context, 7));
    }

    /**
     * Whether the fetched-copy area holds anything for one occurrence.
     *
     * @param \context $context Context the copy is stored against.
     * @param int $occurrenceid Occurrence the copy belongs to.
     * @return bool
     */
    private function area_has_files(\context $context, int $occurrenceid): bool {
        return (bool) get_file_storage()->get_area_files(
            $context->id,
            'report_imagealt',
            remote_image::FILE_AREA,
            $occurrenceid,
            'id',
            false,
        );
    }
}
