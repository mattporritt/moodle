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
 * A javascript module for the time in the assign module.
 *
 * @copyright  2020 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Module level variables.
 */

// Timestamp at which time runs out.
var endtime = 0;

// This records the id of the timeout that updates the clock periodically.
var timeoutid = null;

/**
 * Helper method to convert time remaining in seconds into HH:MM:SS format.
 *
 * @method init
 * @param {Integer} secs Time remaining in seconds to get value for.
 * @return {String} Time remaining in HH:MM:SS format.
 */
const timeremaining = (secs) => {
    const hours = Math.floor(secs / 3600);
    const minutes = Math.floor(secs / 60) % 60;
    const seconds = secs % 60;

    return [hours, minutes, seconds]
        .map(v => v < 10 ? "0" + v : v)
        .filter((v, i) => v !== "00" || i > 0)
        .join(":");
};

/**
 * Stop the timer, if it is running.
 *
 * @method stop
 */
const stop = () => {
    if (timeoutid) {
        clearTimeout(timeoutid);
    }
};

/**
 * Function to update the clock with the current time left.
 *
 * @method update
 */
const update = () => {
    const now = new Date().getTime();
    const secondsleft = Math.floor((endtime - now) / 1000);
    let assignTimer = document.getElementById('assign-timer');
    let assignTimerLeft = document.getElementById('assign-time-left');

    // If time has expired, set the hidden form field that says time has expired.
    if (secondsleft < 0) {
        assignTimer.classList.add('alert');
        assignTimer.classList.add('alert-danger');
        stop();
        return;
    } else if (secondsleft < 300) { // Add danger style when less than 5 minutes left.
        assignTimer.classList.remove('alert-warning');
        assignTimer.classList.add('alert');
        assignTimer.classList.add('alert-danger');
    } else if (secondsleft < 900) { // Add warning style when less than 15 minutes left.
        assignTimer.classList.remove('alert-danger');
        assignTimer.classList.add('alert');
        assignTimer.classList.add('alert-warning');
    }

    // Update the time display.
    assignTimerLeft.innerHTML = timeremaining(secondsleft);

    // Arrange for this method to be called again soon.
    timeoutid = setTimeout(update, 500);
};

/**
 * Set up the submission timer.
 *
 * @method init
 * @param {Integer} timerstartvalue Submissition time remaining, in seconds.
 */
export const init = (timerstartvalue) => {
    let assignTimer = document.getElementById('assign-timer');
    endtime = M.pageloadstarttime.getTime() + (timerstartvalue * 1000);
    update();
    assignTimer.style.display = ('block');
};
