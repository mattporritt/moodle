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
 * Links to the image behind an occurrence, served by this plugin's own preview endpoint.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview {
    /**
     * The address of one occurrence's image.
     *
     * @param int $occurrenceid Occurrence ID.
     * @param string|null $previewhash Content hash recorded at scan time, or null when no file resolved.
     * @return \moodle_url|null Null when the occurrence has no resolvable image, so callers can say so instead
     *      of rendering an image that cannot load.
     */
    public static function url(int $occurrenceid, ?string $previewhash, ?string $src = null): ?\moodle_url {
        if (!$previewhash) {
            // No stored file, but an image referenced by URL is one the browser can fetch for itself: it already
            // does exactly that wherever the content is displayed. Showing it here rather than showing nothing is
            // what makes describing it by hand possible, which is the whole of what this report offers for images
            // it does not store. Restricted to the two schemes a browser will load as an image.
            $scheme = strtolower((string) parse_url(trim((string) $src), PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return new \moodle_url(trim((string) $src));
            }
            return null;
        }

        // The hash is carried only so the address changes when the image does: the endpoint resolves the current
        // file and does not read it. Without it a replaced image would keep showing from the browser cache.
        return new \moodle_url('/report/imagealt/preview.php', [
            'id' => $occurrenceid,
            'hash' => substr($previewhash, 0, 10),
        ]);
    }

    /**
     * A thumbnail of one occurrence's image, or a note that there is none to show.
     *
     * @param int $occurrenceid Occurrence ID.
     * @param string|null $previewhash Content hash recorded at scan time, or null when no file resolved.
     * @return string HTML.
     */
    public static function thumbnail(
        int $occurrenceid,
        ?string $previewhash,
        string $status = '',
        ?string $src = null,
    ): string {
        if ($status === 'broken') {
            return self::broken_placeholder();
        }

        $url = self::url($occurrenceid, $previewhash, $src);
        if (!$url) {
            return get_string('unknownvalue', 'core_ai');
        }

        return \html_writer::empty_tag('img', [
            'src' => $url->out(false),
            'alt' => '',
            'role' => 'presentation',
            'class' => 'img-thumbnail',
            'width' => 96,
            'height' => 72,
            'loading' => 'lazy',
        ]);
    }

    /**
     * A stand-in for an image the content refers to but the site does not have.
     *
     * Sized like a real thumbnail so a broken row keeps its place in the column rather than collapsing, and given a
     * visible label as well as an icon: the whole point of the row is that there is no picture to look at, which a
     * blank cell communicates only by accident.
     *
     * @return string HTML.
     */
    private static function broken_placeholder(): string {
        global $OUTPUT;

        $label = get_string('brokenimage', 'report_imagealt');
        return \html_writer::div(
            $OUTPUT->pix_icon('i/invalid', '', 'moodle', ['class' => 'me-1'])
                . \html_writer::span($label, 'small'),
            'report-imagealt-broken-preview d-flex align-items-center justify-content-center text-muted '
                . 'rounded text-center p-1',
            ['title' => $label],
        );
    }

    /**
     * The image's file name, linked to the image itself where there is one to link to.
     *
     * @param int $occurrenceid Occurrence ID.
     * @param string|null $previewhash Content hash recorded at scan time, or null when no file resolved.
     * @param string $label File name or source path to show.
     * @return string HTML.
     */
    public static function source_link(
        int $occurrenceid,
        ?string $previewhash,
        string $label,
        ?string $src = null,
    ): string {
        $url = self::url($occurrenceid, $previewhash, $src);
        if (!$url) {
            return s($label);
        }

        return \html_writer::link($url, s($label), ['target' => '_blank']);
    }
}
