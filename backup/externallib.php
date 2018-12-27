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
 * External backup API.
 *
 * @package    core_backup
 * @category   external
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Backup external functions.
 *
 * @package    core_backup
 * @category   external
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.7
 */
class core_backup_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function async_backup_progress_parameters() {
        return new external_function_parameters(
            array(
                'backupid' => new external_value(PARAM_ALPHANUM, 'Backup id to get progress for', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                'courseid' => new external_value(PARAM_INT, 'course id'),
            )
        );
    }

    /**
     * Get asynchronous backup porgress.
     *
     * @param string $backupid The id of the backup to get progress for.
     * @param int $courseid The course the backup relates to.
     * @since Moodle 3.7
     */
    public static function async_backup_progress($backupid, $courseid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // Parameter validation.
        $params = self::validate_parameters(
                self::async_backup_progress_parameters(),
                array(
                    'backupid' => $backupid,
                    'courseid' => $courseid
                )
        );

        // Context validation.
        if (! ($course = $DB->get_record('course', array('id'=>$params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'error');
        }

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/backup:backupcourse', $coursecontext);

        try {
            // Get the backup controller by backup id.
            $bc = \backup_controller::load_controller($backupid);

            // Get backup status and progress.
            $status = $bc->get_status();
            $progress = $bc->get_progresscomplete();
        } catch (\backup_dbops_exception $e) {
            // If the backup has successfully completed there will be no
            // controller object to load.
            // In this case we get the info we need directly from the database.
            $backuprecord = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'status,progress', MUST_EXIST);
            $status = $backuprecord->status;
            $progress = $backuprecord->progress;
        }


        return array('status' => $status, 'progress' => $progress);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function async_backup_progress_returns() {
        return new external_single_structure(
            array(
                'status'       => new external_value(PARAM_INT, 'Backup Status'),
                'progress' => new external_value(PARAM_FLOAT, 'Backup progress'),
            )
        );
    }

}
