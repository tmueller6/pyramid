<?php /** @noinspection LossyEncoding */

use availability_grouping\condition;

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
 * Library of interface functions and constants for module pyramid
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the pyramid specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('MOD_PYRAMID_FRANKY', 'mod_pyramid');
define('MOD_PYRAMID_LANG', 'mod_pyramid');
define('MOD_PYRAMID_TABLE', 'pyramid');
define('MOD_PYRAMID_USERTABLE', 'pyramid_attempt');
define('MOD_PYRAMID_USERS', 'pyramid_users');
define('MOD_PYRAMID_SUBMISSION', 'pyramid_submission');
define('MOD_PYRAMID_MODNAME', 'pyramid');
define('MOD_PYRAMID_URL', '/mod/pyramid');
define('MOD_PYRAMID_CLASS', 'mod_pyramid');

define('MOD_PYRAMID_GRADEHIGHEST', 0);
define('MOD_PYRAMID_GRADELOWEST', 1);
define('MOD_PYRAMID_GRADELATEST', 2);
define('MOD_PYRAMID_GRADEAVERAGE', 3);
define('MOD_PYRAMID_GRADENONE', 4);

// //////////////////////////////////////////////////////////////////////////////
// Moodle core API //
// //////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature
 *            FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function pyramid_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        // case FEATURE_GRADE_HAS_GRADE: return true;
        // case FEATURE_GRADE_OUTCOMES: return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        default:
            return null;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the pyramid.
 *
 * @param $mform form
 *            passed by reference
 */
