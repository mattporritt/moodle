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

namespace core_ai\aiactions;

use core_ai\aiactions\responses\response_base;

/**
 * Generate a text description for an image class.
 *
 * @package    core_ai
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class describe_image extends base {
    /**
     * Create a new instance of the describe_image action.
     *
     * It’s responsible for performing any setup tasks,
     * such as getting additional data from the database etc.
     *
     * @param int $contextid The context id the action was created in.
     * @param int $userid The user id making the request.
     * @param \stored_file $image The image to generate a description for.
     */
    public function __construct(
        int $contextid,
        /** @var int The user id requesting the action. */
        protected int $userid,
        /** @var \stored_file The image to generate a description for. */
        protected \stored_file $image,
    ) {
        parent::__construct($contextid);
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();
        $imageinfo = $this->image->get_imageinfo();

        $record = new \stdClass();
        $record->filename = $this->image->get_filename();
        $record->filesize = $this->image->get_filesize();
        $record->mimetype = $imageinfo['mimetype'];
        $record->width = $imageinfo['width'];
        $record->height = $imageinfo['height'];
        $record->responseid = $responsearr['id']; // Can be null.
        $record->fingerprint = $responsearr['fingerprint']; // Can be null.
        $record->generatedcontent = $responsearr['generatedcontent']; // Can be null.
        $record->finishreason = $responsearr['finishreason']; // Can be null.
        $record->prompttokens = $responsearr['prompttokens']; // Can be null.
        $record->completiontoken = $responsearr['completiontokens']; // Can be null.

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
