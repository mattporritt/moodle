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
 * Tests for exact image occurrence parsing and replacement.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(image_parser::class)]
final class image_parser_test extends \advanced_testcase {
    /**
     * The parser records each occurrence and its markup relationships.
     */
    public function test_extract_occurrences(): void {
        $html = '<p>Alpine lesson <img src="one.jpg"></p>'
            . '<a href="/target"><img src="two.jpg" alt="image"></a>'
            . '<img src="divider.png" alt="" role="presentation">';

        $images = (new image_parser())->extract($html);

        $this->assertCount(3, $images);
        $this->assertFalse($images[0]['hasalt']);
        $this->assertSame('Alpine lesson', $images[0]['surroundingtext']);
        $this->assertTrue($images[1]['linkedonly']);
        $this->assertTrue($images[2]['decorative']);
        $this->assertNotSame($images[0]['occurrencekey'], $images[1]['occurrencekey']);
    }

    /**
     * A wrapped image still supplies a link name unless another accessible name is present.
     */
    public function test_extract_link_relationships_through_wrappers(): void {
        $html = '<p>Choose a destination '
            . '<a href="/first"><span><img src="first.jpg"></span></a>'
            . '<a href="/second"><span><img src="second.jpg"><img src="named.jpg" alt="Second destination"></span></a>'
            . '</p>';

        $images = (new image_parser())->extract($html);

        $this->assertTrue($images[0]['linkedonly']);
        $this->assertSame('Choose a destination', $images[0]['surroundingtext']);
        $this->assertFalse($images[1]['linkedonly']);
        $this->assertTrue($images[2]['linkedonly']);
    }

    /**
     * Replacement changes only the selected image tag and escapes attribute text.
     */
    public function test_replace_preserves_surrounding_html(): void {
        $html = '<p class="lead">Before <img data-x="1" src="one.jpg"> after</p>'
            . '<img src="two.jpg" alt="Keep me">';
        $parser = new image_parser();
        $image = $parser->extract($html)[0];

        $updated = $parser->replace($html, 0, $image['occurrencehash'], 'Lake "Lucerne"', false);

        $this->assertSame(
            '<p class="lead">Before <img data-x="1" src="one.jpg" alt="Lake &quot;Lucerne&quot;"> after</p>'
                . '<img src="two.jpg" alt="Keep me">',
            $updated,
        );
    }

    /**
     * A normal alternative text update does not alter existing image semantics.
     */
    public function test_replace_preserves_non_presentational_role(): void {
        $html = '<img src="one.jpg" alt="Old text" role="img">';
        $parser = new image_parser();
        $image = $parser->extract($html)[0];

        $this->assertSame(
            '<img src="one.jpg" role="img" alt="New text">',
            $parser->replace($html, 0, $image['occurrencehash'], 'New text', false),
        );
    }

    /**
     * Decorative replacement uses the same explicit marker as Moodle's image editor.
     */
    public function test_replace_decorative(): void {
        $html = '<img src="one.jpg" alt="Old text">';
        $parser = new image_parser();
        $image = $parser->extract($html)[0];

        $this->assertSame(
            '<img src="one.jpg" alt="" role="presentation">',
            $parser->replace($html, 0, $image['occurrencehash'], '', true),
        );
    }

    /**
     * A changed image tag is stale even when it remains at the same position.
     */
    public function test_replace_rejects_stale_tag(): void {
        $parser = new image_parser();
        $image = $parser->extract('<img src="one.jpg">')[0];
        $this->assertNull($parser->replace(
            '<img src="one.jpg" alt="Newer text">',
            0,
            $image['occurrencehash'],
            'Older suggestion',
            false,
        ));
    }

    /**
     * Angle brackets inside quoted attributes do not truncate an image occurrence.
     */
    public function test_quoted_angle_bracket_is_preserved(): void {
        $html = '<img src="one.jpg" title="One > two">';
        $parser = new image_parser();
        $image = $parser->extract($html)[0];

        $this->assertSame(
            '<img src="one.jpg" title="One > two" alt="Diagram">',
            $parser->replace($html, 0, $image['occurrencehash'], 'Diagram', false),
        );
    }
}
