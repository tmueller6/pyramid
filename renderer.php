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


defined('MOODLE_INTERNAL') || die();

/**
 * A custom renderer class that extends the plugin_renderer_base.
 *
 * @package mod_pyramid
 * @copyright 2020 Tom Mueller
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_pyramid_renderer extends plugin_renderer_base { 



    /**
     * Returns the header for the module
     * @param mod $instance
     * @param string $currenttab current tab that is shown.
     * @param int    $item id of the anything that needs to be displayed.
     * @param string $extrapagetitle String to append to the page title.
     * @return string
     */
    
    public function header($moduleinstance, $cm, $currenttab = '', $itemid = null, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname.": ".$activityname;
        } else {
            $title = $this->page->course->shortname.": ".$activityname.": ".$extrapagetitle;
        }

        // Build the buttons
        $context = context_module::instance($cm->id);

        //Header setup
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        $output = $this->output->header();

        if (has_capability('mod/pyramid:manage', $context)) {
            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/mod/pyramid/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        } else {
            $output .= $this->output->heading($activityname);
        }
        return $output;
    }
	
	/**
     * Return HTML to display limited header
     */
      public function notabsheader(){
      	return $this->output->header();
      }


    /**
     *
     */
    public function show_name($showtext) {
		$ret = $this->output->box_start();
		$ret .= $this->output->heading($showtext, 3, 'main');
		$ret .= $this->output->box_end();
        return $ret;
    }
    
    
    public function show_grouplink($showtext, $id, $groupid){
        $ret = $this->output->container_start();
        $ret .= $this->output->single_button(new moodle_url('viewgroup.php',  array('id'=>$id, 'groupid'=>$groupid)), $showtext, 'post');
        $ret .= $this->output->container_end();
        return $ret;
    }
    

	 /**
     *
     */
	public function show_intro($pyramid,$cm){
		$ret = "";
		if (trim(strip_tags($pyramid->intro))) {
			$ret .= $this->output->box_start('mod_introbox');
			$ret .= format_module_intro('pyramid', $pyramid, $cm->id);
			$ret .= $this->output->box_end();
		}
		return $ret;
	}
	
	
	/**
	 * Renders the user plannner tool
	 *
	 * @param pyramid_user_plan $plan prepared for the user
	 * @return string html code to be displayed
	 */
	protected function render_pyramid_user_plan(pyramid_user_plan $plan) {
	    $o  = '';    // Output HTML code.
	    $numberofphases = count($plan->phases);
	    $o .= html_writer::start_tag('div', array(
	        'class' => 'userplan',
	        'aria-labelledby' => 'mod_workshop-userplanheading',
	        'aria-describedby' => 'mod_workshop-userplanaccessibilitytitle',
	    ));
	    
	    $o .= html_writer::span(get_string('userplanaccessibilitytitle', 'pyramid', $numberofphases),
	        'accesshide', array('id' => 'mod_workshop-userplanaccessibilitytitle'));
	    $o .= html_writer::link('#mod_workshop-userplancurrenttasks', get_string('userplanaccessibilityskip', 'pyramid'),
	        array('class' => 'accesshide'));

	    foreach ($plan->phases as $phasecode => $phase) {
	        $o .= html_writer::start_tag('dl', array('class' => 'phase'));
	        $actions = '';
	        if ($phase->active) {
	            // Mark the section as the current one.
	            $icon = $this->output->pix_icon('i/marked', '', 'moodle', ['role' => 'presentation']);
	            $actions .= get_string('userplancurrentphase', 'workshop').' '.$icon;
	            
	        } else {
	            // Display a control widget to switch to the given phase or mark the phase as the current one.
	            foreach ($phase->actions as $action) {
	                
	                if ($action->type === 'switchphase') {
	                    if ($phasecode == pyramid::PHASE_2 && $plan->pyramid->phase == pyramid::PHASE_3
	                        && $plan->pyramid->phaseswitchassessment) {
	                            $icon = new pix_icon('i/scheduled', get_string('switchphaseauto', 'pyramid'));
	                        } else {
	                            $icon = new pix_icon('i/marker', get_string('switchphase'.$phasecode, 'pyramid'));
	                        }
	                        $actions .= $this->output->action_icon($action->url, $icon, null, null, true);
	                }
	            }
	        }
	        
	        if (!empty($actions)) {
	            $actions = $this->output->container($actions, 'actions');
	        }
	        $classes = 'phase' . $phasecode;
	        if ($phase->active) {
	            $title = html_writer::span($phase->title, 'phasetitle', ['id' => 'mod_workshop-userplancurrenttasks']);
	            $classes .= ' active';
	        } else {
	            $title = html_writer::span($phase->title, 'phasetitle');
	            $classes .= ' nonactive';
	        }
	        $o .= html_writer::start_tag('dt', array('class' => $classes));
	        $o .= $this->output->container($title . $actions);
	        $o .= html_writer::start_tag('dd', array('class' => $classes. ' phasetasks'));
	        $o .= $this->helper_user_plan_tasks($phase->tasks);
	        $o .= html_writer::end_tag('dd');
	        $o .= html_writer::end_tag('dl');
	    }
	    $o .= html_writer::end_tag('div');
	    return $o;
	}
	
	/**
	 * Renders the tasks for the single phase in the user plan
	 *
	 * @param stdClass $tasks
	 * @return string html code
	 */
	protected function helper_user_plan_tasks(array $tasks) {
	    $out = '';
	    foreach ($tasks as $taskcode => $task) {
	        $classes = '';
	        $accessibilitytext = '';
	        $icon = null;
	        if ($task->completed === true) {
	            $classes .= ' completed';
	            $accessibilitytext .= get_string('taskdone', 'pyramid') . ' ';
	        } else if ($task->completed === false) {
	            $classes .= ' fail';
	            $accessibilitytext .= get_string('taskfail', 'pyramid') . ' ';
	        } else if ($task->completed === 'info') {
	            $classes .= ' info';
	            $accessibilitytext .= get_string('taskinfo', 'pyramid') . ' ';
	        } else {
	            $accessibilitytext .= get_string('tasktodo', 'pyramid') . ' ';
	        }
	        if (is_null($task->link)) {
	            $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
	            $title .= $task->title;
	        } else {
	            $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
	            $title .= html_writer::link($task->link, $task->title);
	        }
	        $title = $this->output->container($title, 'title');
	        $details = $this->output->container($task->details, 'details');
	        $out .= html_writer::tag('li', $title . $details, array('class' => $classes));
	    }
	    if ($out) {
	        $out = html_writer::tag('ul', $out, array('class' => 'tasks'));
	    }
	    return $out;
	}
  
}

