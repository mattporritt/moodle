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
 * Tests for the suggestion state badge.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(suggestion_state::class)]
final class suggestion_state_test extends \advanced_testcase {
    /**
     * Every state a suggestion can reach is labelled and explained, so no badge can render as a bare key or an
     * unexplained word.
     */
    public function test_every_state_is_named_and_explained(): void {
        $this->resetAfterTest();

        // The eight the filter offers and the column's help documents. Having no suggestion at all is not among
        // them: it is the absence of one, and is covered on its own below.
        $states = ['queued', 'processing', 'ready', 'accepted', 'discarded', 'stale', 'failed', 'cancelled'];
        foreach ($states as $state) {
            $badge = suggestion_state::badge($state);
            $this->assertStringContainsString(
                get_string("suggestionstatus_{$state}", 'report_imagealt'),
                $badge,
                "State '{$state}' is not labelled",
            );
            $this->assertStringContainsString('title="', $badge, "State '{$state}' carries no explanation");
        }
    }

    /**
     * Badges pair their foreground with their background rather than assuming white reads against all of them.
     * Bootstrap's plain bg-warning leaves white on amber at under 2:1, so "Out of date" would be the state hardest
     * to read of the eight.
     */
    public function test_badge_colours_carry_a_readable_foreground(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString('badge text-bg-warning', suggestion_state::badge('stale'));
        $this->assertStringNotContainsString('badge bg-', suggestion_state::badge('stale'));
    }

    /**
     * The badge is the report's only route to the batch a description was written in, so when the batch is known it
     * has to be a link that is recognisable as one rather than a coloured label.
     */
    public function test_a_known_batch_makes_the_badge_a_recognisable_link(): void {
        $this->resetAfterTest();

        $badge = suggestion_state::badge('ready', 7);

        $this->assertStringContainsString('/report/imagealt/batch.php?id=7', $badge);
        // Boost styles no hover or underline on links of its own, so the badge has to bring its own affordance.
        $this->assertStringContainsString('report-imagealt-statelink', $badge);
        // The tooltip explains the state and says where following it leads.
        $this->assertStringContainsString(
            get_string('suggestionstatusinfo_ready', 'report_imagealt'),
            $badge,
        );
        $this->assertStringContainsString('open the batch', $badge);
    }

    /**
     * Bulk generation is the only route to a batch page, so a suggestion requested from the review modal has
     * nowhere to link to. It still has to say what its state means.
     */
    public function test_a_suggestion_with_no_batch_is_explained_but_not_linked(): void {
        $this->resetAfterTest();

        $badge = suggestion_state::badge('stale');

        $this->assertStringNotContainsString('<a ', $badge);
        $this->assertStringNotContainsString('report-imagealt-statelink', $badge);
        $this->assertStringContainsString(get_string('suggestionstatusinfo_stale', 'report_imagealt'), $badge);
        $this->assertStringNotContainsString('open the batch', $badge);
    }

    /**
     * An image nobody has asked for a description for shows the empty marker this table uses everywhere else, not a
     * ninth state. The filter and the column's help both list eight, so dressing this up as one would promise
     * something that cannot be filtered for or looked up.
     */
    public function test_an_image_with_no_suggestion_shows_an_empty_cell(): void {
        $this->resetAfterTest();

        $badge = suggestion_state::badge(null);

        // The same marker the alternative text and preview columns use when they have nothing to show.
        $this->assertStringContainsString(get_string('unknownvalue', 'core_ai'), $badge);
        // Hovering still says what the empty cell means, and the column's help says it without hovering.
        $this->assertStringContainsString(get_string('suggestionstatusinfo_none', 'report_imagealt'), $badge);
        // Nothing has happened, so it is not dressed up as an outcome alongside the states that are.
        $this->assertStringNotContainsString('badge', $badge);
        $this->assertStringNotContainsString('<a ', $badge);
    }
}
