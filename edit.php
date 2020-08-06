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
require_once ("../../config.php");
require_once ('./edit_form.php');
require_once (__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT); // Course Module ID.

if (! $cm = get_coursemodule_from_id('pyramid', $cmid)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array(
    "id" => $cm->course
))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);
require_login($course, false, $cm);

require_capability('mod/pyramid:submit', $context);

if (! $pyramid = $DB->get_record("pyramid", array(
    "id" => $cm->instance
))) {
    print_error("Course module is incorrect");
}

$pyramid = new pyramid($pyramid, $cm, $course);

// Header.
$PAGE->set_url('/mod/pyramid/edit.php', array(
    'cmid' => $cmid
));
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($pyramid->name));
$PAGE->set_heading($course->fullname);

$temp = groups_get_activity_allowed_groups($cm);
$gruppen = array();
foreach ($temp as $x){
    if(groups_is_member($x->id,$USER->id)){
        $gruppen[$x->id] = $x->idnumber;
    }
}


$data = new stdClass();


if (isset($gruppen)) {

    switch ($pyramid->phase) {

        case pyramid::PHASE_1:
            $groupid;
            foreach ($gruppen as $key=>$value){
                if($value == $pyramid->id.'_'.$USER->id){
                    $groupid = $key;
                }
            }
            $entry = $DB->get_record("pyramid_submission", array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$groupid,
                "current_version" => 1
            ));
            break;
        case pyramid::PHASE_2:
            $groupid;
            foreach($gruppen as $key=>$value){
                if($value == $pyramid->id.'_A' || $value == $pyramid->id.'_B' || $value == $pyramid->id.'_C' || $value == $pyramid->id.'_D'){
                    $groupid = $key;
                }
            }
            $entry = $DB->get_record("pyramid_submission", array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$groupid,
                "current_version" => 1
            ));

            $member = groups_get_members($groupid, $fields = 'u.*', $sort = 'lastname ASC');

            $users = array();
            $groups = array();
            foreach ($member as $x) {
                array_push($users, $x->id);
            }

            foreach ($users as $x) {
                $grpid = groups_get_group_by_idnumber($cm->course, $pyramid->id . "_" . $x);
                array_push($groups, $grpid);
            }

            $grpcount = count($groups);

            switch ($grpcount) {
                case 1:
                    $text1 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[0]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $text2 = "";

                    $data->text1 = format_string($text1);
                    $data->text2 = format_string($text2);

                    break;

                case 2:
                    $text1 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[0]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $text2 = format_string($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[1]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $data->text1 = format_string($text1);
                    $data->text2 = format_string($text2);

                    break;
                case 3:
                    $text1 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[0]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $text2 = format_string($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[1]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $text3 = format_string($DB->get_field("pyramid_submission", 'submission', array(
                        "pyramid_id" => (int)$pyramid->id,
                        "group_id" => (int)$groups[2]->id,
                        "current_version" => 1
                    )), $format = FORMAT_MARKDOWN);

                    $data->text1 = format_string($text1);
                    $data->text2 = format_string($text2);
                    $data->text3 = format_string($text3);
            }
            break;
        case pyramid::PHASE_3:
            $groupid;
            foreach($gruppen as $key=>$value){
                if($value == $pyramid->id.'_E' || $value == $pyramid->id.'_F'){
                    $groupid = $key;
                }
            }
            $entry = $DB->get_record("pyramid_submission", array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$groupid,
                "current_version" => 1
            ));

            $member = groups_get_members($groupid, $fields = 'u.*', $sort = 'lastname ASC');
            $users = array();
            $groups = array();
            $right = array();
            foreach ($member as $x) {
                array_push($users, $x->id);
            }

            foreach ($users as $x) {
                $current = groups_get_activity_allowed_groups($cm, $x);
                foreach ($current as $y) {
                    if(groups_is_member($y->id, $x)){
                        $groups[$y->id] = $y->idnumber;
                    }
                }
            }

            foreach ($groups as $key => $value) {
                $exp_value = explode("_", $value);

                if ($exp_value[1] == 'A' || $exp_value[1] == 'B' || $exp_value[1] == 'C' || $exp_value[1] == 'D') {
                    array_push($right, $key);
                }
            }

            $text1 = "";
            $text2 = "";
            $text1 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$right[0],
                "current_version" => 1
            )), $format = FORMAT_MARKDOWN);

            if (count($right) >= 2) {
                $text2 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                    "pyramid_id" => (int)$pyramid->id,
                    "group_id" => (int)$right[1],
                    "current_version" => 1
                )), $format = FORMAT_MARKDOWN);
            }
            $data->text1 = format_string($text1);
            $data->text2 = format_string($text2);

            break;
        case pyramid::PHASE_4:
            $groupid;
            foreach($gruppen as $key=>$value){
                if($value == $pyramid->id.'_G'){
                    $groupid = $key;
                }
            }
            $entry = $DB->get_record("pyramid_submission", array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$groupid,
                "current_version" => 1
            ));

            $gruppe1 = groups_get_group_by_idnumber($cm->course, $pyramid->id . "_E");
            $gruppe2 = groups_get_group_by_idnumber($cm->course, $pyramid->id . "_F");

            $text1 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$gruppe1->id,
                "current_version" => 1
            )), $format = FORMAT_MARKDOWN);

            $text2 = format_text($DB->get_field("pyramid_submission", 'submission', array(
                "pyramid_id" => (int)$pyramid->id,
                "group_id" => (int)$gruppe2->id,
                "current_version" => 1
            )), $format = FORMAT_MARKDOWN);

            $data->text1 = format_string($text1);
            $data->text2 = format_string($text2);
            break;
        default:
            print_error("Nicht Teil einer Gruppe");
    }
    $opts = Array();
    $opts['groupid'] = $groupid;
    $PAGE->requires->jquery();
    $PAGE->requires->js_init_call('M.mod_pyramid.lock', array(
        $opts
    ), false);
    if ($entry) {
        $data->entryid = $entry->id;
        $data->text = $entry->submission;
        $data->textformat = $entry->format;
    } else {
        $data->entryid = null;
        $data->text = '';
        $data->textformat = FORMAT_HTML;
    }

    $data->cmid = $cm->id;

    $editoroptions = array(
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'context' => $context,
        'subdirs' => false,
        'enable_filemanagement' => false,
        'autosave' => false
    );

    $data = file_prepare_standard_editor($data, 'text', $editoroptions, $context, 'mod_pyramid', 'entry', $data->entryid);

    if (isset($grpcount)) {
        $form = new mod_pyramid_edit_form(null, array(
            'entryid' => $data->entryid,
            'editoroptions' => $editoroptions,
            'pyramid_phase' => $pyramid->phase,
            'grpcount' => $grpcount
        ));
    } else {
        $form = new mod_pyramid_edit_form(null, array(
            'entryid' => $data->entryid,
            'editoroptions' => $editoroptions,
            'pyramid_phase' => $pyramid->phase          
        ));
    }
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
            if (! $DB->update_record("pyramid_submission", $oldentry)) {
                print_error("Could not update your submission");
            } else {
                $newentry->pyramid_id = (int)$pyramid->id;
                $newentry->group_id = (int)$groupid;
                $newentry->current_version = 1;
                $newentry->timecreated = $timenow;
                $newentry->timemodified = $timenow;
                if (! $newentry->id = $DB->insert_record("pyramid_submission", $newentry)) {
                    print_error("Could not insert a new submission");
                }
            }
        } else {

            $newentry->group_id = (int)$groupid;
            $newentry->pyramid_id = (int)$pyramid->id;
            $newentry->current_version = 1;
            $newentry->timecreated = $timenow;
            $newentry->timemodified = $timenow;
            if (! $newentry->id = $DB->insert_record("pyramid_submission", $newentry)) {
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

    if ($entry) {
        if ($entry->timemodified > time()) {
            $urltogo = new moodle_url('/mod/pyramid/notification.php', array(
                'cmid' => $cmid
            ));
            redirect($urltogo);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($pyramid->name));

$intro = format_module_intro('pyramid', $pyramid, $cm->id);
echo $OUTPUT->box($intro);

$form->display();

echo $OUTPUT->footer();

