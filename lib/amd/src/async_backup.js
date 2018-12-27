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
 * This module updates the UI during an asynchronous
 * backup or restore process.
 *
 * @module     core/async_backup
 * @package    core
 * @copyright  2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.7
 */
define(['jquery', 'core/ajax', 'core/str'], function($, ajax, Str) {
    /**
     * Module level variables.
     */
    var Asyncbackup = {};
    var checkdelay = 5000; //  How often we check for progress updates.
    var backupid; //  The backup id to get the progress for.
    var courseid; //  The course this backup progress is for.
    var restoreurl; //  The URL to view course restores.
    var intervalid; //  The id of the setIntercal function.

    /**
     * Update the Moodle user interface with the progress of
     * the backup process.
     *
     * @param object progress The progress and status of the process.
     */
    function updateUserInterface(progress) {
        //         Object { status: 700, progress: 0 }
        var percentage = Math.round(progress.progress) * 100;
        var percentagetext = percentage + '%';

        if (progress.status == 800) {
            // Process is in progress.

            // Add in progress class color to bar
            $('#' + backupid + '_bar').addClass('bg-success');

            // Set progress bar percentage indicators
            $('#' + backupid + '_bar').attr('aria-valuenow', percentage);
            $('#' + backupid + '_bar').attr('width', percentagetext);
            $('#' + backupid + '_bar').text(percentagetext);

            // Change heading
            Str.get_string('asyncbackupprocessing', 'backup').then(function(title) {
                $('#' + backupid + '_status').text(title);
            });

        } else if (progress.status == 900) {
            // Process completed with error.

            // Add in fail class color to bar
            $('#' + backupid + '_bar').removeClass('bg-danger');

            // Remove in progress class color to bar
            $('#' + backupid + '_bar').removeClass('bg-success');

            // Set progress bar percentage indicators
            $('#' + backupid + '_bar').attr('aria-valuenow', '100');
            $('#' + backupid + '_bar').attr('width', '100%');
            $('#' + backupid + '_bar').text('0%');

            // Change heading and text
            Str.get_string('asyncbackuperror', 'backup').then(function(title) {
                $('#' + backupid + '_status').text(title);
            });
            Str.get_string('asyncbackuperrordetail', 'backup').then(function(text) {
                $('#' + backupid + '_detail').text(text);
            })

            // stop checking when we either have an error or a completion.
            clearInterval(intervalid);

        } else if (progress.status == 1000) {
            // Process completed successfully.

            // Add in progress class color to bar
            $('#' + backupid + '_bar').addClass('bg-success');

            // Set progress bar percentage indicators
            $('#' + backupid + '_bar').attr('aria-valuenow', '100');
            $('#' + backupid + '_bar').attr('width', '100%');
            $('#' + backupid + '_bar').text('100%');

            // Change heading and text
            Str.get_string('asyncbackupcomplete', 'backup').then(function(title) {
                $('#' + backupid + '_status').text(title);
            });
            Str.get_string('asyncbackupcompletedetail', 'backup', restoreurl).then(function(text) {
                $('#' + backupid + '_detail').html(text);
            });
            Str.get_string('asyncbackupcompletebutton', 'backup').then(function(text) {
                $('#' + backupid + '_button').text(text);
                $('#' + backupid + '_button').attr('href', restoreurl);
            });

            // stop checking when we either have an error or a completion.
            clearInterval(intervalid);
        }
    }

    /**
     * Get the progress of the backup process via ajax.
     */
    function getProgress() {
        ajax.call([{
            // Get the backup progress via webservice.
            methodname: 'core_backup_async_backup_progress',
            args: {
                'backupid': backupid,
                'courseid': courseid
            },
        }])[0].done(function(response) {
            // We have the progress now update the UI.
            updateUserInterface(response);
        });
    }

    /**
     * Initialise the class.
     *
     * @public
     */
    Asyncbackup.init = function(backup, course, restore) {
        backupid = backup;
        courseid = course;
        restoreurl = restore;

        //  Periodically check for progress updates and update the UI as required.
        intervalid = setInterval(getProgress, checkdelay);

      };

      return Asyncbackup;
});
