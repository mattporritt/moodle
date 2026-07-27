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

namespace core_ai;

use core_files\hook\after_file_created;

/**
 * Core AI hook listener.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Correlate a newly-created file with a generated image action, by content.
     *
     * Generation always leaves the image as a user draft file (component 'user', filearea 'draft'), with
     * its contenthash recorded against the ai_action_generate_image row. When a placement later promotes
     * that draft into its own permanent file area (for example embedding it in a forum post), file storage
     * creates a new file record with the same content and dispatches this hook. This listener matches the
     * incoming file's contenthash against any not-yet-resolved generate_image row and records the new
     * file's pathnamehash there, giving the AI usage report a durable local reference to display from.
     *
     * The draft file creation itself also dispatches this hook, so draft file areas are skipped here to
     * avoid a row matching against its own still-temporary draft copy.
     *
     * @param after_file_created $hook
     */
    public static function correlate_generated_image(after_file_created $hook): void {
        global $DB;

        $file = $hook->storedfile;

        // Only permanent copies are a durable reference; skip draft areas, including the draft file
        // created at generation time itself.
        if ($file->get_component() === 'user' && $file->get_filearea() === 'draft') {
            return;
        }

        $contenthash = $file->get_contenthash();

        // Multiple rows could in principle share a contenthash if identical image bytes were generated
        // more than once; the most recently created unresolved row is the most likely match.
        $candidates = $DB->get_records_select(
            'ai_action_generate_image',
            'contenthash = :contenthash AND localpathnamehash IS NULL',
            ['contenthash' => $contenthash],
            'id DESC',
            'id',
            0,
            1
        );

        if (!$candidates) {
            return;
        }

        $id = array_key_first($candidates);
        $DB->set_field('ai_action_generate_image', 'localpathnamehash', $file->get_pathnamehash(), ['id' => $id]);
    }
}
