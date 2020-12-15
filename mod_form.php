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
 * The main pyramid configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . '/course/moodleform_mod.php');
require_once ($CFG->dirroot . '/mod/pyramid/lib.php'); 

/**
 * Module instance settings form
 */
class mod_pyramid_mod_form extends moodleform_mod
{

    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $CFG, $PAGE, $DB;

        $courseid = $PAGE->course->id;
        $context = context_course::instance($courseid);
        if (isset($_GET['update'])) {
            $id = $_GET['update'];
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($id);
            $pyramid_id = $cm->instance;
            $currentphase = $DB->get_field(MOD_PYRAMID_TABLE, 'phase', array("id"=>$pyramid_id,"course"=>$courseid));
        }
        $mform = $this->_form;

        // -------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('pyramidname', MOD_PYRAMID_LANG), array(
            'size' => '64'
        ));
        if (! empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'pyramidname', MOD_PYRAMID_LANG);

        // Adding the standard "intro" and "introformat" fields
        if ($CFG->version < 2015051100) {
            $this->add_intro_editor();
        } else {
            $this->standard_intro_elements();
        }

        if (isset($_GET['update'])) {
            $mform->addElement('hidden', 'idupdate', $id);
            $mform->setType('idupdate', PARAM_INT);
        }
        // -------------------------------------------------------------------------------
        // Adding the rest of pyramid settings, spreeading all them into this fieldset
        // or adding more fieldsets ('header' elements) if needed for better logic

        // get Users
        $users = get_enrolled_users($context, $withcapability = '', $groupid = 0, $userfields = 'u.*', $orderby = 'id', $limitform = 0, $limitnum = 0);
        $checkbox = array();
        foreach ($users as $x) {
            $uarray = $x->firstname . " " . $x->lastname;
            $checkbox[] = & $mform->createElement('advcheckbox', 'student-' . $x->id . '', NULL, $uarray);
        }
        $mform->addGroup($checkbox, 'usergroup', 'TeilnehmerInnen', array(
            ' '
        ), false);

        if (isset($_GET['update'])) {
            foreach ($users as $x) {
                if ($DB->record_exists(MOD_PYRAMID_USERS, array(
                    'userid' => $x->id,
                    'pyramid_id' => $pyramid_id
                ))) {
                    $mform->setDefault('student-' . $x->id . '', 1);
                } else {
                    $mform->setDefault('student-' . $x->id . '', 0);
                }
            }           
        }

        $mform->addElement('header', 'general', "Hilfsbeschreibungen");

        //Hinweise für Einzelphase
        $mform->addElement('textarea', 'phase1', "Hinweise für die Einzelphase", array(
            'rows' => '2', 'cols' => '155'
        ));
        $mform->setType('phase1', PARAM_TEXT);

        //Hinweise für 1. Gruppenphase
        $mform->addElement('textarea', 'phase2', "Hinweise für 1. Gruppenphase", array(
            'rows' => '2', 'cols' => '155'
        ));
        $mform->setType('phase2', PARAM_TEXT);

        //Hinweise für 2. Gruppenphase
        $mform->addElement('textarea', 'phase3', "Hinweise für die 2. Gruppenphase", array(
            'rows' => '2', 'cols' => '155'
        ));
        $mform->setType('phase3', PARAM_TEXT);

        //Hinweise für Abschlussphase
        $mform->addElement('textarea', 'phase4', "Hinweise für die Abschlussphase", array(
            'rows' => '2', 'cols' => '155'
        ));
        $mform->setType('phase4', PARAM_TEXT);


        $mform->addElement('header', 'general', "Abgabedaten");
        $mform->addElement('advcheckbox', 'modus', "Modus", "Phasen automatisch wechseln");
        if (isset($_GET['update'])) {
            if ($DB->record_exists(MOD_PYRAMID_TABLE, array(
                'id' => $pyramid_id
            ))) {
                $auto = $DB->get_field(MOD_PYRAMID_TABLE, 'autoswitch', array(
                    'id' => $pyramid_id
                ));
                if ($auto == 1) {
                    $mform->setDefault('modus', 1);
                } else {
                    $mform->setDefault('modus', 0);
                }
            }
        }

        // Abschluss 1. Teil
        $mform->addElement('date_time_selector', 'first', 'Abschluss Einzelphase');
        $mform->setDefault('first', time()+(7*24*60*60));

        // Abschluss 2. Teil
        $mform->addElement('date_time_selector', 'second', 'Abschluss 1. Gruppenphase');
        $mform->setDefault('second', time()+(14*24*60*60));

        // Abschluss 3. Teil
        $mform->addElement('date_time_selector', 'third', 'Abschluss 2. Gruppenphase');
        $mform->setDefault('third', time()+(21*24*60*60));

        // Abschluss 4. Teil
        $mform->addElement('date_time_selector', 'fourth', 'Abschluss Abschlussphase');
        $mform->setDefault('fourth', time()+(28*24*60*60));

        // Grade.
        $this->standard_grading_coursemodule_elements();

        // -------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        // -------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }

    /**
     * This adds completion rules
     * The values here are just dummies.
     * They don't work in this project until you implement some sort of grading
     * See lib.php pyramid_get_completion_state()
     */
    function add_completion_rules()
    {
        $mform = & $this->_form;
        $config = get_config(MOD_PYRAMID_FRANKY);

        // timer options
        // Add a place to set a mimumum time after which the activity is recorded complete
        $mform->addElement('static', 'mingradedetails', '', get_string('mingradedetails', MOD_PYRAMID_LANG));
        $options = array(
            0 => get_string('none'),
            20 => '20%',
            30 => '30%',
            40 => '40%',
            50 => '50%',
            60 => '60%',
            70 => '70%',
            80 => '80%',
            90 => '90%',
            100 => '40%'
        );
        $mform->addElement('select', 'mingrade', get_string('mingrade', MOD_PYRAMID_LANG), $options);

        return array(
            'mingrade'
        );
    }

    function completion_rule_enabled($data)
    {
        return ($data['mingrade'] > 0);
    }
}
