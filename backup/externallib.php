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
               // 'backupid' => new external_value(PARAM_ALPHANUM, 'Backup id to get progress for', VALUE_REQUIRED, null, NULL_ALLOWED),
                'backupids' => new external_multiple_structure(
                        new external_value(PARAM_ALPHANUM, 'Backup id to get progress for', VALUE_REQUIRED, null, NULL_ALLOWED),
                        'Backup id to get progress for', VALUE_REQUIRED
                 ),
                'contextid' => new external_value(PARAM_INT, 'Context id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
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
    public static function async_backup_progress($backupids, $contextid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // Parameter validation.
        $params = self::validate_parameters(
                self::async_backup_progress_parameters(),
                array(
                    'backupids' => $backupids,
                    'contextid' => $contextid
                )
        );

        // Context validation.
        list($context, $course, $cm) = get_context_info_array($contextid);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);

        if ($cm) {
            $instanceid = $cm->id;
        } else {
            $instanceid = $course->id;
        }

        // Get backup records directly from database.
        // If the backup has successfully completed there will be no controller object to load.
        $results = array();
        foreach ($backupids as $backupid) {
            $backuprecord = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'status, progress', MUST_EXIST);
            $status = $backuprecord->status;
            $progress = $backuprecord->progress;
            $results[] = array('status' => $status, 'progress' => $progress, 'backupid' => $backupid);
        }

        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function async_backup_progress_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'status'   => new external_value(PARAM_INT, 'Backup Status'),
                    'progress' => new external_value(PARAM_FLOAT, 'Backup progress'),
                    'backupid' => new external_value(PARAM_ALPHANUM, 'Backup id'),
                ), 'Backup completion status'
          ), 'Backup data'
        );
    }

}
