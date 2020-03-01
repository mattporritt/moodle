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
 * Adhoc task that performs asynchronous course copies.
 *
 * @package    core
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\task;

use async_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');

/**
 * Adhoc task that performs asynchronous course copies.
 *
 * @package     core
 * @copyright   2020 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asynchronous_copy_task extends adhoc_task {

    /**
     * Run the adhoc task and preform the backup.
     */
    public function execute() {
        global $DB;
        $started = time();

        $backupid = $this->get_custom_data()->backupid;
        $restoreid = $this->get_custom_data()->restoreid;
        $backuprecord = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'id, itemid', MUST_EXIST);
        $restorerecord = $DB->get_record('backup_controllers', array('backupid' => $restoreid), 'id, itemid', MUST_EXIST);

        // First backup the course.
        mtrace('Processing asynchronous course copy for course id: ' . $backuprecord->itemid);
        $bc = \backup_controller::load_controller($backupid); // Get the backup controller by backup id.
        $bc->set_progress(new \core\progress\db_updater($backuprecord->id, 'backup_controllers', 'progress'));
        $copyinfo = $bc->get_copy();

        // Do some preflight checks on the backup.
        $status = $bc->get_status();
        $execution = $bc->get_execution();
        // Check that the backup is in the correct status and
        // that is set for asynchronous execution.
        if ($status == \backup::STATUS_AWAITING && $execution == \backup::EXECUTION_DELAYED) {
            // Execute the backup.
            mtrace('Backing up course, id: ' . $backuprecord->itemid);
            $bc->execute_plan();

        } else {
            // If status isn't 700, it means the process has failed.
            // Retrying isn't going to fix it, so marked operation as failed.
            $bc->set_status(\backup::STATUS_FINISHED_ERR);
            mtrace('Bad backup controller status, is: ' . $status . ' should be 700, marking job as failed.');

        }

        $results = $bc->get_results();
        $backupbasepath = $bc->get_plan()->get_basepath();
        $file = $results['backup_destination'];
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backupbasepath);

        // Start the restore process.
        $rc = \restore_controller::load_controller($restoreid);  // Get the restore controller by backup id.
        $rc->set_progress(new \core\progress\db_updater($restorerecord->id, 'backup_controllers', 'progress'));
        $rc->convert();

        // Do some preflight checks on the restore.
        $rc->execute_precheck();
        $status = $rc->get_status();
        $execution = $rc->get_execution();

        // Check that the restore is in the correct status and
        // that is set for asynchronous execution.
        if ($status == \backup::STATUS_AWAITING && $execution == \backup::EXECUTION_DELAYED) {
            // Execute the restore.
            mtrace('Restoring into course, id: ' . $restorerecord->itemid);
            $rc->execute_plan();

        } else {
            // If status isn't 700, it means the process has failed.
            // Retrying isn't going to fix it, so marked operation as failed.
            $rc->set_status(\backup::STATUS_FINISHED_ERR);
            mtrace('Bad backup controller status, is: ' . $status . ' should be 700, marking job as failed.');

        }

        // TODO: add message notification.

        // Cleanup.
        $bc->destroy();
        $rc->destroy();

        // Set up new course name as visibility.
        $course = $DB->get_record('course', array('id' => $restorerecord->itemid), '*', MUST_EXIST);
        $course->fullname = $copyinfo->fullname;
        $course->shortname = $copyinfo->shortname;
        $course->visible = $copyinfo->visible;
        $course->idnumber = $copyinfo->idnumber;
        $DB->update_record('course', $course);

        $duration = time() - $started;
        mtrace('Copy completed in: ' . $duration . ' seconds');
    }
}

