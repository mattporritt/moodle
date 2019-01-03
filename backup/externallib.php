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
     * @param int $contextid The context the backup relates to.
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

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function async_backup_links_backup_parameters() {
        return new external_function_parameters(
                array(
                    'filename' => new external_value(PARAM_FILE, 'Backup filename', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                    'contextid' => new external_value(PARAM_INT, 'Context id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                )
         );
    }

    /**
     * Gets backup table row data.
     *
     * @param string $filename The file name of the backup file.
     * @param int $contextid The context the backup relates to.
     * @since Moodle 3.7
     */
    public static function async_backup_links_backup($filename, $contextid) {
        global $OUTPUT;

        // Parameter validation.
        $params = self::validate_parameters(
                self::async_backup_links_backup_parameters(),
                    array(
                        'filename' => $filename,
                        'contextid' => $contextid
                    )
                );

        // Context validation.
        list($context, $course, $cm) = get_context_info_array($contextid);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);

        if ($cm) {
            $instanceid = $cm->id;
            $filearea = 'activity';
        } else {
            $instanceid = $course->id;
            $filearea = 'course';
        }

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, 'backup', $filearea, 0, '/', $filename);

        $fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename(),
                true
                );
        $params = array();
        $params['action'] = 'choosebackupfile';
        $params['filename'] = $file->get_filename();
        $params['filepath'] = $file->get_filepath();
        $params['component'] = $file->get_component();
        $params['filearea'] = $file->get_filearea();
        $params['filecontextid'] = $file->get_contextid();
        $params['contextid'] = $contextid;
        $params['itemid'] = $file->get_itemid();
        $restoreurl = new moodle_url('/backup/restorefile.php', $params);
        $restorelink = html_writer::link($restoreurl, get_string('restore'));
        $downloadlink = html_writer::link($fileurl, get_string('download'));
        $filesize = display_size ($file->get_filesize());

        $icon = $OUTPUT->render(new \pix_icon('i/checked', get_string('successful', 'backup')));
        $status = html_writer::span($icon, 'action-icon');

        $results = array('filesize' => $filesize, 'dowloadlink' => $downloadlink, 'restorelink' => $restorelink, 'status' => $status);

        return $results;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function async_backup_links_backup_returns() {
        return new external_single_structure(
                    array(
                       'filesize'   => new external_value(PARAM_TEXT, 'Backup file size'),
                       'dowloadlink' => new external_value(PARAM_RAW_TRIMMED, 'Backup download link'),
                       'restorelink' => new external_value(PARAM_RAW_TRIMMED, 'Backup restore link'),
                       'status' => new external_value(PARAM_RAW_TRIMMED, 'Backup status'),
                    ), 'Table row data.');
    }
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function async_backup_links_restore_parameters() {
        return new external_function_parameters(
                array(
                        'backupid' => new external_value(PARAM_ALPHANUMEXT, 'Backup id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                        'contextid' => new external_value(PARAM_INT, 'Context id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                )
                );
    }

    /**
     * Gets backup table row data.
     *
     * @param string $filename The file name of the backup file.
     * @param int $contextid The context the backup relates to.
     * @since Moodle 3.7
     */
    public static function async_backup_links_restore($backupid, $contextid) {
        global $OUTPUT, $DB;

        // Parameter validation.
        $params = self::validate_parameters(
                self::async_backup_links_restore_parameters(),
                    array(
                            'backupid' => $backupid,
                            'contextid' => $contextid
                    )
                );

        // Context validation.
        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);

        $backuprec = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'type, itemid', MUST_EXIST);
        if ($backuprec->type == 'activity') {  // Get activity context.
            $newcontext = context_module::instance($backuprec->itemid);
        } else { // Course or Section which have the same context getter.
            $newcontext = context_course::instance($backuprec->itemid);
        }

        $restoreurl = $newcontext->get_url()->out_as_local_url();
        $icon = $OUTPUT->render(new \pix_icon('i/checked', get_string('successful', 'backup')));
        $status = html_writer::span($icon, 'action-icon');

        $results = array('restoreurl' => $restoreurl, 'status' => $status);

        return $results;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function async_backup_links_restore_returns() {
        return new external_single_structure(
                array(
                        'restoreurl' => new external_value(PARAM_URL, 'Restore url'),
                        'status' => new external_value(PARAM_RAW_TRIMMED, 'Restore status'),
                ), 'Table row data.');
    }
}
