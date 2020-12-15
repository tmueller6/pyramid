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
 * Prints a particular instance of pyramid
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once (dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once (dirname(__FILE__) . '/lib.php');
require_once (__DIR__ . '/locallib.php');
require_once ('./edit_form.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT); // pyramid instance ID - it should be named as the first character of the module

if ($id) {
    $cm = get_coursemodule_from_id('pyramid', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array(
        'id' => $cm->course
    ), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('pyramid', array(
        'id' => $cm->instance
    ), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance = $DB->get_record('pyramid', array(
        'id' => $n
    ), '*', MUST_EXIST);
    $course = $DB->get_record('course', array(
        'id' => $moduleinstance->course
    ), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('pyramid', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', MOD_PYRAMID_LANG));
}
$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/pyramid/view.php', array(
    'id' => $cm->id
));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

// Diverge logging logic at Moodle 2.7
if ($CFG->version < 2014051200) {
    add_to_log($course->id, 'pyramid', 'view', "view.php?id={$cm->id}", $moduleinstance->name, $cm->id);
} else {
    // Trigger module viewed event.
    $event = \mod_pyramid\event\course_module_viewed::create(array(
        'objectid' => $moduleinstance->id,
        'context' => $modulecontext
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('pyramid', $moduleinstance);
    $event->trigger();
}


$pyramid = new pyramid($moduleinstance, $cm, $course);

// if we got this far, we can consider the activity "viewed"
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// are we a teacher or a student?
$mode = "view";

//Individueller Userplan zur Anzeige der Aktivität
$userplan = new pyramid_user_plan($pyramid, $USER->id);

// / Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');
$PAGE->requires->jquery();

// Get an admin settings
//$config = get_config(MOD_PYRAMID_FRANKY);

// automatisches Wechseln von Phasen
if ($moduleinstance->autoswitch == 1) {
    $opts = Array();
    $opts['pyramid_id'] = $moduleinstance->id;
    $opts['first'] = $moduleinstance->first;
    $opts['second'] = $moduleinstance->second;
    $opts['third'] = $moduleinstance->third;
    $opts['fourth'] = $moduleinstance->fourth;

    $PAGE->requires->jquery();
    $PAGE->requires->js_init_call('M.mod_pyramid.switchphase', array(
        $opts
    ), false);
}

//mögliche Erinnerung für vergessene Abgaben
/*$users = $DB->get_fieldset_select('pyramid_users', 'userid', 'pyramid_id='.$pyramid->id.'');
$users = json_encode($users);
$opts = Array();
$opts['cmid'] = $cm->id;
$opts['phase'] = $moduleinstance->phase;
$opts['first'] = $moduleinstance->first;
$opts['second'] = $moduleinstance->second;
$opts['third'] = $moduleinstance->third;
$opts['fourth'] = $moduleinstance->fourth;
$opts['name'] = $moduleinstance->name;
$opts['users'] = $users;
$opts['creator'] = $pyramid->creatorid;
$PAGE->requires->jquery();
$PAGE->requires->js_init_call('M.mod_pyramid.send_message', array(
    $opts
), false);*/

// This puts all our display logic into the renderer.php file in this plugin
// theme developers can override classes there, so it makes it customizable for others
// to do it this way.
$renderer = $PAGE->get_renderer('mod_pyramid');

// From here we actually display the page.

echo $renderer->notabsheader();
echo $renderer->show_name($moduleinstance->name);
echo $renderer->show_intro($moduleinstance, $cm);

if ($moduleinstance->autoswitch == 1) {
    switch ($moduleinstance->phase) {
        case 10:
            echo $renderer->show_name("Abgabe: " . date('d.m.Y H:m', $moduleinstance->first));
            break;
        case 20:
            echo $renderer->show_name("Abgabe: " . date('d.m.Y H:m', $moduleinstance->second));
            break;
        case 30:
            echo $renderer->show_name("Abgabe: " . date('d.m.Y H:m', $moduleinstance->third));
            break;
        case 40:
            echo $renderer->show_name("Abgabe: " . date('d.m.Y H:m', $moduleinstance->fourth));
            break;
    }
}
echo $renderer->render($userplan);

// Get the course module id from a post or get request.
/*$id = required_param('id', PARAM_INT);
// Get the course module.
$cm = get_coursemodule_from_id('pyramid', $id, 0, false, MUST_EXIST);*/

if ($pyramid->check_enrollment($USER->id, $pyramid->id)) {
    $test = groups_get_activity_allowed_groups($cm);
    $gruppen = array();
    foreach ($test as $x) {
        if (groups_is_member($x->id, $USER->id)) {
            $gruppen[$x->id] = $x->idnumber;
        }
    }
} else {
    $test = groups_get_activity_allowed_groups($cm);
    $gruppen = array();
    foreach ($test as $x) {
        $gruppen[$x->id] = $x->idnumber;
    }
}

switch ($pyramid->phase) {

    case pyramid::PHASE_1:
        $table = new html_table();
        $table->head = array('Name', 'Status', 'zuletzt aktualisiert', 'Link zur Gruppe');
        foreach ($gruppen as $key => $value) {
            if (!($value == $pyramid->id . '_A') && !($value == $pyramid->id . '_B') && !($value == $pyramid->id . '_C') && !($value == $pyramid->id . '_D') && !($value == $pyramid->id . '_E') && !($value == $pyramid->id . '_F') && !($value == $pyramid->id . '_G')) {
                if($pyramid->check_submission($key,$pyramid->id)){
                    $checksubmission = "abgegeben";
                }else{
                    $checksubmission = "nicht abgegeben";
                }
                $timemodified = $pyramid->check_submission_time($key, $pyramid->id);
                $table->data[] = array(groups_get_group_name($key), $checksubmission, $timemodified, $renderer->show_grouplink("Gruppe anzeigen", $id, $key));
            }
        }
        echo html_writer::table($table);
        break;


    case pyramid::PHASE_2:
        $table = new html_table();
        $table->head = array('Name', 'Status', 'zuletzt aktualisiert', 'Link zur Gruppe');
        foreach ($gruppen as $key => $value) {
            if (!($value == $pyramid->id . '_E') && !($value == $pyramid->id . '_F') && !($value == $pyramid->id . '_G')) {
                if($pyramid->check_submission($key,$pyramid->id)){
                    $checksubmission = "abgegeben";
                }else{
                    $checksubmission = "nicht abgegeben";
                }
                $timemodified = $pyramid->check_submission_time($key, $pyramid->id);
                $table->data[] = array(groups_get_group_name($key), $checksubmission, $timemodified, $renderer->show_grouplink("Gruppe anzeigen", $id, $key));
            }
        }
        echo html_writer::table($table);
        break;

    case pyramid::PHASE_3:
        $table = new html_table();
        $table->head = array('Name', 'Status', 'zuletzt aktualisiert', 'Link zur Gruppe');
        foreach ($gruppen as $key => $value) {
            if (! ($value == $pyramid->id . '_G')) {
                if($pyramid->check_submission($key,$pyramid->id)){
                    $checksubmission = "abgegeben";
                }else{
                    $checksubmission = "nicht abgegeben";
                }
                $timemodified = $pyramid->check_submission_time($key, $pyramid->id);
                $table->data[] = array(groups_get_group_name($key), $checksubmission, $timemodified, $renderer->show_grouplink("Gruppe anzeigen", $id, $key));
            }
        }
        echo html_writer::table($table);
        break;
    case pyramid::PHASE_4:
        $table = new html_table();
        $table->head = array('Name', 'Status', 'zuletzt aktualisiert', 'Link zur Gruppe');
        foreach ($gruppen as $key => $value) {
            if($pyramid->check_submission($key,$pyramid->id)){
                $checksubmission = "abgegeben";
            }else{
                $checksubmission = "nicht abgegeben";
            }
            $timemodified = $pyramid->check_submission_time($key, $pyramid->id);
            $table->data[] = array(groups_get_group_name($key), $checksubmission, $timemodified, $renderer->show_grouplink("Gruppe anzeigen", $id, $key));
        }
        echo html_writer::table($table);
        break;
    default:
}

// Finish the page
echo $renderer->footer();
