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
 * The network transfer itself needs a live URL, and Moodle's curl wrapper has no test double for it, so that part
 * is exercised end to end by hand rather than here. What is covered here is everything that decides whether a fetch
 * is attempted at all, what happens to a downloaded file once it exists on disk (size and MIME-type enforcement,
 * exercised via reflection against store_downloaded_file() and files this test suite controls), and that a fetched
 * copy does not outlive its use.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(remote_image::class)]
final class remote_image_test extends \advanced_testcase {
    /**
     * Call the protected file-validation seam directly, as if the given local file had just been downloaded.
     *
     * remote_image is final, so this is reflection rather than a test subclass, kept to this one helper so every
     * test below reads like a call to a normal method.
     *
     * @param string $path Path to the file on local disk.
     * @param string $url Source URL the file is presented as having come from.
     * @param \context $context Context the stored copy is stored against.
     * @param int $occurrenceid Occurrence the copy belongs to.
     * @return \stored_file
     */
    private function store_downloaded_file(string $path, string $url, \context $context, int $occurrenceid): \stored_file {
        $method = new \ReflectionMethod(remote_image::class, 'store_downloaded_file');
        return $method->invoke(new remote_image(), $path, $url, $context, $occurrenceid);
    }

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

    /** One-pixel PNG image. */
    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * A downloaded file over the size limit is rejected without being stored.
     */
    public function test_an_oversized_downloaded_file_is_rejected(): void {
        $this->resetAfterTest();
        $context = context_system::instance();
        $path = make_request_directory() . '/oversized.png';
        file_put_contents($path, str_repeat('a', 12582913));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:remotetoolarge', 'report_imagealt', display_size(12582912)));
        try {
            $this->store_downloaded_file($path, 'https://example.com/oversized.png', $context, 9);
        } finally {
            $this->assertFalse($this->area_has_files($context, 9));
        }
    }

    /**
     * A downloaded file that is not one of the supported image types is rejected without being stored.
     */
    public function test_a_non_image_downloaded_file_is_rejected(): void {
        $this->resetAfterTest();
        $context = context_system::instance();
        $path = make_request_directory() . '/not-an-image.txt';
        file_put_contents($path, 'not really a png');

        $this->expectException(\moodle_exception::class);
        try {
            $this->store_downloaded_file($path, 'https://example.com/not-an-image.txt', $context, 10);
        } finally {
            $this->assertFalse($this->area_has_files($context, 10));
        }
    }

    /**
     * A downloaded file within the size limit and of a supported type is stored against the occurrence.
     */
    public function test_a_valid_downloaded_file_is_stored(): void {
        $this->resetAfterTest();
        $context = context_system::instance();
        $path = make_request_directory() . '/lake.png';
        file_put_contents($path, base64_decode(self::IMAGE));

        $file = $this->store_downloaded_file($path, 'https://example.com/lake.png', $context, 11);

        $this->assertSame('lake.png', $file->get_filename());
        $this->assertTrue($this->area_has_files($context, 11));
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
