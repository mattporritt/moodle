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
 * The badge describing one suggestion's state, shared by the report and the bulk review table.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class suggestion_state {
    /** @var array<string, string> Bootstrap contextual suffix for each suggestion status. */
    private const VARIANTS = [
        'queued' => 'secondary',
        'processing' => 'info',
        'ready' => 'success',
        'accepted' => 'success',
        'failed' => 'danger',
        'stale' => 'warning',
        'cancelled' => 'secondary',
        'discarded' => 'secondary',
    ];

    /**
     * Render one suggestion's state as a badge, linked to the batch it was generated in.
     *
     * The colours and wording match the batch progress panel, so scanning a table and reading the summary above it
     * tell the same story without having to map words onto each other.
     *
     * @param string|null $status The suggestion status, or null when the image has never had one requested.
     * @param int|null $batchid The batch the suggestion belongs to, linked to when known. Without it the badge is
     *      plain text: bulk generation is the only route to a batch page, so a suggestion requested from the
     *      review modal has nowhere to link to.
     * @return string
     */
    public static function badge(?string $status, ?int $batchid = null): string {
        // A state word alone does not say what it means for the image, so every badge carries the explanation as
        // its tooltip. The same wording is repeated in the column's help icon, which is the route that does not
        // depend on hovering.
        $description = get_string('suggestionstatusinfo_' . ($status ?? 'none'), 'report_imagealt');

        // No description has been asked for, so there is no state to report: this is the absence of a suggestion
        // rather than a ninth kind of one, which is why the filter and the column's help both list eight. Shown the
        // way this table shows every other empty cell, so the column reads as one thing, and so the badges carry
        // all of the meaning in it: on most sites nearly every row will be this one, and a column of grey pills
        // would bury the few rows that are actually asking for something.
        if ($status === null) {
            return \html_writer::span(
                get_string('unknownvalue', 'core_ai'),
                'text-muted',
                ['title' => $description],
            );
        }

        $variant = self::VARIANTS[$status] ?? 'secondary';
        $label = get_string("suggestionstatus_{$status}", 'report_imagealt');
        // Bootstrap's text-bg-* rather than bg-*, so the foreground is chosen to read against each variant's
        // background. Plain bg-warning leaves white text on amber at 1.95:1, well under the 4.5:1 minimum.
        $badge = \html_writer::span($label, "badge text-bg-{$variant}");
        if ($batchid === null) {
            return \html_writer::span($badge, '', ['title' => $description]);
        }

        // The badge itself is the route to the batch, which is otherwise unreachable from the report. Linking per
        // row rather than offering one link for the whole table keeps it unambiguous: a user can have several
        // batches at once, and this image's suggestion belongs to exactly one of them.
        //
        // Boost strips underlines from every link and styles no hover state, so an unadorned anchor around a badge
        // is indistinguishable from the plain badges beside it. The class carries the styling that says otherwise.
        return \html_writer::link(
            new \moodle_url('/report/imagealt/batch.php', ['id' => $batchid]),
            $badge,
            [
                'class' => 'report-imagealt-statelink',
                'title' => get_string('suggestionstatuslink', 'report_imagealt', $description),
            ],
        );
    }
}
