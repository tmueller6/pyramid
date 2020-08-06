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
 * JavaScript library for the pyramid module.
 * 
 * @package mod
 * @subpackage pyramid
 * @copyright 2020 Tom Mueller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_pyramid = M.mod_pyramid || {};

M.mod_pyramid.helper = {
	gY: null,


	 /**
		 * @param Y
		 *            the YUI object
		 * @param opts
		 *            an array of options
		 */
    init: function(Y,opts) {
    	
    	M.mod_pyramid.helper.gY = Y;
    	// alert(opts['someinstancesetting']);
    	//console.log(opts['someinstancesetting']);
    
    },
    
    test: function(Y,opts){
    	var gid = opts['groupid'];
    	console.log(gid);
    	console.log("test");
    }
};
M.mod_pyramid.lock = function get_fb(Y,opts){
	var gid = opts['groupid'];
    var feedback = $.ajax({
        type: "POST",
        url: "lock.php",
        data: {id: gid},
        async: false
    	}).done(function(){
        setTimeout(function(){get_fb(Y,opts);}, 10000);
    }).responseText;

    $('div.feedback-box').html(feedback);
};

M.mod_pyramid.switchphase = function get_fb(Y,opts){
	var pid = opts['pyramid_id'];
	var first = opts['first'];
	var second = opts['second'];
	var third = opts['third'];
	var fourth = opts['fourth'];
	var feedback = $.ajax({
		type: "POST",
		url: "autoswitch.php",
		data: {pyramid_id: pid, first: first, second: second, third: third, fourth: fourth},
		async: false
	}).done(function(){
		setTimeout(function(){get_fb(Y,opts);}, 10000);
	}).responseText;

	$('div.feedback-box').html(feedback);
};

M.mod_pyramid.send_message = function get_fb(Y,opts){
	var cmid = opts['cmid'];
	var phase = opts['phase'];
	var first = opts['first'];
	var second = opts['second'];
	var third = opts['third'];
	var fourth = opts['fourth'];
	var name = opts['name'];
	var users = opts['users'];
	var creator = opts['creator'];
	var feedback = $.ajax({
		type: "POST",
		url: "message.php",
		data: {cmid: cmid, phase: phase, first: first, second: second, third: third, fourth: fourth, name: name, users: users, creator: creator},
		async: false
	}).done(function(){
		//setTimeout(function(){get_fb(Y,opts);}, 10000);
		console.log("test1");
	}).responseText;

	$('div.feedback-box').html(feedback);
};