function pyramid_reset_course_form_definition(&$mform)
{
    $mform->addElement('header', MOD_PYRAMID_MODNAME . 'header', get_string('modulenameplural', MOD_PYRAMID_LANG));
    $mform->addElement('advcheckbox', 'reset_' . MOD_PYRAMID_MODNAME, get_string('deletealluserdata', MOD_PYRAMID_LANG));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function pyramid_reset_course_form_defaults($course)
{
    return array(
        'reset_' . MOD_PYRAMID_MODNAME => 1
    );
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param
 *            string optional type
 */
function pyramid_reset_gradebook($courseid, $type = '')
{
    global $CFG, $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {" . MOD_PYRAMID_TABLE . "} l, {course_modules} cm, {modules} m
             WHERE m.name='" . MOD_PYRAMID_MODNAME . "' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";
    $params = array(
        "course" => $courseid
    );
    if ($moduleinstances = $DB->get_records_sql($sql, $params)) {
        foreach ($moduleinstances as $moduleinstance) {
            pyramid_grade_item_update($moduleinstance, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * pyramid attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data
 *            the data submitted from the reset course.
 * @return array status array
 */
function pyramid_reset_userdata($data)
{
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', MOD_PYRAMID_LANG);
    $status = array();

    if (! empty($data->{'reset_' . MOD_PYRAMID_MODNAME})) {
        $sql = "SELECT l.id
                         FROM {" . MOD_PYRAMID_TABLE . "} l
                        WHERE l.course=:course";

        $params = array(
            "course" => $data->courseid
        );
        $DB->delete_records_select(MOD_PYRAMID_USERTABLE, MOD_PYRAMID_MODNAME . "id IN ($sql)", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            pyramid_reset_gradebook($data->courseid);
        }

        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('deletealluserdata', MOD_PYRAMID_LANG),
            'error' => false
        );
    }

    // / updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates(MOD_PYRAMID_MODNAME, array(
            'available',
            'deadline'
        ), $data->timeshift, $data->courseid);
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('datechanged'),
            'error' => false
        );
    }

    return $status;
}

/**
 * Create grade item for activity instance
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $moduleinstance
 *            object with extra cmidnumber
 * @param array|object $grades
 *            optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function pyramid_grade_item_update($moduleinstance, $grades = null)
{
    global $CFG;
    if (! function_exists('grade_update')) { // workaround for buggy PHP versions
        require_once ($CFG->libdir . '/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $moduleinstance)) { // it may not be always present
        $params = array(
            'itemname' => $moduleinstance->name,
            'idnumber' => $moduleinstance->cmidnumber
        );
    } else {
        $params = array(
            'itemname' => $moduleinstance->name
        );
    }

    if ($moduleinstance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $moduleinstance->grade;
        $params['grademin'] = 0;
    } else if ($moduleinstance->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = - $moduleinstance->grade;

        // Make sure current grade fetched correctly from $grades
        $currentgrade = null;
        if (! empty($grades)) {
            if (is_array($grades)) {
                $currentgrade = reset($grades);
            } else {
                $currentgrade = $grades;
            }
        }

        // When converting a score to a scale, use scale's grade maximum to calculate it.
        if (! empty($currentgrade) && $currentgrade->rawgrade !== null) {
            $grade = grade_get_grades($moduleinstance->course, 'mod', MOD_PYRAMID_MODNAME, $moduleinstance->id, $currentgrade->userid);
            $params['grademax'] = reset($grade->items)->grademax;
        }
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (! empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms)
        if (is_object($grades)) {
            $grades = array(
                $grades->userid => $grades
            );
        } else if (array_key_exists('userid', $grades)) {
            $grades = array(
                $grades['userid'] => $grades
            );
        }
        foreach ($grades as $key => $grade) {
            if (! is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            // check raw grade isnt null otherwise we insert a grade of 0
            if ($grade['rawgrade'] !== null) {
                $grades[$key]['rawgrade'] = ($grade['rawgrade'] * $params['grademax'] / 100);
            } else {
                // setting rawgrade to null just in case user is deleting a grade
                $grades[$key]['rawgrade'] = null;
            }
        }
    }

    return grade_update('mod/' . MOD_PYRAMID_MODNAME, $moduleinstance->course, 'mod', MOD_PYRAMID_MODNAME, $moduleinstance->id, 0, $grades, $params);
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $moduleinstance
 * @param int $userid
 *            specific user only, 0 means all
 * @param bool $nullifnone
 */
function pyramid_update_grades($moduleinstance, $userid = 0, $nullifnone = true)
{
    global $CFG, $DB;
    require_once ($CFG->libdir . '/gradelib.php');

    if ($moduleinstance->grade == 0) {
        pyramid_grade_item_update($moduleinstance);
    } else if ($grades = pyramid_get_user_grades($moduleinstance, $userid)) {
        pyramid_grade_item_update($moduleinstance, $grades);
    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        pyramid_grade_item_update($moduleinstance, $grade);
    } else {
        pyramid_grade_item_update($moduleinstance);
    }

    // echo "updategrades" . $userid;
}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $moduleinstance
 * @param int $userid
 *            optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function pyramid_get_user_grades($moduleinstance, $userid = 0)
{
    global $CFG, $DB;

    $params = array(
        "moduleid" => $moduleinstance->id
    );

    if (! empty($userid)) {
        $params["userid"] = $userid;
        $user = "AND u.id = :userid";
    } else {
        $user = "";
    }

    $idfield = 'a.' . MOD_PYRAMID_MODNAME . 'id';
    if ($moduleinstance->maxattempts == 1 || $moduleinstance->gradeoptions == MOD_PYRAMID__GRADELATEST) {

        $sql = "SELECT u.id, u.id AS userid, a.sessionscore AS rawgrade
                  FROM {user} u,  {" . MOD_PYRAMID_USERTABLE . "} a
                 WHERE u.id = a.userid AND $idfield = :moduleid
                       AND a.status = 1
                       $user";
    } else {
        switch ($moduleinstance->gradeoptions) {
            case MOD_PYRAMID_GRADEHIGHEST:
                $sql = "SELECT u.id, u.id AS userid, MAX( a.sessionscore  ) AS rawgrade
                      FROM {user} u, {" . MOD_PYRAMID_USERTABLE . "} a
                     WHERE u.id = a.userid AND $idfield = :moduleid
                           $user
                  GROUP BY u.id";
                break;
            case MOD_PYRAMID_GRADELOWEST:
                $sql = "SELECT u.id, u.id AS userid, MIN(  a.sessionscore  ) AS rawgrade
                      FROM {user} u, {" . MOD_PYRAMID_USERTABLE . "} a
                     WHERE u.id = a.userid AND $idfield = :moduleid
                           $user
                  GROUP BY u.id";
                break;
            case MOD_PYRAMID_GRADEAVERAGE:
                $sql = "SELECT u.id, u.id AS userid, AVG( a.sessionscore  ) AS rawgrade
                      FROM {user} u, {" . MOD_PYRAMID_USERTABLE . "} a
                     WHERE u.id = a.userid AND $idfield = :moduleid
                           $user
                  GROUP BY u.id";
                break;
        }
    }

    return $DB->get_records_sql($sql, $params);
}

function pyramid_get_completion_state($course, $cm, $userid, $type)
{
    return pyramid_is_complete($course, $cm, $userid, $type);
}

// this is called internally only
function pyramid_is_complete($course, $cm, $userid, $type)
{
    global $CFG, $DB;

    // Get module object
    if (! ($moduleinstance = $DB->get_record(MOD_PYRAMID_TABLE, array(
        'id' => $cm->instance
    )))) {
        throw new Exception("Can't find module with cmid: {$cm->instance}");
    }
    $idfield = 'a.' . MOD_PYRAMID_MODNAME . 'id';
    $params = array(
        'moduleid' => $moduleinstance->id,
        'userid' => $userid
    );
    $sql = "SELECT  MAX( sessionscore  ) AS grade
                      FROM {" . MOD_PYRAMID_USERTABLE . "}
                     WHERE userid = :userid AND " . MOD_PYRAMID_MODNAME . "id = :moduleid";
    $result = $DB->get_field_sql($sql, $params);
    if ($result === false) {
        return false;
    }

    // check completion reqs against satisfied conditions
    switch ($type) {
        case COMPLETION_AND:
            $success = $result >= $moduleinstance->mingrade;
            break;
        case COMPLETION_OR:
            $success = $result >= $moduleinstance->mingrade;
    }
    // return our success flag
    return $success;
}

/**
 * A task called from scheduled or adhoc
 *
 * @param
 *            progress_trace trace object
 *            
 */
function pyramid_dotask(progress_trace $trace)
{
    $trace->output('executing dotask');
}

/**
 * Saves a new instance of the pyramid into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $pyramid
 *            An object from the form in mod_form.php
 * @param mod_pyramid_mod_form $mform
 * @return int The id of the newly inserted pyramid record
 */
function pyramid_add_instance(stdClass $pyramid, mod_pyramid_mod_form $mform = null)
{
    global $DB, $CFG, $USER;
    require_once ($CFG->dirroot . '/group/lib.php');

    $groupingdata = new StdClass();
    $groupingdata->courseid = $pyramid->course;
    $groupingdata->name = $pyramid->name;
    $groupingdata->description = "Gruppierung fuer " . $pyramid->name;
    $groupingdata->descriptionformat = FORMAT_HTML;
    $grouping = groups_create_grouping($groupingdata);

    $pyramid->timecreated = time();
    $pyramid->id = $pyramid->instance;
    $pyramid->autoswitch = $pyramid->modus;
    $pyramid->groupingid = $grouping;

    // instance ID
    $id = $DB->insert_record(MOD_PYRAMID_TABLE, $pyramid);

    // Teilnehmer in Tabelle pyramid_user
    foreach ($pyramid as $key => $value) {
        $exp_key = explode('-', $key);
        if ($exp_key[0] == 'student') {
            $result = $exp_key[1];
            if ($value == 1) {
                if (! $DB->record_exists(MOD_PYRAMID_USERS, array(
                    'userid' => $result,
                    'pyramid_id' => (int)$id
                ))) {
                    $DB->insert_record(MOD_PYRAMID_USERS, array(
                        'name' => $pyramid->name,
                        'userid' => (int)$result,
                        'pyramid_id' => (int)$id
                    ));
                }
            }
        }
    }

    // Teilnehmer der Aktivität
    $users = $DB->get_fieldset_select(MOD_PYRAMID_USERS, 'userid', 'pyramid_id = ' . $id . '');

    $data = new StdClass();
    $data->courseid = $pyramid->course;
    $data->descriptionformat = FORMAT_HTML;

    $letters = [
        1 => "A",
        2 => "B",
        3 => "C",
        4 => "D",
        5 => "E",
        6 => "F",
        7 => "G"
    ];

    for ($i = 0; $i < count($users); $i ++) {

        $firstname = $DB->get_field('user', 'firstname', array(
            "id" => $users[$i]
        ));
        $lastname = $DB->get_field('user', 'lastname', array(
            "id" => $users[$i]
        ));

        $data->name = "Einzelphase-" . $pyramid->name . "-" . $firstname . " " . $lastname;
        $data->description = 'Gruppe fuer Phase 1';
        $data->idnumber = $id . '_' . $users[$i];
        $groupid = groups_create_group($data);
        groups_add_member($groupid, $users[$i]);

        groups_assign_grouping($pyramid->groupingid, $groupid);
    }

    for ($i = 1; $i <= 4; $i ++) {

        $data->name = 'Gruppenphase 1-' . $pyramid->name . '-Gruppe ' . $letters[$i];
        $data->description = 'Gruppe fuer Phase 2';
        $data->idnumber = $id . '_' . $letters[$i];
        $data->enablemessaging = true;

        $groupid = groups_create_group($data);

        groups_assign_grouping($pyramid->groupingid, $groupid);
    }

    for ($i = 5; $i <= 6; $i ++) {

        $data->name = 'Gruppenphase 2-' . $pyramid->name . '-Gruppe ' . $letters[$i];
        $data->description = 'Gruppe fuer Phase 3';
        $data->idnumber = $id . '_' . $letters[$i];
        $data->enablemessaging = true;

        $groupid = groups_create_group($data);

        groups_assign_grouping($pyramid->groupingid, $groupid);
    }

    $data->name = 'Abschlussphase-' . $pyramid->name . '-Gruppe ' . $letters[7];
    $data->description = 'Gruppe fuer Phase 4';
    $data->idnumber = $id . '_' . $letters[$i];
    $data->enablemessaging = true;

    $groupid = groups_create_group($data);
    for ($i = 0; $i < count($users); $i ++) {
        groups_add_member($groupid, $users[$i]);
    }
    groups_assign_grouping($pyramid->groupingid, $groupid);

    $id1 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_A'
    ));
    $id2 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_B'
    ));
    $id3 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_C'
    ));
    $id4 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_D'
    ));
    $id5 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_E'
    ));
    $id6 = $DB->get_field('groups', 'id', array(
        'courseid' => (int)$pyramid->course,
        'idnumber' => $id . '_F'
    ));

    shuffle($users);
    $nummer = count($users);
    $rest = $nummer % 4;
    $nummer = $nummer / 4;
    $nummer = floor($nummer);

    for ($i = 0; $i < $nummer; $i ++) {
        $user = array_shift($users);
        groups_add_member($id1, $user);
    }

    for ($i = 0; $i < $nummer; $i ++) {
        $user = array_shift($users);
        groups_add_member($id2, $user);
    }

    for ($i = 0; $i < $nummer; $i ++) {
        $user = array_shift($users);
        groups_add_member($id3, $user);
    }

    for ($i = 0; $i < $nummer; $i ++) {
        $user = array_shift($users);
        groups_add_member($id4, $user);
    }

    switch ($rest) {
        case 1:
            $user = array_shift($users);
            groups_add_member($id1, $user);
            break;
        case 2:
            $user = array_shift($users);
            groups_add_member($id1, $user);

            $user = array_shift($users);
            groups_add_member($id2, $user);
            break;
        case 3:
            $user = array_shift($users);
            groups_add_member($id1, $user);

            $user = array_shift($users);
            groups_add_member($id2, $user);

            $user = array_shift($users);
            groups_add_member($id3, $user);
            break;
        default:
            break;
    }
    $groupa = groups_get_members($id1, $fields = 'u.*');

    foreach ($groupa as $x) {
        groups_add_member($id5, $x->id);
    }

    $groupc = groups_get_members($id3, $fields = 'u.*');

    foreach ($groupc as $x) {
        groups_add_member($id5, $x->id);
    }

    $groupb = groups_get_members($id2, $fields = 'u.*');

    foreach ($groupb as $x) {
        groups_add_member($id6, $x->id);
    }

    $groupd = groups_get_members($id4, $fields = 'u.*');

    foreach ($groupd as $x) {
        groups_add_member($id6, $x->id);
    }

    $DB->set_field('course_modules', 'groupingid', $grouping, array(
        'id' => (int)$pyramid->coursemodule,
        "course" => (int)$pyramid->course,
        "instance" => (int)$pyramid->id
    ));

    $restriction = \core_availability\tree::get_root_json([
        \availability_grouping\condition::get_json($grouping)
    ]);

    $DB->set_field('course_modules', 'availability', json_encode($restriction), array(
        'id' => (int)$pyramid->coursemodule,
        "course" => (int)$pyramid->course,
        "instance" => (int)$pyramid->id
    ));

    return $id;
}

