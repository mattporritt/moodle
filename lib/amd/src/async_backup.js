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
    var contextid; //  The course this backup progress is for.
    var restoreurl; //  The URL to view course restores.
    var typeid; //  The type of operation backup or restore.
    var backupintervalid; //  The id of the setInterval function.
    var allbackupintervalid; //  The id of the setInterval function.

    /**
     * Helper function to update UI components.
     *
     * @param string backupid The id to match elements on.
     * @param int percentage The completion percentage to apply.
     */
    function updateElement(backupid, percentage) {
        var elementbar = $('#' + backupid + '_bar');
        var percentagetext = percentage + '%';

        // Set progress bar percentage indicators
        elementbar.attr('aria-valuenow', percentage);
        elementbar.attr('width', percentagetext);
        elementbar.text(percentagetext);
    }

    /**
     * Update backup table row when an async backup completes.
     *
     * @param string backupid The id to match elements on.
     */
    function updateBackupTableRow(backupid){
        var statuscell = $('#' + backupid + '_bar').parent().parent();
        var cellsiblings = statuscell.siblings();
        var sizecell = cellsiblings[2];
        var downloadcell = cellsiblings[3];
        var restorecell = cellsiblings[4];
        var filenamecell = cellsiblings[0];

        var filename = $(filenamecell).text();

        ajax.call([{
            // Get the table data via webservice.
            methodname: 'core_backup_async_backup_links_backup',
            args: {
                'filename': filename,
                'contextid': contextid
            },
        }])[0].done(function(response) {
            // We have the data now update the UI.
            $(sizecell).html(response.filesize);
            $(downloadcell).html(response.dowloadlink);
            $(restorecell).html(response.restorelink);
            $(statuscell).html(response.status);
        });
    }

    /**
     * Update restore table row when an async restore completes.
     *
     * @param string backupid The id to match elements on.
     */
    function updateRestoreTableRow(backupid){
        var statuscell = $('#' + backupid + '_bar').parent().parent();
        var cellsiblings = statuscell.siblings();
        var coursecell = cellsiblings[0];

        ajax.call([{
            // Get the table data via webservice.
            methodname: 'core_backup_async_backup_links_restore',
            args: {
                'backupid': backupid,
                'contextid': contextid
            },
        }])[0].done(function(response) {
            // We have the data now update the UI.
            var resourcename = $(coursecell).text();
            var resourcelink = $('<a>',{'text': resourcename, 'href': response.restoreurl});

            $(statuscell).html(response.status);
            $(coursecell).html(resourcelink);

        });
    }

    /**
     * Update the Moodle user interface with the progress of
     * the backup process.
     *
     * @param object progress The progress and status of the process.
     */
    function updateProgress(progress) {
        var percentage = Math.round(progress.progress) * 100;
        var elementbar = $('#' + backupid + '_bar');
        var elementstatus = $('#' + backupid + '_status');
        var elementdetail = $('#' + backupid + '_detail');
        var elementbutton = $('#' + backupid + '_button');

        if (progress.status == 800) {
            // Process is in progress.

            // Add in progress class color to bar
            elementbar.addClass('bg-success');

            updateElement(backupid, percentage);

            // Change heading
            Str.get_string('async' + typeid + 'processing', 'backup').then(function(title) {
                elementstatus.text(title);
            });

        } else if (progress.status == 900) {
            // Process completed with error.

            // Add in fail class color to bar
            elementbar.addClass('bg-danger');

            // Remove in progress class color to bar
            elementbar.removeClass('bg-success');

            updateElement(backupid, 100);

            // Change heading and text
            Str.get_string('async' + typeid + 'error', 'backup').then(function(title) {
                elementstatus.text(title);
            });
            Str.get_string('async' + typeid + 'errordetail', 'backup').then(function(text) {
                elementdetail.text(text);
            });

            $('.backup_progress').children('span').removeClass('backup_stage_current');
            $('.backup_progress').children('span').last().addClass('backup_stage_current');

            // stop checking when we either have an error or a completion.
            clearInterval(backupintervalid);

        } else if (progress.status == 1000) {
            // Process completed successfully.

            // Add in progress class color to bar
            elementbar.addClass('bg-success');

            updateElement(backupid, 100);

            // Change heading and text
            Str.get_string('async' + typeid + 'complete', 'backup').then(function(title) {
                elementstatus.text(title);
            });

            if (typeid == 'restore') {
                ajax.call([{
                    // Get the table data via webservice.
                    methodname: 'core_backup_async_backup_links_restore',
                    args: {
                        'backupid': backupid,
                        'contextid': contextid
                    },
                }])[0].done(function(response) {
                    Str.get_string('async' + typeid + 'completedetail', 'backup', response.restoreurl).then(function(text) {
                        elementdetail.html(text);
                    });
                    Str.get_string('async' + typeid + 'completebutton', 'backup').then(function(text) {
                        elementbutton.text(text);
                        elementbutton.attr('href', response.restoreurl);
                    });
                });
            } else {
                Str.get_string('async' + typeid + 'completedetail', 'backup', restoreurl).then(function(text) {
                    elementdetail.html(text);
                });
                Str.get_string('async' + typeid + 'completebutton', 'backup').then(function(text) {
                    elementbutton.text(text);
                    elementbutton.attr('href', restoreurl);
                });
            }

            $('.backup_progress').children('span').removeClass('backup_stage_current');
            $('.backup_progress').children('span').last().addClass('backup_stage_current');

            // Stop checking when we either have an error or a completion.
            clearInterval(backupintervalid);
        }
    }

    /**
     * Update the Moodle user interface with the progress of
     * the all pending processes.
     *
     * @param object progress The progress and status of the process.
     */
    function updateProgressAll(progress) {
        progress.forEach(function(element) {
            var percentage = Math.round(element.element) * 100;
            var backupid = element.backupid;
            var elementbar = $('#' + backupid + '_bar');

            if (element.status == 800) {
                // Process is in element.

                // Add in element class color to bar
                elementbar.addClass('bg-success');

                updateElement(backupid, percentage);

            } else if (element.status == 900) {
                // Process completed with error.

                // Add in fail class color to bar
                elementbar.addClass('bg-danger');
                elementbar.addClass('complete');

                // Remove in element class color to bar
                $('#' + backupid + '_bar').removeClass('bg-success');

                updateElement(backupid, 100);

            } else if (element.status == 1000) {
                // Process completed successfully.

                // Add in element class color to bar
                elementbar.addClass('bg-success');
                elementbar.addClass('complete');

                updateElement(backupid, 100);

                // We have a successful backup. Update the UI with download and file details.
                if (typeid == 'backup') {
                    updateBackupTableRow(backupid);
                } else {
                    updateRestoreTableRow(backupid);
                }

            }

        });
    }

    /**
     * Get the progress of the backup process via ajax.
     */
    function getBackupProgress() {
        ajax.call([{
            // Get the backup progress via webservice.
            methodname: 'core_backup_async_backup_progress',
            args: {
                'backupids': [backupid],
                'contextid': contextid
            },
        }])[0].done(function(response) {
            // We have the progress now update the UI.
            updateProgress(response[0]);
        });
    }

    /**
     * Get the progress of all backup processes via ajax.
     */
    function getAllBackupProgress() {
        var backupids = [];
        var progressbars = $('.progress').find('.progress-bar').not('.complete');

        progressbars.each(function(){
            backupids.push((this.id).substring(0,32));
        });

        if (backupids.length > 0) {
            ajax.call([{
                // Get the backup progress via webservice.
                methodname: 'core_backup_async_backup_progress',
                args: {
                    'backupids': backupids,
                    'contextid': contextid
                },
            }])[0].done(function(response) {
                updateProgressAll(response);
            });
        } else {
            clearInterval(allbackupintervalid); // No more progress bars to update, stop checking.
        }
    }

    /**
     * Get status updates for all backups.
     *
     * @public
     */
    Asyncbackup.asyncBackupAllStatus = function(context) {
        contextid = context;
        allbackupintervalid = setInterval(getAllBackupProgress, checkdelay);
    };

    /**
     * Get status updates for backup.
     *
     * @public
     */
    Asyncbackup.asyncBackupStatus = function(backup, context, restore, type) {
        backupid = backup;
        contextid = context;
        restoreurl = restore;

        if (type == 'backup') {
            typeid = 'backup';
        } else {
            typeid = 'restore';
        }

        // Remove the links from the progress bar, no going back now.
        $('.backup_progress').children('a').removeAttr('href');

        //  Periodically check for progress updates and update the UI as required.
        backupintervalid = setInterval(getBackupProgress, checkdelay);

      };

      return Asyncbackup;
});
