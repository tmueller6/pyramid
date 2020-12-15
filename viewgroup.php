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
require_once ("../../config.php");
require_once ('./viewgroup_form.php');
require_once ('./viewgroup_teacher_form.php');
require_once (__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT); // pyramid instance ID - it should be named as the first character of the module
$cmid = optional_param('cmid', 0, PARAM_INT);
$groupid = required_param('groupid',PARAM_INT);
//throw new exception(var_dump($groupid));
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
} elseif($cmid){
    if (! $cm = get_coursemodule_from_id('pyramid', $cmid)) {
        print_error("Course Module ID was incorrect");
    }

    if (! $course = $DB->get_record("course", array(
        "id" => $cm->course
    ))) {
        print_error("Course is misconfigured");
    }
    $context = context_module::instance($cm->id);
    if (! $moduleinstance = $DB->get_record("pyramid", array(
        "id" => $cm->instance
    ))) {
        print_error("Course module is incorrect");
    }
}else {
    print_error(get_string('missingidandcmid', MOD_PYRAMID_LANG));
}
/*$cmid = required_param('cmid', PARAM_INT); // Course Module ID.

if (! $cm = get_coursemodule_from_id('pyramid', $cmid)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array(
    "id" => $cm->course
))) {
    print_error("Course is misconfigured");
}
if (! $moduleinstance = $DB->get_record("pyramid", array(
    "id" => $cm->instance
))) {
    print_error("Course module is incorrect");
}
$modulecontext = context_module::instance($cm->id);
*/
$PAGE->set_url('/mod/pyramid/viewgroup.php', array(
    'id' => $cm->id
));
$modulecontext = context_module::instance($cm->id);
$pyramid = new pyramid($moduleinstance, $cm, $course);

require_login($course, true, $cm);

