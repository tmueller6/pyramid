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
 * pyramid module admin settings and defaults
 *
 * @package    mod
 * @subpackage pyramid
 * @copyright  2020 Tom Mueller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/mod/pyramid/lib.php');

/*if ($ADMIN->fulltree) {
	  $settings->add(new admin_setting_configtext('mod_pyramid/someadminsetting',
        get_string('someadminsetting', 'pyramid'), get_string('someadminsetting_details', MOD_PYRAMID_LANG), 'default text', PARAM_TEXT));

}*/
