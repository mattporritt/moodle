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
 * Row action controls shared by the report and the bulk review table.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class actions {
    /**
     * Build the icon button that opens the review modal for one occurrence.
     *
     * Both tables list the same images and now offer more than one action per row, so the actions are icons
     * rather than competing blocks of link text. Following core's own row actions, the icon itself is decorative
     * and the label is carried by the button, where it is both the tooltip and the accessible name.
     *
     * @param int $occurrenceid The occurrence to review.
     * @param int $contextid The context to return to after saving.
     * @return string
     */
    public static function edit_button(int $occurrenceid, int $contextid): string {
        global $OUTPUT;

        $label = get_string('editalttext', 'report_imagealt');
        return \html_writer::tag('button', $OUTPUT->pix_icon('i/edit', ''), [
            'type' => 'button',
            'class' => 'btn btn-icon',
            'title' => $label,
            'aria-label' => $label,
            'data-action' => 'report-imagealt-edit',
            'data-occurrenceid' => $occurrenceid,
            'data-contextid' => $contextid,
        ]);
    }

    /**
     * Build the link that takes a reviewer to the content holding a broken image.
     *
     * The only useful action on an image that is not there. The destination is resolved per row rather than stored,
     * which is affordable because it only happens for rows in this state, and returns nothing when the content has
     * since gone: a link to a page that no longer exists is worse than no link.
     *
     * @param \stdClass $row The occurrence row, carrying its provider and item keys.
     * @param \report_imagealt\local\manager $manager Content manager used to resolve the item.
     * @return string
     */
    public static function fix_broken_link(\stdClass $row, \report_imagealt\local\manager $manager): string {
        global $OUTPUT;

        $provider = $manager->get_provider((string) $row->providerkey);
        $item = $provider?->get_item((string) $row->itemkey);
        if (!$item) {
            return '';
        }

        $label = get_string('brokenimagefix', 'report_imagealt');
        return \html_writer::link($item->editurl, $OUTPUT->pix_icon('i/export', ''), [
            'class' => 'btn btn-icon',
            'title' => $label,
            'aria-label' => $label,
            'target' => '_blank',
            'rel' => 'noopener',
        ]);
    }
}