class mod_pyramid_report_renderer extends plugin_renderer_base {


	public function render_reportmenu($moduleinstance,$cm) {
		
		$basic = new single_button(
			new moodle_url(MOD_PYRAMID_URL . '/reports.php',array('report'=>'basic','id'=>$cm->id,'n'=>$moduleinstance->id)), 
			get_string('basicreport',MOD_PYRAMID_LANG), 'get');

		$ret = html_writer::div($this->render($basic) .'<br />'  ,MOD_PYRAMID_CLASS  . '_listbuttons');

		return $ret;
	}

	public function render_delete_allattempts($cm){
		$deleteallbutton = new single_button(
				new moodle_url(MOD_PYRAMID_URL . '/manageattempts.php',array('id'=>$cm->id,'action'=>'confirmdeleteall')), 
				get_string('deleteallattempts',MOD_PYRAMID_LANG), 'get');
		$ret =  html_writer::div( $this->render($deleteallbutton) ,MOD_PYRAMID_CLASS  . '_actionbuttons');
		return $ret;
	}

	public function render_reporttitle_html($course,$username) {
		$ret = $this->output->heading(format_string($course->fullname),2);
		$ret .= $this->output->heading(get_string('reporttitle',MOD_PYRAMID_LANG,$username),3);
		return $ret;
	}

