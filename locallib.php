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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for module pyramid
 *
 * All the pyramid specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once (__DIR__ . '/lib.php'); // we extend this library here
require_once ($CFG->libdir . '/gradelib.php'); // we use some rounding and comparing routines here
require_once ($CFG->libdir . '/filelib.php');

class pyramid
{

    const PHASE_1 = 10;

    const PHASE_2 = 20;

    const PHASE_3 = 30;

    const PHASE_4 = 40;

    /** @var stdclass pyramid record from database */
    public $dbrecord;

    /** @var cm_info course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int current phase of pyramid, for example {@link pyramid::PHASE_1} */
    public $phase;

    /** @var int workshop instance identifier */
    public $id;

    /** @var string workshop activity name */
    public $name;

    /** @var bool automatically switch to the assessment phase after the submissions deadline */
    public $phaseswitchassessment;

    public $intro;

    public $introformat;


    /**
     * Initializes the pyramid API instance using the data from DB
     *
     * Makes deep copy of all passed records properties.
     *
     * For unit testing only, $cm and $course may be set to null. This is so that
     * you can test without having any real database objects if you like. Not all
     * functions will work in this situation.
     *
     * @param stdClass $dbrecord
     *            Pyramid instance data from {workshop} table
     * @param stdClass|cm_info $cm
     *            Course module record
     * @param stdClass $course
     *            Course record from {course} table
     * @param stdClass $context
     *            The context of the pyramid instance
     */
    public function __construct(stdclass $dbrecord, $cm, $course, stdclass $context = null)
    {
        $this->dbrecord = $dbrecord;
        foreach ($this->dbrecord as $field => $value) {
            if (property_exists('pyramid', $field)) {
                $this->{$field} = $value;
            }
        }
        if (is_null($cm) || is_null($course)) {
            throw new coding_exception('Must specify $cm and $course');
        }
        $this->course = $course;
        if ($cm instanceof cm_info) {
            $this->cm = $cm;
        } else {
            $modinfo = get_fast_modinfo($course);
            $this->cm = $modinfo->get_cm($cm->id);
        }
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }
    }

    /**
     *
     * @return moodle_url of this workshop's view page
     */
    public function view_url()
    {
        global $CFG;
        return new moodle_url('/mod/pyramid/view.php', array(
            'id' => $this->cm->id
        ));
    }

    /**
     *
     * @param int $phasecode
     *            The internal phase code
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function switchphase_url($phasecode)
    {
        global $CFG;
        $phasecode = clean_param($phasecode, PARAM_INT);
        return new moodle_url('/mod/pyramid/switchphase.php', array(
            'cmid' => $this->cm->id,
            'phase' => $phasecode
        ));
    }

    /**
     * Switch to a new workshop phase
     *
     * Modifies the underlying database record. You should terminate the script shortly after calling this.
     *
     * @param int $newphase
     *            new phase code
     * @return bool true if success, false otherwise
     */
    public function switch_phase($newphase)
    {
        global $DB;

        $known = $this->available_phases_list();
        if (! isset($known[$newphase])) {
            return false;
        }

        /*
         * if (self::PHASE_CLOSED == $newphase) {
         * // push the grades into the gradebook
         * $workshop = new stdclass();
         * foreach ($this as $property => $value) {
         * $workshop->{$property} = $value;
         * }
         * $workshop->course = $this->course->id;
         * $workshop->cmidnumber = $this->cm->id;
         * $workshop->modname = 'workshop';
         * workshop_update_grades($workshop);
         * }
         */

        $DB->set_field('pyramid', 'phase', $newphase, array(
            'id' => $this->id
        ));

        $DB->set_field('pyramid', 'autoswitch', 0, array(
            'id' => $this->id
        ));
        $this->phase = $newphase;

        return true;
    }

    protected function available_phases_list()
    {
        return array(
            self::PHASE_1 => true,
            self::PHASE_2 => true,
            self::PHASE_3 => true,
            self::PHASE_4 => true
        );
    }

    /**
     *
     * @return moodle_url of the page to view a submission, defaults to the own one
     */
    public function submission_url($id = null)
    {
        global $CFG;
        return new moodle_url('/mod/pyramid/edit.php', array(
            'cmid' => $this->cm->id
        ));
    }

    /**         
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function notification_url()
    {
        global $CFG;
        return new moodle_url('/mod/pyramid/notification.php', array(
            'cmid' => $this->cm->id
        ));
    }

    public function check_enrollment($userid,$pyramidid){
      global $DB;
      if($DB->record_exists('pyramid_users', array('pyramid_id'=>$pyramidid, 'userid'=>$userid))){
          return true;
      }
      else{
          return false;
      }
    }
}

class pyramid_user_plan implements renderable
{

    /** @var int id of the user this plan is for */
    public $userid;

    /** @var workshop */
    public $pyramid;

    /** @var array of (stdclass)tasks */
    public $phases = array();

    /** @var null|array of example submissions to be assessed by the planner owner */
    protected $examples = null;

    /**
     * Prepare an individual pyramid plan for the given user.
     *
     * @param pyramid $pyyramid
     *            instance
     * @param int $userid
     *            whom the plan is prepared for
     */
    public function __construct(pyramid $pyramid, $userid)
    {
        global $DB;

        $this->pyramid = $pyramid;
        $this->userid = $userid;

        $courseid = $pyramid->course->id;
        $instanceid = $pyramid->id;
        // -----------------------------------------
        // * PHASE 1 | phase 2 | phase 3 | phase 4 |
        // -----------------------------------------
        $phase = new stdclass();
        // $phase->title = get_string('phase1', MOD_PYRAMID_LANG);
        $phase->title = get_string('phase1', MOD_PYRAMID_LANG);
        $phase->tasks = array();

        if (has_capability('mod/pyramid:submit', $pyramid->context, $userid, false) and $pyramid->phase == pyramid::PHASE_1) {

            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'pyramid');
            $task->link = $pyramid->submission_url();
            $phase->tasks['submit'] = $task;
        }

        if ($pyramid->phase == pyramid::PHASE_1) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            if ($DB->record_exists(MOD_PYRAMID_TABLE, array(
                'course' => (int)$courseid,
                'id' => (int)$instanceid
            ))) {
                if (($DB->get_field(MOD_PYRAMID_TABLE, 'phase1', array(
                    'course' => (int)$courseid,
                    "id" => (int)$instanceid
                )) == false) && has_capability('mod/pyramid:itemedit', $pyramid->context, $userid, false)) {
                    $task = new stdclass();
                    $task->title = "Bitte einen Informationstext hinzufuegen.";
                    $task->completed = 'info';
                    $phase->tasks['phase1'] = $task;
                } else {
                    $trim = trim($DB->get_field(MOD_PYRAMID_TABLE, 'phase1', array(
                        'course' => (int)$courseid,
                        "id" => (int)$instanceid
                    )));
                    if (! empty($trim)) {
                        $task = new stdclass();
                        $task->title = $DB->get_field(MOD_PYRAMID_TABLE, 'phase1', array(
                            'course' => (int)$courseid,
                            "id" => (int)$instanceid
                        ));
                        $task->completed = 'info';
                        $phase->tasks['phase1'] = $task;
                    }
                }
            }
        }

        $this->phases[pyramid::PHASE_1] = $phase;

        // -----------------------------------------
        // * phase 1 | PHASE 2 | phase 3 | phase 4 |
        // -----------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phase2', 'pyramid');
        $phase->tasks = array();

        if (has_capability('mod/pyramid:submit', $pyramid->context, $userid, false) and $pyramid->phase == pyramid::PHASE_2) {

            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'pyramid');
            $task->link = $pyramid->submission_url();
            $phase->tasks['submit'] = $task;
        }

        // if (empty($phase->tasks) and $pyramid->phase == pyramid::PHASE_2) {
        if ($pyramid->phase == pyramid::PHASE_2) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            if ($DB->record_exists(MOD_PYRAMID_TABLE, array(
                'course' => (int)$courseid,
                'id' => (int)$instanceid
            ))) {
                if (($DB->get_field(MOD_PYRAMID_TABLE, 'phase2', array(
                    'course' => (int)$courseid,
                    "id" => (int)$instanceid
                )) == false) && has_capability('mod/pyramid:itemedit', $pyramid->context, $userid, false)) {
                    $task = new stdclass();
                    $task->title = "Bitte einen Informationstext hinzufuegen.";
                    $task->completed = 'info';
                    $phase->tasks['phase1'] = $task;
                } else {

                    $trim = trim($DB->get_field(MOD_PYRAMID_TABLE, 'phase2', array(
                        'course' => (int)$courseid,
                        "id" => (int)$instanceid
                    )));
                    if (! empty($trim)) {
                        $task = new stdclass();
                        $task->title = $DB->get_field(MOD_PYRAMID_TABLE, 'phase2', array(
                            'course' => (int)$courseid,
                            "id" => (int)$instanceid
                        ));
                        $task->completed = 'info';
                        $phase->tasks['phase1'] = $task;
                    }
                }
            }
        }

        $this->phases[pyramid::PHASE_2] = $phase;

        // -----------------------------------------
        // * phase 1 | phase 2 | PHASE 3 | phase 4 |
        // -----------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phase3', 'pyramid');
        $phase->tasks = array();

        if (has_capability('mod/pyramid:submit', $pyramid->context, $userid, false) and $pyramid->phase == pyramid::PHASE_3) {

            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'pyramid');
            $task->link = $pyramid->submission_url();
            $phase->tasks['submit'] = $task;
        }

        if ($pyramid->phase == pyramid::PHASE_3) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            if ($DB->record_exists(MOD_PYRAMID_TABLE, array(
                'course' => (int)$courseid,
                'id' => (int)$instanceid
            ))) {
                if (($DB->get_field(MOD_PYRAMID_TABLE, 'phase3', array(
                    'course' => (int)$courseid,
                    "id" => (int)$instanceid
                )) == false) && has_capability('mod/pyramid:itemedit', $pyramid->context, $userid, false)) {
                    $task = new stdclass();
                    $task->title = "Bitte einen Informationstext hinzufuegen.";
                    $task->completed = 'info';
                    $phase->tasks['phase1'] = $task;
                } else {

                    $trim = trim($DB->get_field(MOD_PYRAMID_TABLE, 'phase3', array(
                        'course' => (int)$courseid,
                        "id" => (int)$instanceid
                    )));
                    if (! empty($trim)) {
                        $task = new stdclass();
                        $task->title = $DB->get_field(MOD_PYRAMID_TABLE, 'phase3', array(
                            'course' => (int)$courseid,
                            "id" => (int)$instanceid
                        ));
                        $task->completed = 'info';
                        $phase->tasks['phase1'] = $task;
                    }
                }
            }
        }

        $this->phases[pyramid::PHASE_3] = $phase;

        // -----------------------------------------
        // * phase 1 | phase 2 | phase 3 | PHASE 4 |
        // -----------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phase4', 'pyramid');
        $phase->tasks = array();

        if (has_capability('mod/pyramid:submit', $pyramid->context, $userid, false) and $pyramid->phase == pyramid::PHASE_4) {

            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'pyramid');
            $task->link = $pyramid->submission_url();
            $phase->tasks['submit'] = $task;
        }

        if ($pyramid->phase == pyramid::PHASE_4) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            if ($DB->record_exists(MOD_PYRAMID_TABLE, array(
                'course' => (int)$courseid,
                'id' => (int)$instanceid
            ))) {
                if (($DB->get_field(MOD_PYRAMID_TABLE, 'phase4', array(
                    'course' => (int)$courseid,
                    "id" => (int)$instanceid
                )) == false) && has_capability('mod/pyramid:itemedit', $pyramid->context, $userid, false)) {
                    $task = new stdclass();
                    $task->title = "Bitte einen Informationstext hinzufuegen.";
                    $task->completed = 'info';
                    $phase->tasks['phase1'] = $task;
                } else {

                    $trim = trim($DB->get_field(MOD_PYRAMID_TABLE, 'phase4', array(
                        'course' => (int)$courseid,
                        "id" => (int)$instanceid
                    )));
                    if (! empty($trim)) {
                        $task = new stdclass();
                        $task->title = $DB->get_field(MOD_PYRAMID_TABLE, 'phase4', array(
                            'course' => (int)$courseid,
                            "id" => (int)$instanceid
                        ));
                        $task->completed = 'info';
                        $phase->tasks['phase1'] = $task;
                    }
                }
            }
        }

        $this->phases[pyramid::PHASE_4] = $phase;

        foreach ($this->phases as $phasecode => $phase) {
            $phase->title = isset($phase->title) ? $phase->title : '';
            $phase->tasks = isset($phase->tasks) ? $phase->tasks : array();

            $pyramid->phase = (int) $pyramid->phase;

            if ($phasecode == $pyramid->phase) {
                $phase->active = true;
            } else {
                $phase->active = false;
            }
            if (! isset($phase->actions)) {
                $phase->actions = array();
            }

            foreach ($phase->tasks as $taskcode => $task) {
                $task->title = isset($task->title) ? $task->title : '';
                $task->link = isset($task->link) ? $task->link : null;
                $task->details = isset($task->details) ? $task->details : '';
                $task->completed = isset($task->completed) ? $task->completed : null;
            }
        }

        if (has_capability('mod/pyramid:switchphase', $pyramid->context, $userid) && !$pyramid->check_enrollment($userid,$pyramid->id)) {
            $nextphases = array(
                pyramid::PHASE_1 => pyramid::PHASE_2,
                pyramid::PHASE_2 => pyramid::PHASE_3,
                pyramid::PHASE_3 => pyramid::PHASE_4
            );

            foreach ($this->phases as $phasecode => $phase) {
                if ($phase->active) {
                    if (isset($nextphases[$pyramid->phase])) {
                        $task = new stdClass();
                        $task->title = "Zur naechsten Phase wechseln";
                        $task->link = $pyramid->switchphase_url($nextphases[$pyramid->phase]);
                        $task->details = '';
                        $task->completed = null;
                        $phase->tasks['switchtonextphase'] = $task;
                    }
                } else {
                    $action = new stdclass();
                    $action->type = 'switchphase';
                    $action->url = $pyramid->switchphase_url($phasecode);
                    $phase->actions[] = $action;
                }
            }
        }
    }
}

