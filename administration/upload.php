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
 * 
 *
 * @package    local
 * @subpackage reservasalas
 * @copyright  2014 Nicolas Ba�ados
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//Page to block a student

require_once(dirname(__FILE__) . '/../../config.php'); //Mandatory
require_once($CFG->dirroot.'/local/bookingrooms/administration_form.php');
require_once($CFG->dirroot.'/local/bookingrooms/import_form.php');
require_once($CFG->dirroot.'/local/bookingrooms/tables.php');
require_once($CFG->libdir . '/csvlib.class.php');

global $PAGE, $CFG, $OUTPUT, $DB;
require_login();
$url = new moodle_url('/local/bookingrooms/upload.php'); 
$context = context_system::instance();//context_system::instance();
//$id            = required_param('id', PARAM_INT); // course id
$separator     = optional_param('separator', '', PARAM_ALPHA);
$verbosescales = optional_param('verbosescales', 1, PARAM_BOOL);
$iid           = optional_param('iid', null, PARAM_INT);
$importcode    = optional_param('importcode', '', PARAM_FILE);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');



$PAGE->navbar->add(get_string('roomsreserve', 'local_bookingrooms'),'booking.php');
$PAGE->navbar->add('CSV');
echo $OUTPUT->header();
if(!has_capability('local/bookingrooms:upload', $context)) {
	print_error(get_string('INVALID_ACCESS','booking_room'));
}
if(isset($CFG->local_uai_debug) && $CFG->local_uai_debug==0) {
	print_error(get_string('INVALID_ACCESS','booking_room'));
	
}
$mform = new import_form(null, array('includeseparator'=>true, 'verbosescales'=>true));
/*echo html_writer::start_tag('div', array('class' => 'clearer'));
echo html_writer::end_tag('div');
$mform->display();
*/
if (!$iid) {
	// If the import form has been submitted.
	if ($mform->is_cancelled()){
		
		redirect($url);
		
	}else if ($formdata = $mform->get_data()) {

		// Large files are likely to take their time and memory. Let PHP know
		// that we'll take longer, and that the process should be recycled soon
		// to free up memory.
		@set_time_limit(0);
		raise_memory_limit(MEMORY_EXTRA);

		

		$text = $mform->get_file_content('userfile');
		$iid = csv_import_reader::get_new_iid('rooms');
		$csvimport = new csv_import_reader($iid, 'rooms');

		$csvimport->load_csv_content($text, $formdata->encoding, $separator);

		// --- get header (field names) ---
		$header = $csvimport->get_columns();

		// Print a preview of the data.
		$numlines = 0; // 0 lines previewed so far.

		echo $OUTPUT->heading(get_string('importpreview', 'grades'));

		foreach ($header as $i => $h) {
			$h = trim($h); // Remove whitespace.
			$h = clean_param($h, PARAM_RAW); // Clean the header.
			$header[$i] = $h;
		}

		$table = new html_table();
		$table->head = $header;
		$csvimport->init();
		$previewdata = array();
		while ($numlines <= $formdata->previewrows) {
			$lines = $csvimport->next();
			if ($lines) {
				
				$previewdata[] = $lines;
			}
			$numlines ++;
		}
		
		$table->data = $previewdata;
		echo html_writer::table($table);
	} else {
		// Display the standard upload file form.
		
		echo html_writer::start_tag('div', array('class' => 'clearer'));
		echo html_writer::end_tag('div');

		$mform->display();
		echo $OUTPUT->footer();
		die();
	}
}

// Data has already been submitted so we can use the $iid to retrieve it.


$mform2 = new import_mapping_form(null,array('iid'=>$iid));

if ($mform2->is_cancelled()){
	
	redirect($url);
		
	}else if ($formdata = $mform2->get_data()) {
	$csvimport = new csv_import_reader($iid, 'rooms');
	$header = $csvimport->get_columns();
	@set_time_limit(0);
	raise_memory_limit(MEMORY_EXTRA);
	
	$csvimport->init();
	$info=array();
	
	while ($line = $csvimport->next()) {
		if(count($line) <= 1){
			// there is no data on this line, move on
			continue;
		}
		
		$data=new stdClass();
		$data->date_booking=$line[0];
		$data->module=$line[1];
		$data->confirmed=$line[2];
		$data->active=$line[3];
		$data->student_id=$line[4];
		$data->rooms_id=$line[5];
		
		$data->comment_student=$line[6];
		$data->comment_admin=$line[7];
		$data->ip=$line[8];
		$data->date_creation=$line[9];
		$data->name_event=$line[10];
		$data->assistants=$line[11];
		if(!$duplicated=$DB->get_record('bookingrooms_bookings',array('date_bookings'=>$data->date_booking,'module'=>$data->module,
		'rooms_id'=>$data->rooms_id,'active'=>1))){
		
		$DB->insert_record('bookingrooms_bookings', $data);
		echo get_string('inserted', 'local_bookingrooms');

	}else{
		$data->id=$duplicated->id;
		$DB->update_record('bookingrooms_bookings', $data);
		echo get_string('updated', 'local_bookingrooms');
		
	}
	}

	echo get_string('informationadded', 'local_bookingrooms');
    echo $OUTPUT->single_button($url, get_string('return', 'local_bookingrooms'));

}else{
	$mform2->display();
}
echo $OUTPUT->footer();
