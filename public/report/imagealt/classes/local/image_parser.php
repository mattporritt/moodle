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
 * Finds and updates exact image occurrences while preserving surrounding stored HTML.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_parser {
    /** Pattern matching an image tag while allowing angle brackets inside quoted attributes. */
    private const IMAGE_TAG_PATTERN = '~<img\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~iu';

    /**
     * Extract image occurrences.
     *
     * @param string $html Stored HTML.
     * @return array<int, array<string, mixed>>
     */
    public function extract(string $html): array {
        if ($html === '' || !preg_match_all(self::IMAGE_TAG_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $relationships = $this->get_dom_relationships($html);
        $result = [];
        foreach ($matches[0] as $index => [$tag, $offset]) {
            $attributes = $this->parse_attributes($tag);
            if (!array_key_exists('src', $attributes) || trim($attributes['src']) === '') {
                continue;
            }

            $role = \core_text::strtolower(trim($attributes['role'] ?? ''));
            $hasalt = array_key_exists('alt', $attributes);
            $alt = $attributes['alt'] ?? '';
            $decorative = $hasalt && trim($alt) === '' && in_array($role, ['none', 'presentation'], true);

            $result[] = [
                'index' => $index,
                'offset' => $offset,
                'tag' => $tag,
                'src' => $attributes['src'],
                'hasalt' => $hasalt,
                'alt' => $alt,
                'decorative' => $decorative,
                'linkedonly' => $relationships[$index]['linkedonly'] ?? false,
                'surroundingtext' => $relationships[$index]['surroundingtext'] ?? '',
                'occurrencekey' => hash('sha256', $index . "\0" . $attributes['src']),
                'occurrencehash' => hash('sha256', $tag),
            ];
        }

        return $result;
    }

    /**
     * Replace the selected image's accessibility attributes.
     *
     * @param string $html Current HTML.
     * @param int $index Zero-based image index.
     * @param string $expectedhash Hash of the exact indexed image tag.
     * @param string $alt Replacement alternative text.
     * @param bool $decorative Whether to mark the image presentational.
     * @return string|null Updated HTML, or null when the occurrence is stale.
     */
    public function replace(
        string $html,
        int $index,
        string $expectedhash,
        string $alt,
        bool $decorative,
    ): ?string {
        if (
            !preg_match_all(self::IMAGE_TAG_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE)
                || !isset($matches[0][$index])
        ) {
            return null;
        }

        [$tag, $offset] = $matches[0][$index];
        if (!hash_equals($expectedhash, hash('sha256', $tag))) {
            return null;
        }

        $attributestoremove = $decorative ? '(?:alt|role)' : 'alt';
        $replacement = preg_replace(
            '~\s+' . $attributestoremove . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~iu',
            '',
            $tag,
        );
        if ($replacement === null) {
            return null;
        }

        $attributes = $decorative
            ? ' alt="" role="presentation"'
            : ' alt="' . htmlspecialchars(trim($alt), ENT_QUOTES | ENT_HTML5) . '"';
        $ending = str_ends_with(rtrim($tag), '/>') ? ' />' : '>';
        $replacement = preg_replace('~\s*/?>$~u', $attributes . $ending, $replacement);
        if ($replacement === null) {
            return null;
        }

        return substr($html, 0, $offset) . $replacement . substr($html, $offset + strlen($tag));
    }

    /**
     * Parse one image tag's attributes without serialising the source fragment.
     *
     * @param string $tag Image tag.
     * @return array<string, string>
     */
    private function parse_attributes(string $tag): array {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $tag . '</body>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $image = $document->getElementsByTagName('img')->item(0);
        if (!$image) {
            return [];
        }

        $attributes = [];
        foreach ($image->attributes as $attribute) {
            $attributes[\core_text::strtolower($attribute->name)] = $attribute->value;
        }
        return $attributes;
    }

    /**
     * Determine link and surrounding-text relationships with a read-only DOM pass.
     *
     * @param string $html Stored HTML.
     * @return array<int, array{linkedonly: bool, surroundingtext: string}>
     */
    private function get_dom_relationships(string $html): array {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="report-imagealt-root">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $relationships = [];
        foreach ($document->getElementsByTagName('img') as $index => $image) {
            $parent = $image->parentNode;
            $link = null;
            $ancestor = $parent;
            while ($ancestor instanceof \DOMElement) {
                if (\core_text::strtolower($ancestor->tagName) === 'a') {
                    $link = $ancestor;
                    break;
                }
                if ($ancestor->getAttribute('id') === 'report-imagealt-root') {
                    break;
                }
                $ancestor = $ancestor->parentNode;
            }
            $linktext = $link ? trim($link->textContent) : '';
            $linklabel = $link?->getAttribute('aria-label') ?: $link?->getAttribute('title');
            $otherlabel = '';
            if ($link) {
                foreach ($link->getElementsByTagName('img') as $linkimage) {
                    if (!$linkimage->isSameNode($image) && trim($linkimage->getAttribute('alt')) !== '') {
                        $otherlabel = $linkimage->getAttribute('alt');
                        break;
                    }
                }
            }

            $contextnode = $parent;
            $surrounding = '';
            while ($contextnode instanceof \DOMElement) {
                $surrounding = trim($contextnode->textContent);
                if ($surrounding !== '' || $contextnode->getAttribute('id') === 'report-imagealt-root') {
                    break;
                }
                $contextnode = $contextnode->parentNode;
            }
            $relationships[$index] = [
                'linkedonly' => $link !== null
                    && $linktext === ''
                    && trim((string) $linklabel) === ''
                    && trim($otherlabel) === '',
                'surroundingtext' => \core_text::substr(preg_replace('/\s+/u', ' ', $surrounding) ?? '', 0, 500),
            ];
        }

        return $relationships;
    }
}
