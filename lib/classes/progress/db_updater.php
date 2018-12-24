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

/**
 * Progress handler that updates a database table with the progress.
 * Useful when used with an Ajax progress bar.
 *
 * @package    core
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Progress handler that updates a database table with the progress.
 * Useful when used with an Ajax progress bar.
 *
 * The database table and field must be supplied when class is instantiated.
 *
 * @package    core
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_updater extends base {

    /**
     * The databse table to insert the progress updates into.
     *
     * @var string
     */
    protected $table = '';

    /**
     * The table field to update with the progress.
     *
     * @var string
     */
    protected $field = '';

    /**
     * The maximum frequency in seconds to update the database (default 5 seconds).
     * Lower values will increase database calls.
     *
     * @var integer
     */
    protected $interval = 5;

    /**
     * Constructs the progress reporter.
     *
     * @param string $table The databse table to insert the progress updates into.
     * @param string $field The table field to update with the progress.
     * @param int $interval The maximum frequency in seconds to update the database (default 5 seconds).
     */
    public function __construct($table, $field, $interval=5) {
        $this->table = $table;
        $this->field = $field;
        $this->interval = $interval;

    }


    /**
     * When update the database rogress is updated.
     * Database update frequency is set by $interval.
     *
     * @see \core\progress\base::update_progress()
     */
    public function update_progress() {
        // Get progress.
        $now = $this->get_time();
        $nextupdate = $this->lastprogresstime + $this->interval;
        if($now > $nextupdate) {
            list ($min, $max) = $this->get_progress_proportion_range();
            error_log($min * 100);
        }

        // Call when done.
        if (!$this->is_in_progress_section()) {
            error_log('done');
        }
    }
}
