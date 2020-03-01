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
 * Course copy class, handles procesing data submitted by UI copy form
 * and sets up the course copy process.
 *
 * @package    core_backup
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_backup\copy;

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Course copy class, handles procesing data submitted by UI copy form
 * and sets up the course copy process.
 *
 * @package    core_backup
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_backup_copy  {

    /**
     * The data submitted to the course copy form.
     *
     * @var \stdClass
     */
    private $formdata;

    public function __construct(\stdClass $formdata) {
        $this->formdata = $formdata;
    }

    public function create_copy() : array {
        global $USER;
        $copyids = array();

        // Create the initial backupcontoller.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $this->formdata->courseid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_COPY, $USER->id, \backup::RELEASESESSION_YES);
        $copyids['backupid'] = $bc->get_backupid();

        // Create the initial restore contoller.
        $newcourseid = \restore_dbops::create_new_course(
            $this->formdata->fullname, $this->formdata->shortname, $this->formdata->category);
        $rc = new \restore_controller($copyids['backupid'], $newcourseid,
            \backup::INTERACTIVE_NO, \backup::MODE_COPY, $USER->id,
            \backup::TARGET_NEW_COURSE);
        $copyids['restoreid'] = $rc->get_restoreid();

        // Configure the controllers based on the submitted data.
        $copydata = $this->formdata;
        $copydata->copyids = $copyids;
        $bc->set_copy($copydata);
        $bc->set_status(\backup::STATUS_AWAITING);
        $bc->get_status();

        $rc->set_copy($copydata);
        $rc->save_controller();

        // Create the ad-hoc task to perform the course copy.
        $asynctask = new \core\task\asynchronous_copy_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data($copyids);
        \core\task\manager::queue_adhoc_task($asynctask);

        // Clean up the controllers.
        $bc->destroy();

        return $copyids;
    }

    /**
     * Filters an array of copy records by course ID.
     *
     * @param array $copyrecords
     * @param int $courseid
     * @return array $copies Filtered array of records.
     */
    static private function filter_copies_course(array $copyrecords, int $courseid) : array {
        $copies = array();

        foreach ($copyrecords as $copyrecord) {
            if($copyrecord->operation == \backup::OPERATION_RESTORE) { // Restore records.
                if ($copyrecord->status == \backup::STATUS_FINISHED_OK
                    || $copyrecord->status == \backup::STATUS_FINISHED_ERR) {
                        continue;
                } else {
                    $rc = \restore_controller::load_controller($copyrecord->backupid);
                    if ($rc->get_copy()->courseid == $courseid) {
                        $copies[] = $copyrecord;
                    }
                }
            } else { // Backup records.
                if ($copyrecord->itemid == $courseid) {
                    $copies[] = $copyrecord;
                }
            }
        }
        return $copies;
    }

    /**
     * Get the in progress course copy operations for a user.
     *
     * @param int $userid User id to get the course copies for.
     * @param int $courseid The optional source coourse id to get copies for.
     * @return array $copies Details of the inprogress copies.
     */
    static public function get_copies(int $userid, int $courseid=0) : array {
        global $DB;
        $copies = array();
        $select = 'userid = ? AND execution = ? AND purpose = ?';
        $params = array($userid, \backup::EXECUTION_DELAYED, \backup::MODE_COPY);
        $copyrecords = $DB->get_records_select(
            'backup_controllers',
            $select,
            $params,
            'timecreated DESC',
            'backupid, itemid, operation, status, timecreated');

       foreach ($copyrecords as $copyrecord) {
           $copy = new \stdClass();
           $copy->itemid = $copyrecord->itemid;
           $copy->time = $copyrecord->timecreated;
           $copy->operation = $copyrecord->operation;
           $copy->status = $copyrecord->status;
           $copy->backupid = null;
           $copy->restoreid = null;

           if($copyrecord->operation == \backup::OPERATION_RESTORE) {
               $copy->restoreid = $copyrecord->backupid;
               // If record is complete or complete with errors, it means the backup also completed.
               // It also means there are no controllers. In this case just skip and move on.
               if ($copyrecord->status == \backup::STATUS_FINISHED_OK
                   || $copyrecord->status == \backup::STATUS_FINISHED_ERR) {
                   continue;
                   } else if ($copyrecord->status > \backup::STATUS_REQUIRE_CONV) {
                   // If record is a restore and it's in progress (>200), it means the backup is finished.
                   // In this case return the restore.
                   $rc = \restore_controller::load_controller($copyrecord->backupid);
                   $course = get_course($rc->get_copy()->courseid);

                   $copy->source = $course->shortname;
                   $copy->sourceid = $course->id;
                   $copy->destination = $rc->get_copy()->shortname;
                   $copy->backupid = $rc->get_copy()->copyids['backupid'];
                   $rc->destroy();

                   } else if ($copyrecord->status == \backup::STATUS_REQUIRE_CONV) {
                   // If record is a restore and it is waiting (=200), load the controller
                   // and check the status of the backup.
                   // If the backup has finished successfully we have and edge case. Process as per in progress restore.
                   // If the backup has any other code it will be handled by backup processing.
                   $rc = \restore_controller::load_controller($copyrecord->backupid);
                   $bcid = $rc->get_copy()->copyids['backupid'];
                   $backuprecord = $copyrecords[$bcid];
                   $backupstatus = $backuprecord->status;
                   if ($backupstatus == \backup::STATUS_FINISHED_OK) {
                       $course = get_course($rc->get_copy()->courseid);

                       $copy->source = $course->shortname;
                       $copy->sourceid = $course->id;
                       $copy->destination = $rc->get_copy()->shortname;
                       $copy->backupid = $rc->get_copy()->copyids['backupid'];
                   } else {
                       continue;
                   }
               }
           } else { // Record is a backup.
               $copy->backupid = $copyrecord->backupid;
               if ($copyrecord->status == \backup::STATUS_FINISHED_OK
                   || $copyrecord->status == \backup::STATUS_FINISHED_ERR) {
                   // If successfully finished then skip it. Restore procesing will look after it.
                   // If it has errored then we can't go any further.
                   continue;
               } else {
                   // If is in progress then process it.
                   $bc = \backup_controller::load_controller($copyrecord->backupid);
                   $course = get_course($bc->get_courseid());

                   $copy->source = $course->shortname;
                   $copy->sourceid = $course->id;
                   $copy->destination = $bc->get_copy()->shortname;
                   $copy->restoreid = $bc->get_copy()->copyids['restoreid'];
               }
           }

           $copies[] = $copy;
       }

       // Extra processing to filter records for a given course.
       if ($courseid != 0 ) {
           $copies = self::filter_copies_course($copies, $courseid);
       }

       return $copies;
    }

}