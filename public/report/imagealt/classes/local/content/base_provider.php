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

namespace report_imagealt\local\content;

/**
 * Shared stored-file resolution for content providers.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_provider implements provider {
    #[\Override]
    public function get_sitewide_page(int $lastid, int $limit): array {
        // Course- and category-owned content is discovered through course and category IDs instead; see scan_manager.
        return [];
    }

    #[\Override]
    public function resolve_file(content_item $item, string $src): ?\stored_file {
        $path = $this->normalise_pluginfile_path($item, $src);
        if ($path === null || $path === '' || $path === '/') {
            return null;
        }

        $filename = basename($path);
        $directory = trim(dirname($path), './');
        $filepath = $directory === '' ? '/' : "/{$directory}/";
        $file = get_file_storage()->get_file(
            $item->filecontextid,
            $item->filecomponent,
            $item->filearea,
            $item->fileitemid,
            $filepath,
            $filename,
        );

        return $file && !$file->is_directory() ? $file : null;
    }

    /**
     * Convert a known pluginfile URL or token to a path inside the item's file area.
     *
     * @param content_item $item Content item.
     * @param string $src Raw source.
     * @return string|null
     */
    private function normalise_pluginfile_path(content_item $item, string $src): ?string {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5);
        if (str_starts_with($src, '@@PLUGINFILE@@')) {
            return rawurldecode((string) parse_url(substr($src, strlen('@@PLUGINFILE@@')), PHP_URL_PATH));
        }

        $path = (string) parse_url($src, PHP_URL_PATH);
        $prefix = sprintf(
            '/pluginfile.php/%d/%s/%s/',
            $item->filecontextid,
            $item->filecomponent,
            $item->filearea,
        );
        $position = strpos($path, $prefix);
        if ($position === false) {
            return null;
        }

        $relativepath = substr($path, $position + strlen($prefix));
        if ($item->fileitemid !== 0) {
            $itemprefix = $item->fileitemid . '/';
            if (!str_starts_with($relativepath, $itemprefix)) {
                return null;
            }
            $relativepath = substr($relativepath, strlen($itemprefix));
        } else if (str_starts_with($relativepath, '0/')) {
            $relativepath = substr($relativepath, 2);
        }

        return '/' . ltrim(rawurldecode($relativepath), '/');
    }
}
