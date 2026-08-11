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
 * Deterministic alternative text classifier.
 *
 * This intentionally makes no semantic-quality claim and never calls AI.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classifier {
    /** Maximum alternative text length used by Moodle's image editor. */
    public const MAX_ALT_LENGTH = 750;

    /**
     * Whether a source names a host, and so can be fetched as a URL rather than resolved through the file API.
     *
     * @param string $src The image source value.
     * @return bool
     */
    public static function is_remote_source(string $src): bool {
        return (string) parse_url(trim($src), PHP_URL_HOST) !== '';
    }

    /**
     * Whether a source is a reference the surrounding content is itself responsible for resolving.
     *
     * Only these can be called broken when they resolve to nothing, because only for these does failing to resolve
     * prove anything. A "@@PLUGINFILE@@" reference names a file the content claims as its own, and a plain relative
     * path is resolved by the browser against whatever page happens to be displaying the content, which is never
     * where Moodle keeps files. Both are dead if the file API cannot find them.
     *
     * Deliberately excludes anything starting with "/", which is a perfectly ordinary address on this site -
     * another activity's pluginfile.php path, a theme image - that this content does not own and cannot be judged
     * on. Those are left unclassified rather than reported as broken.
     *
     * @param string $src The image source value.
     * @return bool
     */
    public static function is_owned_reference(string $src): bool {
        $src = trim($src);
        return $src !== ''
            && !self::is_remote_source($src)
            && (string) parse_url($src, PHP_URL_SCHEME) === ''
            && !str_starts_with($src, '/');
    }

    /**
     * Classify one image occurrence.
     *
     * @param bool $hasalt Whether the alt attribute exists.
     * @param string $alt Alternative text value.
     * @param string $src Source value.
     * @param bool $decorative Whether markup explicitly identifies a presentational image.
     * @param bool $linkedonly Whether the image is the link's only accessible-name source.
     * @param bool $filemissing Whether the source points at a file this site owns and cannot find.
     * @return array{status: string, reason: string}
     */
    public function classify(
        bool $hasalt,
        string $alt,
        string $src,
        bool $decorative,
        bool $linkedonly,
        bool $filemissing = false,
    ): array {
        $trimmed = trim($alt);

        // Reported ahead of anything about the alternative text, because there is no image here to describe. Whatever
        // the alt attribute says, the reader gets nothing, and the fix is to the content rather than to the text: find
        // the image, restore it or remove the reference. Classifying these as "missing alternative text" sent people
        // to write descriptions of images that were not there.
        if ($filemissing) {
            return ['status' => 'broken', 'reason' => 'broken'];
        }
        if ($decorative) {
            return ['status' => 'decorative', 'reason' => 'none'];
        }
        if (!$hasalt || $trimmed === '') {
            return ['status' => 'missing', 'reason' => $linkedonly ? 'linkedimage' : 'missing'];
        }
        if ($linkedonly && !$this->is_meaningful($trimmed, $src)) {
            return ['status' => 'potentiallypoor', 'reason' => 'linkedimage'];
        }
        if ($this->is_placeholder($trimmed)) {
            return ['status' => 'potentiallypoor', 'reason' => 'placeholder'];
        }
        if ($this->is_filename_or_path($trimmed, $src)) {
            return ['status' => 'potentiallypoor', 'reason' => 'filename'];
        }

        return ['status' => 'present', 'reason' => 'none'];
    }

    /**
     * Determine whether text is more useful than a deterministic placeholder/path.
     *
     * @param string $alt Alternative text.
     * @param string $src Source URL.
     * @return bool
     */
    private function is_meaningful(string $alt, string $src): bool {
        return !$this->is_placeholder($alt) && !$this->is_filename_or_path($alt, $src);
    }

    /**
     * Detect localised generic placeholder values.
     *
     * Translators can extend or replace the comma-separated list for their language.
     *
     * @param string $alt Alternative text.
     * @return bool
     */
    private function is_placeholder(string $alt): bool {
        $placeholders = array_filter(array_map(
            static fn(string $value): string => \core_text::strtolower(trim($value)),
            explode(',', get_string('placeholderalternatives', 'report_imagealt')),
        ));

        return in_array(\core_text::strtolower(trim($alt)), $placeholders, true);
    }

    /**
     * Detect an alternative consisting only of a filename, URL, or path.
     *
     * @param string $alt Alternative text.
     * @param string $src Source URL.
     * @return bool
     */
    private function is_filename_or_path(string $alt, string $src): bool {
        $normalise = static function (string $value): string {
            $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5);
            return \core_text::strtolower(rawurldecode($value));
        };

        $normalisedalt = $normalise($alt);
        $normalisedsrc = $normalise($src);
        $path = (string) parse_url($normalisedsrc, PHP_URL_PATH);
        $basename = basename($path);

        if ($normalisedalt === $normalisedsrc || ($basename !== '' && $normalisedalt === $basename)) {
            return true;
        }

        if (preg_match('~^(?:https?://|(?:\.{0,2}/)|[a-z]:[/\\\\])~iu', $normalisedalt)) {
            return true;
        }

        return (bool) preg_match('~^[^\s]+\.(?:avif|bmp|gif|jpe?g|png|svg|webp)(?:[?#].*)?$~iu', $normalisedalt);
    }
}
