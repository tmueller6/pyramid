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
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require (__DIR__ . '/../../config.php');
require_once (__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT); // course module

$cm = get_coursemodule_from_id('pyramid', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array(
    'id' => $cm->course
), '*', MUST_EXIST);
$pyramid = $DB->get_record('pyramid', array(
    'id' => $cm->instance
), '*', MUST_EXIST);
$pyramid = new pyramid($pyramid, $cm, $course);

$PAGE->set_url($pyramid->notification_url(), array(
    'cmid' => $cmid
));

require_login($course, false, $cm);

$PAGE->set_title($pyramid->name);
$PAGE->set_heading($course->fullname);

//
// Output starts here
//
echo $OUTPUT->header();
echo $OUTPUT->heading($pyramid->name);
echo $OUTPUT->notification("Ein/eine anderer/andere Nutzer*in bearbeitet gerade den Eintrag. Bitte versuchen Sie es später noch einmal.", 'warning');
echo $OUTPUT->continue_button($pyramid->view_url());
echo $OUTPUT->footer();

