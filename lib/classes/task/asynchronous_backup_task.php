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
 * Adhoc task that performs asynchronous backups.
 *
 * @package    core
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Adhoc task that performs asynchronous backups.
 *
 * @package     core
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asynchronous_backup_task extends adhoc_task {

    /**
     * Run the task.
     */
    public function execute() {
        $backupid = $this->get_custom_data()->backupid;
        mtrace('foobarbarjoobar');

        // Get the backup controller by backup id.
        $bc = \backup_controller::load_controller($backupid);
        $bc->set_progress(new \core\progress\db_updater('backup_controllers', 'progress'));

        // Do some preflight checks on the backup.
        //$rc->execute_precheck()

        // Execute the backup.
        $bc->execute_plan();
        $bc->destroy();

        // Throw error on failure.

        // Do some kind of progress tracking and add it to db.

    }
}

