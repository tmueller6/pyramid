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
 * Datei für AJAX-Aufruf um Editieren alle 10 Sekunden für weitere Nutzer zu sperren
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once (dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once (dirname(__FILE__) . '/lib.php');
require_once (__DIR__ . '/locallib.php');
require_once ('./edit_form.php');

global $DB;

$pyramid_id = $_POST['pyramid_id'];
$first = $_POST['first'];
$second = $_POST['second'];
$third = $_POST['third'];
$fourth = $_POST['fourth'];

if ($third <= time()) {
    $DB->set_field(MOD_PYRAMID_TABLE, 'phase', 40, array(
        "id" => $pyramid_id
    ));
} elseif ($second <= time()) {
    $DB->set_field(MOD_PYRAMID_TABLE, 'phase', 30, array(
        "id" => $pyramid_id
    ));
} elseif ($first <= time()) {
    $DB->set_field(MOD_PYRAMID_TABLE, 'phase', 20, array(
        "id" => $pyramid_id
    ));
} else {
    $DB->set_field(MOD_PYRAMID_TABLE, 'phase', 10, array(
        "id" => $pyramid_id
    ));
}
?>