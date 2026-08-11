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
 * Tests for deterministic image alternative text classification.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(classifier::class)]
final class classifier_test extends \advanced_testcase {
    /**
     * Classification rules are predictable and do not perform semantic scoring.
     *
     * @param bool $hasalt Whether an alt attribute is present.
     * @param string $alt Alternative text.
     * @param string $src Image source.
     * @param bool $decorative Whether the image is explicitly presentational.
     * @param bool $linkedonly Whether the image is the link's only name source.
     * @param string $status Expected status.
     * @param string $reason Expected reason.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('classification_provider')]
    public function test_classify(
        bool $hasalt,
        string $alt,
        string $src,
        bool $decorative,
        bool $linkedonly,
        string $status,
        string $reason,
    ): void {
        $this->assertSame(
            ['status' => $status, 'reason' => $reason],
            (new classifier())->classify($hasalt, $alt, $src, $decorative, $linkedonly),
        );
    }

    /**
     * An image the site cannot find is reported as broken whatever its alternative text says, because there is
     * nothing there for a description to describe.
     */
    public function test_a_missing_file_outranks_every_alt_text_rule(): void {
        $classifier = new classifier();

        $cases = [
            'no alt' => [false, ''],
            'good alt' => [true, 'A snowy summit at dawn'],
            'placeholder alt' => [true, 'image'],
        ];
        foreach ($cases as $case => [$hasalt, $alt]) {
            $this->assertSame(
                ['status' => 'broken', 'reason' => 'broken'],
                $classifier->classify($hasalt, $alt, 'gone.png', false, false, true),
                $case,
            );
        }

        // Including one the markup calls decorative: a decorative image is one a reader is meant to skip, and this
        // one is skipped whatever the markup says, so what the author needs to know is that the file is gone.
        $this->assertSame(
            ['status' => 'broken', 'reason' => 'broken'],
            $classifier->classify(true, '', 'gone.png', true, false, true),
        );
    }

    /**
     * Which sources can be judged broken when nothing resolves, and which have to be left alone.
     *
     * @param string $src Image source.
     * @param bool $owned Whether the content is responsible for resolving it.
     * @param bool $remote Whether it names a host and so could be fetched.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('source_kind_provider')]
    public function test_source_kinds(string $src, bool $owned, bool $remote): void {
        $this->assertSame($owned, classifier::is_owned_reference($src), "owned: {$src}");
        $this->assertSame($remote, classifier::is_remote_source($src), "remote: {$src}");
    }

    /**
     * Source kinds and what can be concluded from each.
     *
     * @return array<string, array>
     */
    public static function source_kind_provider(): array {
        return [
            // The content claims this file as its own, so the file API failing to find it proves it is gone.
            'pluginfile placeholder' => ['@@PLUGINFILE@@/summit.png', true, false],
            // Resolved by the browser against whatever page shows the content, which is never where files live.
            'bare relative path' => ['summit.png', true, false],
            'relative subdirectory' => ['images/summit.png', true, false],
            // An ordinary address on this site that this content does not own: another activity's file, a theme
            // image. Nothing can be concluded from it not resolving here, so it is neither broken nor fetchable.
            'root relative' => ['/pluginfile.php/42/mod_page/content/0/summit.png', false, false],
            'root relative theme image' => ['/theme/image.php/boost/core/1/moodlelogo', false, false],
            // Addressed by host, so it can be fetched and described even though it is not stored here.
            'absolute https' => ['https://example.com/summit.png', false, true],
            'absolute http' => ['http://example.com/summit.png', false, true],
            'protocol relative' => ['//example.com/summit.png', false, true],
        ];
    }

    /**
     * Classification cases.
     *
     * @return array<string, array>
     */
    public static function classification_provider(): array {
        return [
            'no alt attribute' => [false, '', 'lake.jpg', false, false, 'missing', 'missing'],
            'whitespace alt' => [true, '  ', 'lake.jpg', false, false, 'missing', 'missing'],
            'decorative' => [true, '', 'divider.png', true, false, 'decorative', 'none'],
            'filename' => [true, 'lake.jpg', '/images/lake.jpg', false, false, 'potentiallypoor', 'filename'],
            'URL' => [true, 'https://example.test/lake', 'lake.jpg', false, false, 'potentiallypoor', 'filename'],
            'placeholder' => [true, 'Picture', 'lake.jpg', false, false, 'potentiallypoor', 'placeholder'],
            'linked missing' => [false, '', 'lake.jpg', false, true, 'missing', 'linkedimage'],
            'linked placeholder' => [true, 'image', 'lake.jpg', false, true, 'potentiallypoor', 'linkedimage'],
            'present' => [true, 'Lake and mountains at sunrise', 'lake.jpg', false, false, 'present', 'none'],
        ];
    }
}
