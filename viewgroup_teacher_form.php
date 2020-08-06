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
defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . '/lib/formslib.php');

class mod_pyramid_view_teacher_form extends moodleform
{

    public function definition()
    {
        /*
         * if (!($this->_customdata['pyramid_phase'] == 10)) {
         * $this->_form->addElement('textarea', 'text1', 'Text 1', array(
         * 'readonly' => 'true',
         * 'rows' => '10',
         * 'cols' => '180'
         * ));
         * $this->_form->addElement('textarea', 'text2', 'Text 2', array(
         * 'readonly' => 'true',
         * 'rows' => '10',
         * 'cols' => '180'
         * ));
         * }
         */
        $this->_form->addElement('textarea', 'text1', 'Aktuelle Version der Gruppe', array(
            'readonly' => 'true',
            'rows' => '10',
            'cols' => '180'
        ));

        $this->_form->addElement('header', 'general', "Nachtrag", true);
        $this->_form->setExpanded('general',false);
        $this->_form->addElement('editor', 'text_editor', get_string('entry', 'pyramid'), $this->_customdata['editoroptions']);
        $this->_form->setType('text_editor', PARAM_RAW);
        //$this->_form->addRule('text_editor', null, 'required', null, 'client');
        $this->_form->addElement('hidden', 'cmid');
        $this->_form->setType('cmid', PARAM_INT);

        $this->_form->addElement('hidden', 'groupid');
        $this->_form->setType('groupid', PARAM_INT);


        $this->add_action_buttons();
    }
}