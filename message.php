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
$cmid = $_POST['cmid'];
$phase = $_POST['phase'];
$first = $_POST['first'];
$second = $_POST['second'];
$third = $_POST['third'];
$fourth = $_POST['fourth'];
$name = $_POST['name'];
$users = $_POST['users'];
$users = json_decode($users);
$creator = $_POST['creator'];

$message = new \core\message\message();
$message->component = 'moodle';
$message->name = 'instantmessage';
$message->userfrom = $creator;
$message->subject = 'Erinnerung für Kurs: '.$name;
$message->fullmessage = 'Erinnerung: Nicht vergessen für den Kurs: '.$name.' einen Text einzureichen.';
$message->fullmessageformat = FORMAT_MARKDOWN;
$message->fullmessagehtml = '<p>Erinnerung: Nicht vergessen für den Kurs: '.$name.' einen Text einzureichen.</p>';
$message->smallmessage = 'Erinnerung: Nicht vergessen für den Kurs: '.$name.' einen Text einzureichen.';
$message->notification = '0';
$message->replyto = "random@example.com";
$content = array('*' => array('header' => ' test ', 'footer' => ' test '));
$message->set_additional_content('email', $content);
$message->courseid = $cmid;

switch($phase) {
    case 10:

        $message->userto = 3;

}


