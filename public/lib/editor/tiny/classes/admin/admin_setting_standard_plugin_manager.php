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

declare(strict_types=1);

namespace editor_tiny\admin;

/**
 * Admin setting that renders the native TinyMCE standard plugin management table.
 *
 * This is a display-only (nosave) setting that embeds the AJAX toggle table for
 * enabling and disabling native TinyMCE plugins within the admin settings page.
 *
 * @package    editor_tiny
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_standard_plugin_manager extends \admin_setting {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('editor_tiny/standard_plugins_table', '', '', '');
    }

    /**
     * Returns true; nothing to read.
     *
     * @return bool
     */
    public function get_setting(): bool {
        return true;
    }

    /**
     * Returns true; nothing to read.
     *
     * @return bool
     */
    public function get_defaultsetting(): bool {
        return true;
    }

    /**
     * Returns empty string; nothing to write.
     *
     * @param mixed $data
     * @return string
     */
    public function write_setting($data): string {
        return '';
    }

    /**
     * Render the standard plugin management table.
     *
     * @param mixed  $data  Unused.
     * @param string $query Search query for highlighting.
     * @return string HTML output.
     */
    public function output_html($data, $query = ''): string {
        $table = new \editor_tiny\table\standard_plugin_management_table();
        return highlight($query, $table->get_content());
    }
}