// Diverge logging logic at Moodle 2.7
if ($CFG->version < 2014051200) {
    add_to_log($course->id, 'pyramid', 'view', "viewgroup.php?id={$cm->id}", $moduleinstance->name, $cm->id);
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

// if we got this far, we can consider the activity "viewed"
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// are we a teacher or a student?
$mode = "view";

// / Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');

// Get an admin settings
$config = get_config(MOD_PYRAMID_FRANKY);
$someadminsetting = $config->someadminsetting;

// Get an instance setting
$someinstancesetting = $moduleinstance->someinstancesetting;

// get our javascript all ready to go
// We can omit $jsmodule, but its nice to have it here,
// if for example we need to include some funky YUI stuff
$jsmodule = array(
    'name' => 'mod_pyramid',
    'fullpath' => '/mod/pyramid/module.js',
    'requires' => array()
);
// here we set up any info we need to pass into javascript
$opts = Array();
$opts['someinstancesetting'] = $someinstancesetting;

// this inits the M.mod_pyramid thingy, after the page has loaded.
$PAGE->requires->js_init_call('M.mod_pyramid.helper.init', array(
    $opts
), false, $jsmodule);

// this loads any external JS libraries we need to call
// $PAGE->requires->js("/mod/pyramid/js/somejs.js");
// $PAGE->requires->js(new moodle_url('http://www.somewhere.com/some.js'),true);

// This puts all our display logic into the renderer.php file in this plugin
// theme developers can override classes there, so it makes it customizable for others
// to do it this way.
$renderer = $PAGE->get_renderer('mod_pyramid');

// From here we actually display the page.
// this is core renderer stuff

// if we are teacher we see tabs. If student we just see the quiz
/*if (has_capability('mod/pyramid:preview', $modulecontext)) {
    echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('view', MOD_PYRAMID_LANG));
} else {
    echo $renderer->notabsheader();
}*/
echo $renderer->notabsheader();

echo $renderer->show_intro($moduleinstance, $cm);

// if we have too many attempts, lets report that.
if ($moduleinstance->maxattempts > 0) {
    $attempts = $DB->get_records(MOD_PYRAMID_USERTABLE, array(
        'userid' => $USER->id,
        MOD_PYRAMID_MODNAME . 'id' => $moduleinstance->id
    ));
    if ($attempts && count($attempts) < $moduleinstance->maxattempts) {
        echo get_string("exceededattempts", MOD_PYRAMID_LANG, $moduleinstance->maxattempts);
    }
}

if(isset($_POST['groupid'])) {
    //$groupid = $_POST['groupid'];

    $data = new stdClass();
    $data->groupid = $groupid;
    $text1 = format_text($DB->get_field('pyramid_submission', 'submission', array(
        "pyramid_id" => $pyramid->id,
        "group_id" => $groupid,
        "current_version" => 1
    )), $format = 4);
    $data->text1 = format_string($text1);
    if (has_capability('mod/pyramid:itemedit', $modulecontext) && !$pyramid->check_enrollment($USER->id, $pyramid->id)) {
        $entry = $DB->get_record("pyramid_submission", array(
            "pyramid_id" => $pyramid->id,
            "group_id" => $groupid,
            "current_version" => 1
        ));
        if ($entry) {
            $data->entryid = $entry->id;
            $data->text = $entry->submission;
            $data->textformat = $entry->format;
            $opts = array();
            $opts['groupid'] = $entry->group_id;

        } else {
            $data->entryid = null;
            $data->text = '';
            $data->textformat = FORMAT_HTML;
        }

        $data->cmid = $cm->id;
        $editoroptions = array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $modulecontext,
            'subdirs' => false,
            'enable_filemanagement' => false,
            'autosave' => false
        );

        $data = file_prepare_standard_editor($data, 'text', $editoroptions, $modulecontext, 'mod_pyramid', 'entry', $data->entryid);

        $form = new mod_pyramid_view_teacher_form();
        $form->set_data($data);
        if ($form->is_cancelled()) {
            redirect($CFG->wwwroot . '/mod/pyramid/view.php?id=' . $cm->id);
        } else if ($fromform = $form->get_data()) {
            // If data submitted, then process and store.

            // Prevent CSFR.
            confirm_sesskey();
            $timenow = time();

            // This will be overwriten after being we have the entryid.

            $oldentry = new StdClass();
            $newentry = new stdClass();
            $newentry->submission = $fromform->text_editor['text'];
            $newentry->format = $fromform->text_editor['format'];
            $newentry->timemodified = $timenow;

            if ($entry) {
                $oldentry->submission = $entry->submission;
                $oldentry->id = $entry->id;
                $oldentry->current_version = 0;
                if (!$DB->update_record("pyramid_submission", $oldentry)) {
                    print_error("Could not update your submission");
                } else {
                    $newentry->pyramid_id = $pyramid->id;
                    $newentry->group_id = $groupid;
                    $newentry->current_version = 1;
                    $newentry->timecreated = $timenow;
                    $newentry->timemodified = $timenow;
                    if (!$newentry->id = $DB->insert_record("pyramid_submission", $newentry)) {
                        print_error("Could not insert a new submission");
                    }
                }
            } else {

                $newentry->group_id = $groupid;
                $newentry->pyramid_id = $pyramid->id;
                $newentry->current_version = 1;
                $newentry->timecreated = $timenow;
                $newentry->timemodified = $timenow;
                if (!$newentry->id = $DB->insert_record("pyramid_submission", $newentry)) {
                    print_error("Could not insert a new submission");
                }
            }

            // Relink using the proper entryid.
            // We need to do this as draft area didn't have an itemid associated when creating the entry.
            $fromform = file_postupdate_standard_editor($fromform, 'text', $editoroptions, $editoroptions['context'], 'mod_pyramid', 'entry', $newentry->id);
            $newentry->text = $fromform->text;
            $newentry->format = $fromform->textformat;

            $DB->update_record('pyramid_submission', $newentry);

            redirect(new moodle_url('/mod/pyramid/view.php?id=' . $cm->id));
            die();
        }
        $form->display();
        echo $renderer->footer();
    } else {
        $form = new mod_pyramid_view_form();
        $form->set_data($data);
        $name = groups_get_group_name($groupid);


        echo $renderer->show_name($name);

        $members = groups_get_members($groupid, $fields = 'u.*', $sort = 'lastname ASC');

        foreach ($members as $x) {
            echo $x->firstname . " " . $x->lastname;
            echo "<br>";
        }
        echo "<br>";

        $form->display();

        echo $OUTPUT->continue_button($pyramid->view_url());
// Finish the page
        echo $renderer->footer();
    }
}
?>