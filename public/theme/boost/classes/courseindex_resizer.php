<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_boost;

/**
 * Helpers for the resizable course index drawer.
 *
 * The chosen width is stored as a user preference and emitted pre-paint by
 * the drawers layout as an inline --drawer-index-width custom property, so
 * the drawer renders at the saved width with no flicker. The width limits
 * here are the single source of truth: theme_boost_get_pre_scss() injects
 * them into the theme SCSS as $drawer-index-width-min and
 * $drawer-index-width-max, whose !default declarations in drawer.scss only
 * serve the statically precompiled CSS and must mirror these values.
 *
 * @package    theme_boost
 * @copyright  2026 A K M Safat Shahin <safatshahin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseindex_resizer {
    /** @var string The user preference storing the chosen course index drawer width in pixels. */
    public const PREFERENCE = 'drawer-index-width';

    /** @var int The smallest allowed course index drawer width in pixels. */
    public const MIN_WIDTH = 285;

    /** @var int The largest allowed course index drawer width in pixels. */
    public const MAX_WIDTH = 640;

    /**
     * Clamp a width to the allowed range.
     *
     * @param int $width The requested width in pixels.
     * @return int The width clamped to the allowed range.
     */
    public static function clamp_width(int $width): int {
        return max(self::MIN_WIDTH, min(self::MAX_WIDTH, $width));
    }

    /**
     * Get the current user's chosen course index drawer width.
     *
     * @return int|null The clamped width in pixels, or null if the user has never resized the drawer.
     */
    public static function get_user_width(): ?int {
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $width = get_user_preferences(self::PREFERENCE);
        if ($width === null || $width === '') {
            return null;
        }
        return self::clamp_width((int) $width);
    }
}
