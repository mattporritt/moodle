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
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');

/**
 * Adhoc task that performs asynchronous backups.
 *
 * @package     core
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asynchronous_backup_task extends adhoc_task {

    /**
     * Run the adhoc task and preform the backup.
     */
    public function execute() {
        global $DB;
        $started = time();

        $backupid = $this->get_custom_data()->backupid;
        $backuprecordid = $DB->get_field('backup_controllers', 'id', array('backupid' => $backupid), MUST_EXIST);
        mtrace('Processing asynchronous backup for backup: ' . $backupid);

        // Get the backup controller by backup id.
        $bc = \backup_controller::load_controller($backupid);
        $bc->set_progress(new \core\progress\db_updater($backuprecordid, 'backup_controllers', 'progress'));

        // Do some preflight checks on the backup.
        $status = $bc->get_status();
        $execution = $bc->get_execution();

        // Check that the backup is in the correct status.
        if ($status != 700) {
            throw new \moodle_exception('asyncbadstatus', 'backup', '', $status);
        }

        // Check that the backup is asynchronous.
        if ($execution != 2) {
            throw new \moodle_exception('asyncbadexecution', 'backup', '', $execution);
        }

        // Execute the backup.
        $bc->execute_plan();
        $bc->destroy();

        $duration = time() - $started;
        mtrace('Backup completed in: ' . $duration . ' seconds');
    }
}