/**
 * Updates an instance of the pyramid in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $pyramid
 *            An object from the form in mod_form.php
 * @param mod_pyramid_mod_form $mform
 * @return boolean Success/Fail
 */
function pyramid_update_instance(stdClass $pyramid, mod_pyramid_mod_form $mform = null)
{
    global $DB, $CFG;
    require_once ($CFG->dirroot . '/group/lib.php');

    $modinfo = get_fast_modinfo($pyramid->course);
    $cm = $modinfo->get_cm($pyramid->coursemodule);

    $pyramid->timemodified = time();


    $pyramid->id = $pyramid->instance;
    $pyramid->autoswitch = $pyramid->modus;

    foreach ($pyramid as $key => $value) {
        $exp_key = explode('-', $key);
        if ($exp_key[0] == 'student') {
            $result = $exp_key[1];
            if ($value == 0) {
                if ($DB->record_exists(MOD_PYRAMID_USERS, array(
                    'userid' => (int)$result,
                    'pyramid_id' => (int)$pyramid->id
                ))) {
                    $DB->delete_records(MOD_PYRAMID_USERS, array(
                        'userid' => (int)$result,
                        'pyramid_id' => (int)$pyramid->id
                    ));
                }
            } elseif (!$DB->record_exists(MOD_PYRAMID_USERS, array(
                'userid' => (int)$result,
                'pyramid_id' => (int)$pyramid->id
            ))) {
                $DB->insert_record(MOD_PYRAMID_USERS, array(
                    'name' => $pyramid->name,
                    'userid' => (int)$result,
                    'pyramid_id' => (int)$pyramid->id
                ));
            }
        }
    }
    $users = $DB->get_fieldset_select(MOD_PYRAMID_USERS, 'userid', 'pyramid_id = ' . $pyramid->id . '');


    $data = new StdClass();
    $data->courseid = $pyramid->course;
    $data->descriptionformat = FORMAT_HTML;

    $groupingusers = groups_get_grouping_members($pyramid->groupingid, $fields = 'u.*');
    $groupingu = array();

    foreach ($groupingusers as $x) {
        $groupingu[] = $x->id;
    }


    // User Differenz
    $resultremove = array_values(array_diff($groupingu, $users));


    if (!empty($resultremove)) {

        for ($i = 0; $i < count($resultremove); $i++) {

            $groupinfo = groups_get_activity_allowed_groups($cm, $resultremove[$i]);

            foreach ($groupinfo as $x) {
                if(groups_is_member($x->id,$resultremove[$i])) {
                    groups_remove_member($x->id, $resultremove[$i]);
                }
            }
        }
    }


    $resultadd = array_values(array_diff($users, $groupingu));


    if (!empty($resultadd)){

        for ($i = 0; $i < count($resultadd); $i++) {

            //Einzelphase Gruppe
            if($DB->record_exists('groups', array("courseid" => (int)$pyramid->course,"idnumber"=>$pyramid->id."_".$resultadd[$i]))){
                $usergroup = $DB->get_field('groups', 'id', array("courseid" => (int)$pyramid->course, "idnumber"=>$pyramid->id."_".$resultadd[$i]));
                groups_add_member($usergroup,$resultadd[$i]);
            }
            else {
                $firstname = $DB->get_field('user', 'firstname', array(
                    "id" => $resultadd[$i]
                ));
                $lastname = $DB->get_field('user', 'lastname', array(
                    "id" => $resultadd[$i]
                ));

                $data->name = "Einzelphase-" . $pyramid->name . "-" . $firstname . " " . $lastname;
                $data->description = 'Gruppe fuer Phase 1';
                $data->idnumber = $pyramid->id . '_' . $resultadd[$i];

                $groupid = groups_create_group($data);
                groups_add_member($groupid, $resultadd[$i]);

                groups_assign_grouping($pyramid->groupingid, $groupid);
            }

            $groups = $DB->get_fieldset_select('groups', 'id', "idnumber LIKE '".$pyramid->id."_%'");
            $gruppenphase1 = array();
            $gruppenphase2 = array();
            foreach($groups as $x){
                $tempgroup = groups_get_group($x);
                if($tempgroup->idnumber == $pyramid->id.'_A' || $tempgroup->idnumber == $pyramid->id.'_B' || $tempgroup->idnumber == $pyramid->id.'_C' || $tempgroup->idnumber == $pyramid->id.'_D'){
                    array_push($gruppenphase1, $x);
                }
                if($tempgroup->idnumber == $pyramid->id.'_E' || $tempgroup->idnumber == $pyramid->id.'_F'){
                    array_push($gruppenphase2, $x);
                }
                if($tempgroup->idnumber == $pyramid->id.'_G'){
                    $abschlussphase = $x;
                }
            }
            foreach($gruppenphase2 as $x){
                $tempgroup = groups_get_group($x);
                if($tempgroup->idnumber == $pyramid->id.'_E'){
                    $idE = $x;
                }
                if($tempgroup->idnumber == $pyramid->id.'_F'){
                    $idF = $x;
                }
            }
            $count = array();
            foreach($gruppenphase1 as $x){
                $count[$x] = count(groups_get_members($x, 'u.*'));
            }
            $count = array_keys($count, min($count));

            //Gruppenphase 1: Teilnehmer wird zu der Gruppe mit niedrigster Mitgliederzahl hinzugefügt
            groups_add_member($count[0], $resultadd[$i]);

            $temp = groups_get_group($count[0]);

            //Gruppenphase 2: Teilnehmer wird zur entsprechenden Gruppe in Gruppenphase 2 hinzugefügt
            switch($temp->idnumber){
                case $pyramid->id."_A":
                    groups_add_member($idE, $resultadd[$i]);
                    break;
                case $pyramid->id."_B":
                    groups_add_member($idF, $resultadd[$i]);
                    break;
                case $pyramid->id."_C":
                    groups_add_member($idE, $resultadd[$i]);
                    break;
                case $pyramid->id."_D":
                    groups_add_member($idF, $resultadd[$i]);
                    break;
            }
            //Teilnehmer wird zur Gruppe für die Abschlussphase hinzugefügt.
            groups_add_member($abschlussphase, $resultadd[$i]);
        }
    }

    return $DB->update_record(MOD_PYRAMID_TABLE, $pyramid);
}

