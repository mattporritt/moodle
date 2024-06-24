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

namespace core_ai\actions;

use coding_exception;

/**
 * Action response class.
 * Any method that processes an action must return an instance of this class.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action_response {
    /** @var bool The success status of the action. */
    private bool $success;

    /** @var int The timestamp of when the response was created. */
    private int $timecreated;

    /** @var string The name of the action that was processed. */
    private string $actionname;

    /** @var int  Error code. Must exist if status is error. */
    private int $errorcode;

    /** @var string Error message. Must exist if status is error */
    private string $errormessage;

    /** @var array Body, which contains things specific to a successful response. Must exist in successful response. */
    private array $body;

    public function __construct(bool $success, string $actionname, int $errorcode, string $errormessage, array $body) {
        $this->success = $success;
        $this->actionname = $actionname;
        $this->errorcode = $errorcode;
        $this->errormessage = $errormessage;
        $this->body = $body;
        $this->timecreated = time();
    }

}
