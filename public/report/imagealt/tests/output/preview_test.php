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

namespace report_imagealt\output;

/**
 * Tests for image preview addressing.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(preview::class)]
final class preview_test extends \advanced_testcase {
    /**
     * The address points at this plugin's own endpoint and changes when the image does.
     */
    public function test_url_identifies_the_occurrence_and_the_image_version(): void {
        $url = preview::url(42, str_repeat('a', 30) . str_repeat('b', 10));

        $this->assertNotNull($url);
        $this->assertStringContainsString('/report/imagealt/preview.php', $url->out(false));
        $this->assertSame('42', $url->param('id'));
        // Only the leading characters are carried: enough to change the address when the image is replaced.
        $this->assertSame(str_repeat('a', 10), $url->param('hash'));

        $changed = preview::url(42, str_repeat('c', 40));
        $this->assertNotSame($url->out(false), $changed->out(false));
    }

    /**
     * An occurrence whose image could not be resolved has no address, and says so rather than showing a broken
     * image or a link that leads nowhere.
     */
    public function test_unresolvable_images_are_reported_rather_than_linked(): void {
        $this->resetAfterTest();

        $this->assertNull(preview::url(42, null));
        $this->assertSame(get_string('unknownvalue', 'core_ai'), preview::thumbnail(42, null));
        $this->assertSame('lake.png', preview::source_link(42, null, 'lake.png'));
        $this->assertStringNotContainsString('<a ', preview::source_link(42, '', 'lake.png'));
    }

    /**
     * A resolvable image is rendered as a thumbnail that is ignored by screen readers, and a linked file name.
     */
    public function test_resolvable_images_are_rendered_for_review(): void {
        $this->resetAfterTest();
        $hash = str_repeat('a', 40);

        $thumbnail = preview::thumbnail(42, $hash);
        $this->assertStringContainsString('/report/imagealt/preview.php', $thumbnail);
        // The report already names the image in its own column, so the thumbnail adds nothing to announce.
        $this->assertStringContainsString('alt=""', $thumbnail);
        $this->assertStringContainsString('role="presentation"', $thumbnail);

        $link = preview::source_link(42, $hash, 'lake.png');
        $this->assertStringContainsString('/report/imagealt/preview.php', $link);
        $this->assertStringContainsString('>lake.png<', $link);
    }

    /**
     * File names are escaped, since they are content a user can influence.
     */
    public function test_source_labels_are_escaped(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            '&lt;script&gt;',
            preview::source_link(42, str_repeat('a', 40), '<script>alert(1)</script>.png'),
        );
        $this->assertStringContainsString('&lt;script&gt;', preview::source_link(42, null, '<script>alert(1)</script>.png'));
    }
}