/**
 * Removes an instance of the pyramid from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 *            Id of the module instance
 * @return boolean Success/Failure
 */
function pyramid_delete_instance($id)
{
    global $DB;

    if (! $pyramid = $DB->get_record(MOD_PYRAMID_TABLE, array(
        'id' => $id
    ))) {
        return false;
    }

    // Delete any dependent records here #

    /*
     * $DB->delete_records(MOD_PYRAMID_USERS, array(
     * 'id' => $pyramid->id
     * ));
     */
    $DB->delete_records(MOD_PYRAMID_TABLE, array(
        'id' => $pyramid->id
    ));

    $DB->delete_records_select('groups', "courseid = " . $pyramid->course . " AND idnumber LIKE '" . $pyramid->id . "_%%'");
    // $DB->delete_records('groups', array("courseid" =>$pyramid->course, "idnumber" => $pyramid->id."_%%"));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function pyramid_user_outline($course, $user, $mod, $pyramid)
{
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course
 *            the current course record
 * @param stdClass $user
 *            the record of the user we are generating report for
 * @param cm_info $mod
 *            course module info
 * @param stdClass $pyramid
 *            the module instance record
 * @return void, is supposed to echp directly
 */
function pyramid_user_complete($course, $user, $mod, $pyramid)
{}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in pyramid activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function pyramid_print_recent_activity($course, $viewfullnames, $timestart)
{
    return false; // True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link pyramid_print_recent_mod_activity()}.
 *
 * @param array $activities
 *            sequentially indexed array of objects with the 'cmid' property
 * @param int $index
 *            the index in the $activities to use for the next record
 * @param int $timestart
 *            append activity since this time
 * @param int $courseid
 *            the id of the course we produce the report for
 * @param int $cmid
 *            course module id
 * @param int $userid
 *            check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid
 *            check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function pyramid_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0)
{}

/**
 * Prints single activity item prepared by {@see pyramid_get_recent_mod_activity()}
 *
 * @return void
 */
function pyramid_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames)
{}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function pyramid_cron()
{

    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function pyramid_get_extra_capabilities()
{
    return array();
}

// //////////////////////////////////////////////////////////////////////////////
// Gradebook API //
// //////////////////////////////////////////////////////////////////////////////

/**
 * Is a given scale used by the instance of pyramid?
 *
 * This function returns if a scale is being used by one pyramid
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $pyramidid
 *            ID of an instance of this module
 * @return bool true if the scale is used by the given pyramid instance
 */
function pyramid_scale_used($pyramidid, $scaleid)
{
    global $DB;

    /**
     *
     * @example
     */
    if ($scaleid and $DB->record_exists(MOD_PYRAMID_TABLE, array(
        'id' => $pyramidid,
        'grade' => - $scaleid
    ))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of pyramid.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param $scaleid int
 * @return boolean true if the scale is used by any pyramid instance
 */
function pyramid_scale_used_anywhere($scaleid)
{
    global $DB;

    /**
     *
     * @example
     */
    if ($scaleid and $DB->record_exists(MOD_PYRAMID_TABLE, array(
        'grade' => - $scaleid
    ))) {
        return true;
    } else {
        return false;
    }
}

// //////////////////////////////////////////////////////////////////////////////
// File API //
// //////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function pyramid_get_file_areas($course, $cm, $context)
{
    return array();
}

function create_filearea()
{
    $mform = $this->_form;
    $mform->addElement('filemanager', 'attachments', get_string('attachment', 'moodle'), null, array(
        'subdirs' => 0,
        'maxbytes' => $maxbytes,
        'areamaxbytes' => 10485760,
        'maxfiles' => 50,
        'accepted_types' => array(
            'document'
        ),
        'return_types' => FILE_INTERNAL | FILE_EXTERNAL
    ));
}

/**
 * File browsing support for pyramid file areas
 *
 * @package mod_pyramid
 * @category files
 *          
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function pyramid_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename)
{
    return null;
}

/**
 * Serves the files from the pyramid file areas
 *
 * @package mod_pyramid
 * @category files
 *          
 * @param stdClass $course
 *            the course object
 * @param stdClass $cm
 *            the course module object
 * @param stdClass $context
 *            the pyramid's context
 * @param string $filearea
 *            the name of the file area
 * @param array $args
 *            extra arguments (itemid, path)
 * @param bool $forcedownload
 *            whether or not force download
 * @param array $options
 *            additional options affecting the file serving
 */
function pyramid_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array())
{
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

// //////////////////////////////////////////////////////////////////////////////
// Navigation API //
// //////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding pyramid nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref
 *            An object representing the navigation tree node of the pyramid module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function pyramid_extend_navigation(navigation_node $pyramidnode, stdclass $course, stdclass $module, cm_info $cm)
{}

/**
 * Extends the settings navigation with the pyramid settings
 *
 * This function is called when the context for the page is a pyramid module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav
 *            {@link settings_navigation}
 * @param navigation_node $pyramidnode
 *            {@link navigation_node}
 */
function pyramid_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $pyramidnode = null)
{}
