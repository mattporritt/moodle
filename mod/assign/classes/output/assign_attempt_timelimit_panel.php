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
 * Represents the timer panel.
 *
 * @package   mod_assign
 * @copyright  2020 Ilya Tregubov <ilyatregubov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_assign\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents the timer panel.
 *
 * @package   mod_assign
 * @copyright  2020 Ilya Tregubov <ilyatregubov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_attempt_timelimit_panel {
    /** @var \stdClass assign submission attempt.*/
    protected $submissionattempt;
    /** @var object assign object.*/
    protected $assign;

    /**
     * Constructor.
     *
     * @param \stdClass $submissionattempt assign submission attempt.
     * @param object $assign assign object.
     */
    public function __construct(\stdClass $submissionattempt, $assign) {
        $this->submission = $submissionattempt;
        $this->assign = $assign;
    }

    /**
     * Render timer.
     *
     * @param renderer $output the assign renderer.
     * @return string The timer markup.
     */
    public function render_panel(renderer $output) {
        return $this->countdown_timer(time());
    }

    /**
     * Return the HTML of the assign timer.
     *
     * @param int $timenow the time to consider as 'now'.
     * @return string HTML content.
     */
    public function countdown_timer($timenow) {

        $timeleft = $this->get_time_left_display($timenow);
        if ($timeleft !== false) {
            $timerstartvalue = $timeleft;
            $this->initialise_timer($timerstartvalue);
        } else {
            return \html_writer::tag('div', \html_writer::tag('span', '0:00:00', array('id' => 'assign-time-left')),
                array('id' => 'assign-timer', 'role' => 'timer',
                    'aria-atomic' => 'true', 'aria-relevant' => 'text'));
        }

        return \html_writer::tag('div', \html_writer::tag('span', '', array('id' => 'assign-time-left')),
            array('id' => 'assign-timer', 'role' => 'timer',
                'aria-atomic' => 'true', 'aria-relevant' => 'text'));
    }

    /**
     * Compute end time for this assign attempt.
     *
     * @return int the time when assign attempt is due.
     */
    public function end_time() {
        $timedue = $this->submission->timecreated + $this->assign->timelimit;
        if ($this->assign->duedate) {
            $timedue = min($timedue, $this->assign->duedate);
        } else if ($this->assign->cutoffdate) {
            $timedue = min($timedue, $this->assign->cutoffdate);
        }
        return $timedue;
    }

    /**
     * Compute time left for this assign attempt.
     *
     * @param int $timenow the time to consider as 'now'.
     * @return int the time left for this assign attempt.
     */
    public function time_left_display($timenow) {
        $endtime = $this->end_time();
        if ($timenow > $endtime) {
            return false;
        }
        return $endtime - $timenow;
    }

    /**
     * Compute what should be displayed to the user for time remaining in this attempt.
     *
     * @param int $timenow the time to consider as 'now'.
     * @return int|false the number of seconds remaining for this attempt.
     *      False if no limit should be displayed.
     */
    public function get_time_left_display($timenow) {
        $timeleft = false;
        $ruletimeleft = $this->time_left_display($timenow);
        if ($ruletimeleft !== false && ($timeleft === false || $ruletimeleft < $timeleft)) {
            $timeleft = $ruletimeleft;
        }
        return $timeleft;
    }

    /**
     * Output the JavaScript required to initialise the countdown timer.
     * @param int $timerstartvalue time remaining, in seconds.
     */
    public function initialise_timer($timerstartvalue) {
        global $PAGE;

        $options = array($timerstartvalue);
        $PAGE->requires->js_call_amd('mod_assign/timer', 'init', $options);
    }

}
