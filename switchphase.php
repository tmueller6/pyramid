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
 * Change the current phase of the workshop
 *
 * @package    mod_pyramid
 * @copyright  2020 Tom Müller <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');  
require_once(__DIR__.'/locallib.php');

$cmid       = required_param('cmid', PARAM_INT);            // course module
$phase      = required_param('phase', PARAM_INT);           // the code of the new phase
$confirm    = optional_param('confirm', false, PARAM_BOOL); // confirmation

$cm         = get_coursemodule_from_id('pyramid', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$pyramid   = $DB->get_record('pyramid', array('id' => $cm->instance), '*', MUST_EXIST);
$pyramid   = new pyramid($pyramid, $cm, $course);

$PAGE->set_url($pyramid->switchphase_url($phase), array('cmid' => $cmid, 'phase' => $phase));

require_login($course, false, $cm);
require_capability('mod/pyramid:switchphase', $PAGE->context);

if ($confirm) {
    if (!confirm_sesskey()) {
        throw new moodle_exception('confirmsesskeybad');
    }
    if (!$pyramid->switch_phase($phase)) {
        print_error('errorswitchingphase', 'workshop', $pyramid->view_url());
    }
    redirect($pyramid->view_url());
}

$PAGE->set_title($pyramid->name);
$PAGE->set_heading($course->fullname);

//
// Output starts here
//
echo $OUTPUT->header();

echo $OUTPUT->heading($pyramid->name);
echo $OUTPUT->confirm(get_string('switchphase' . $phase . 'info', 'pyramid'),
    new moodle_url($PAGE->url, array('confirm' => 1)), $pyramid->view_url());
echo $OUTPUT->footer();

