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
 * Capability definitions for the pyramid module
 *
 * The capabilities are loaded into the database table when the module is
 * installed or updated. Whenever the capability definitions are updated,
 * the module version number should be bumped up.
 *
 * The system has four possible values for a capability:
 * CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
 *
 * It is important that capability names are unique. The naming convention
 * for capabilities that are specific to modules and blocks is as follows:
 *   [mod/block]/<plugin_name>:<capabilityname>
 *
 * component_name should be the same as the directory name of the mod or block.
 *
 * Core moodle capabilities are defined thus:
 *    moodle/<capabilityclass>:<capabilityname>
 *
 * Examples: mod/forum:viewpost
 *           block/recent_activity:view
 *           moodle/site:deleteuser
 *
 * The variable name for the capability definitions array is $capabilities
 *
 * @package    mod_pyramid
 * @copyright  2020 Tom Mueller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

	'mod/pyramid:addinstance' => array(
			'riskbitmask' => RISK_XSS,
			'captype' => 'write',
			'contextlevel' => CONTEXT_COURSE,
			'archetypes' => array(
					'editingteacher' => CAP_ALLOW,
					'manager' => CAP_ALLOW
			),
			'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),
	
	'mod/pyramid:manage' => array(
			'riskbitmask' => RISK_XSS,
			'captype' => 'write',
			'contextlevel' => CONTEXT_COURSE,
			'archetypes' => array(
					'editingteacher' => CAP_ALLOW,
					'manager' => CAP_ALLOW
			),
			'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),
	
	'mod/pyramid:itemedit' => array(
			'riskbitmask' => RISK_XSS,
			'captype' => 'write',
			'contextlevel' => CONTEXT_COURSE,
			'archetypes' => array(
					'editingteacher' => CAP_ALLOW,
					'manager' => CAP_ALLOW
			),
			'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),
	
	'mod/pyramid:itemview' => array(
			'riskbitmask' => RISK_XSS,
			'captype' => 'write',
			'contextlevel' => CONTEXT_COURSE,
			'archetypes' => array(
					'editingteacher' => CAP_ALLOW,
					'teacher' => CAP_ALLOW,
					'manager' => CAP_ALLOW
			),
			'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),
	
	'mod/pyramid:preview' => array(
			'captype' => 'write',
			'contextlevel' => CONTEXT_COURSE,
			'archetypes' => array(
					'teacher' => CAP_ALLOW,
					'editingteacher' => CAP_ALLOW,
					'manager' => CAP_ALLOW
			),
			'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),

    'mod/pyramid:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'mod/pyramid:submit' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'student' => CAP_ALLOW
        )
    ),
    
    'mod/pyramid:editdimensions' => array(
        'riskbitmask' => RISK_XSS,              // can embed flash and javascript into wysiwyg
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    
    'mod/pyramid:manageexamples' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'mod/pyramid:switchphase' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    
    'mod/pyramid:resetlock' => array(
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    
    // Ability to submit own work. All users having this capability are expected to participate
    // in the workshop as the authors
    'mod/pyramid:submit' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'student' => CAP_ALLOW,
        )
    ),
);