	public function render_empty_section_html($sectiontitle) {
		global $CFG;
		return $this->output->heading(get_string('nodataavailable',MOD_PYRAMID_LANG),3);
	}
	
	public function render_exportbuttons_html($cm,$formdata,$showreport){
		$formdata = (array) $formdata;
		$formdata['id']=$cm->id;
		$formdata['report']=$showreport;
		$formdata['format']='csv';
		$excel = new single_button(
			new moodle_url(MOD_PYRAMID_URL . '/reports.php',$formdata), 
			get_string('exportexcel',MOD_PYRAMID_LANG), 'get');

		return html_writer::div( $this->render($excel),MOD_PYRAMID_CLASS  . '_actionbuttons');
	}
	

	
	public function render_section_csv($sectiontitle, $report, $head, $rows, $fields) {

        // Use the sectiontitle as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($sectiontitle, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));
		$quote = '"';
		$delim= ",";//"\t";
		$newline = "\r\n";

		header("Content-Disposition: attachment; filename=$name.csv");
		header("Content-Type: text/comma-separated-values");

		//echo header
		$heading="";	
		foreach($head as $headfield){
			$heading .= $quote . $headfield . $quote . $delim ;
		}
		echo $heading. $newline;
		
		//echo data rows
        foreach ($rows as $row) {
			$datarow = "";
			foreach($fields as $field){
				$datarow .= $quote . $row->{$field} . $quote . $delim ;
			}
			 echo $datarow . $newline;
		}
        exit();
	}

	public function render_section_html($sectiontitle, $report, $head, $rows, $fields) {
		global $CFG;
		if(empty($rows)){
			return $this->render_empty_section_html($sectiontitle);
		}
		
		//set up our table and head attributes
		$tableattributes = array('class'=>'generaltable '. MOD_PYRAMID_CLASS .'_table');
		$headrow_attributes = array('class'=>MOD_PYRAMID_CLASS . '_headrow');
		
		$htmltable = new html_table();
		$htmltable->attributes = $tableattributes;
		
		
		$htr = new html_table_row();
		$htr->attributes = $headrow_attributes;
		foreach($head as $headcell){
			$htr->cells[]=new html_table_cell($headcell);
		}
		$htmltable->data[]=$htr;
		
		foreach($rows as $row){
			$htr = new html_table_row();
			//set up descrption cell
			$cells = array();
			foreach($fields as $field){
				$cell = new html_table_cell($row->{$field});
				$cell->attributes= array('class'=>MOD_PYRAMID_CLASS . '_cell_' . $report . '_' . $field);
				$htr->cells[] = $cell;
			}

			$htmltable->data[]=$htr;
		}
		$html = $this->output->heading($sectiontitle, 4);
		$html .= html_writer::table($htmltable);
		return $html;
		
	}
	
	  /**
       * Returns HTML to display a single paging bar to provide access to other pages  (usually in a search)
       * @param int $totalcount The total number of entries available to be paged through
       * @param stdclass $paging an object containting sort/perpage/pageno fields. Created in reports.php and grading.php
       * @param string|moodle_url $baseurl url of the current page, the $pagevar parameter is added
       * @return string the HTML to output.
       */
    function show_paging_bar($totalcount,$paging,$baseurl){
		$pagevar="pageno";
		$baseurl->params(array('perpage'=>$paging->perpage,'sort'=>$paging->sort));
    	return $this->output->paging_bar($totalcount,$paging->pageno,$paging->perpage,$baseurl,$pagevar);
    }
	
	function show_reports_footer($moduleinstance,$cm,$formdata,$showreport){
		$link = new moodle_url(MOD_PYRAMID_URL . '/reports.php',array('report'=>'menu','id'=>$cm->id,'n'=>$moduleinstance->id));
		$ret =  html_writer::link($link, get_string('returntoreports',MOD_PYRAMID_LANG));
		$ret .= $this->render_exportbuttons_html($cm,$formdata,$showreport);
		return $ret;
	}

}

