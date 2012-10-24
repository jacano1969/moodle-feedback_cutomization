<?php

/**
* prints an analysed excel-spreadsheet of the feedback
*
* @author Andreas Grabs
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package feedback
*/

require_once("../../config.php");
require_once("lib.php");
// require_once('easy_excel.php');
require('../attforblock/tcpdf/config/lang/eng.php');
require('../attforblock/tcpdf/tcpdf.php');
//require_once("$CFG->libdir/excellib.class.php");
session_start();
feedback_load_feedback_items();

$id = required_param('id', PARAM_INT);  //the POST dominated the GET


$coursefilter = optional_param('coursefilter', '0', PARAM_INT);

$url = new moodle_url('/mod/feedback/analysis_to_pdf.php', array('id'=>$id));
if ($coursefilter !== '0') {
    $url->param('coursefilter', $coursefilter);
}
$PAGE->set_url($url);

$formdata = data_submitted();
 
if (! $cm = get_coursemodule_from_id('feedback', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $feedback = $DB->get_record("feedback", array("id"=>$cm->instance))) {
    print_error('invalidcoursemodule');
}

if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
}

require_login($course->id, true, $cm);

require_capability('mod/feedback:viewreports', $context);

//buffering any output
//this prevents some output before the excel-header will be send
ob_start();
$fstring = new stdClass();
$fstring->bold = get_string('bold', 'feedback');
$fstring->page = get_string('page', 'feedback');
$fstring->of = get_string('of', 'feedback');
$fstring->modulenameplural = get_string('modulenameplural', 'feedback');
$fstring->questions = get_string('questions', 'feedback');
$fstring->itemlabel = get_string('item_label', 'feedback');
$fstring->question = get_string('question', 'feedback');
$fstring->responses = get_string('responses', 'feedback');
$fstring->idnumber = get_string('idnumber');
$fstring->username = get_string('username');
$fstring->fullname = get_string('fullnameuser');
$fstring->courseid = get_string('courseid', 'feedback');
$fstring->course = get_string('course');
$fstring->anonymous_user = get_string('anonymous_user','feedback');
ob_end_clean();

$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);		
$params['contextid'] =$context->id;
//$query="SELECT name from {course_categories} cat WHERE id =(SELECT category from {course} c where id=$course->id)";
$semester=$DB->get_record_sql("SELECT id,name from {course_categories} cat WHERE id =(SELECT category from {course} c where id=$course->id)");
$class=$DB->get_record_sql("SELECT name,id from {course_categories} ct WHERE id =(SELECT parent from {course_categories} cat WHERE id =$semester->id)");														
$string = $semester->name;
$find = "Section";
if(strstr($string, $find) ==true){
	$semester=$DB->get_record_sql("SELECT name,id from {course_categories} ct WHERE id =(SELECT parent from {course_categories} cat WHERE id =$semester->id)");					
	$class=$DB->get_record_sql("SELECT name,id from {course_categories} ct WHERE id =(SELECT parent from {course_categories} cat WHERE id =$semester->id)");														
	
}

$sem_duration=$DB->get_records_sql("SELECT startdate,numsections from {course} c WHERE id =$course->id");
foreach($sem_duration as $sem_duratn){
$sem_durtn=$sem_duratn->startdate;
$weeks=$sem_duratn->numsections;
}

$endtime = strtotime("+$weeks weeks", $sem_durtn);
$starttime= date(" M jS, Y", $sem_durtn);
$endtime= date(" M jS, Y", $endtime);
/*
$sql="SELECT *
FROM {user} u
JOIN {user_enrolments} ue ON ( ue.userid = u.id )
WHERE u.id
IN (

SELECT ra.userid
FROM {role_assignments} ra
WHERE ra.roleid =3
AND contextid =:contextid
)
GROUP BY u.id";
$faculty=$DB->get_records_sql( $sql , $params);
foreach($faculty as $fac){
	if($facultyname!=""){
		$facultyname.=" , ".$fac->firstname." ".$fac->lastname;
	}
	else{
		$facultyname.=$fac->firstname." ".$fac->lastname;
	}
//$facultyname=$fac->firstname." ".$fac->lastname;
}
*/
$faculty = explode("(", $feedback->name);
$facultyname=rtrim($faculty[1],")");

//get the questions (item-names)
if(!$items = $DB->get_records('feedback_item', array('feedback'=>$feedback->id, 'hasvalue'=>1), 'position')) {
    print_error('no_items_available_yet', 'feedback', $CFG->wwwroot.'/mod/feedback/view.php?id='.$id);
    exit;
}
//Added By Hina Yousuf //pdf

	$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	
	// set document information
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetAuthor('');
	$pdf->SetTitle('Feedback Report');
	$pdf->SetSubject('Feedback Report');
	
	// remove default header/footer
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	
	// set default monospaced font
	//$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
	
	//set margins
	//$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
	$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
	
	
	//set auto page breaks
	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
	
	//set image scale factor
	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
	
	//set some language-dependent strings
	$pdf->setLanguageArray($l);
	
	// ---------------------------------------------------------
	
	// set font
	$pdf->SetFont('helvetica', '', 8);
	
	// add a page
	$pdf->AddPage('P','A4');
	ob_clean();

	$mygroupid = groups_get_activity_group($cm);
	error_reporting(0);
	error_reporting($CFG->debug);
	//get the completeds
	$completedscount = feedback_get_completeds_group_count($feedback, $mygroupid, $coursefilter);
	$coursename=$course->fullname;
	if (empty($items)) {
	     $items=array();
	}

//Added By Hina Yousuf  //pdf
	$coursename=$course->fullname;
// 	$content = $content.'<h1 align="center">Faculty Feedback</h1>';
	$content = $content.'<table cellpadding="2" border="0"><tr><td ><img src="pics/NUST_Logo.jpg" height="52" width="52" /> <font size="15"><b>&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Faculty Feedback</b><br/></font></td></tr></table>';
    
      
  $content = $content.'<br/><table cellpadding="2" border="1"><tr>';
    $content = $content.'<td width="50%"><b>'."Course: ".$coursename.'</b></td>';
    $content = $content.'<td width="50%"><b>'. "Faculty: ".$facultyname.'</b></td></tr>';
    $content = $content.'<tr><td width="50%"><b>'."Semester: ".$semester->name.'</b></td>';
    $content = $content.'<td width="50%"><b>'. "Degree: ".$class->name.'</b></td></tr>';
    $content = $content.'<tr><td width="50%"><b>'."Semester Duartion: ".$starttime.'-'.$endtime.'</b></td></tr></table>';
   	$content = $content.'<br/><table cellpadding="2" border="2"><tr>';
    if (empty($items)) {
     $items=array();
	}
	$_SESSION['totalavg']=0;
	$count=0;
	$_SESSION['count']=$count;
	$_SESSION['excellent']=0;
	$_SESSION['questions']=0;
	$flag=false;
	foreach($items as $item) {
		$_SESSION['count']=$_SESSION['count']+1;
		$itemobj = feedback_get_item_class($item->typ);
		if($item->typ=="textfield"){
			$rowOffset1+=2;
			$flag=true;
			$content = $content.'<tr><td width="8%"><b>'."Total :".'</b></td>'; 
			for($i=0;$i<$_SESSION['datasize'];$i++)
			{
				 $content = $content.'<td width="15%"><b>'. $_SESSION[$i].'</b></td>';
			}
			$rowOffset1++;
			$content = $content.'</tr><tr><td width="8%"><b>'. "%age:".'</b></td>';
			$prod=($completedscount) * (sizeof($items)-1);
			$sep_dec = get_string('separator_decimal', 'feedback');
	        if(substr($sep_dec, 0, 2) == '[['){
	            $sep_dec = FEEDBACK_DECIMAL;
	        }
	
	        $sep_thous = get_string('separator_thousand', 'feedback');
	        if(substr($sep_thous, 0, 2) == '[['){
	            $sep_thous = FEEDBACK_THOUSAND;
	        }
			for($i=0;$i<$_SESSION['datasize'];$i++)
			{
				$percent=($_SESSION[$i]/$prod)*100;
				$percent=number_format((float)$percent, 2, $sep_dec, $sep_thous);
				$content = $content.'<td width="15%"><b>'. $percent.'</b></td>'; 
			}
			$totalmarks=$_SESSION['questions']*5;
			
			//print total results
			$totalavg=($_SESSION['totalavg']/100)*100;
			if($completedscount > 0){
				$content = $content.'</tr></table><table cellpadding="2" border="0"><tr><td></td></tr><tr><td width="20%"><b>'. 'Total Number of Students: '.strval($completedscount).'</b></td>';
			   // $worksheet1->write_string($rowOffset1+1, 0,'Total Number of Students: '.strval($completedscount), $xlsFormats->value_bold);
			}
			$content = $content.'<td width="20%"><b>'. "Total Marks: ".$totalmarks.'</b></td>';
			$totalavg=number_format((float)$totalavg, 2, $sep_dec, $sep_thous);
			$content = $content.'<td width="20%"><b>'. "%age Marks: ".$totalavg.'</b></td></tr></table>';
			$rowOffset1 = $itemobj->pdfprint_item($content, $item, $mygroupid, $coursefilter);
		}
		else{
		    //get the class of item-typ
		   $rowOffset1 = $itemobj->pdfprint_item($content, $item, $mygroupid, $coursefilter);
		}
	}
	if($flag==false){
		$rowOffset1+=2;
		$content = $content.'<tr><td width="8%"><b>'."Total :".'</b></td>'; 
		for($i=0;$i<$_SESSION['datasize'];$i++)
		{
			  $content = $content.'<td width="15%"><b>'. $_SESSION[$i].'</b></td>'; 
		}
		$rowOffset1++;
		$content = $content.'</tr><tr><td width="8%"><b>'. "%age:".'</b></td>'; 
		$prod=($completedscount) * (sizeof($items)-1);
		$sep_dec = get_string('separator_decimal', 'feedback');
		        if(substr($sep_dec, 0, 2) == '[['){
		            $sep_dec = FEEDBACK_DECIMAL;
		        }
		
		        $sep_thous = get_string('separator_thousand', 'feedback');
		        if(substr($sep_thous, 0, 2) == '[['){
		            $sep_thous = FEEDBACK_THOUSAND;
		        }
		for($i=0;$i<$_SESSION['datasize'];$i++)
		{
			$percent=($_SESSION[$i]/$prod)*100;
			$percent=number_format((float)$percent, 2, $sep_dec, $sep_thous);
			$content = $content.'<td width="15%"><b>'. $percent.'</b></td>'; 
		}
	
		$rowOffset1++;
		$totalmarks=$_SESSION['questions']*5;
		$totalavg=($_SESSION['totalavg']/100)*100;
		if($completedscount > 0){
			$content = $content.'</tr></table><table cellpadding="2" border="0"><tr><td>y<tr><td width="20%"><b>'. 'Total Number of Students: '.strval($completedscount).'</b></td>';		   
		}
		$content = $content.'<td width="20%"><b>'. "Total Marks: ".$totalmarks.'</b></td>';
		$totalavg=number_format((float)$totalavg, 2, $sep_dec, $sep_thous);
		$content = $content.'<td width="20%"><b>'. "%age Marks: ".$totalavg.'</b></td></tr></table>';
		 
	}  
		$pdf->writeHTML($content, true, false,false,false,'');
		//Close and output PDF document
		$pdf->Output("Feedback_Report".$facultyname, 'D');
		exit;
	
function ImprovedTable($headings,$data)
{
	global $CFG;
	global $headings;
	global $name,$type;
	global $department; 
	//echo "improe";
    //Column widths
    //$w=array(40,35,40,45);
    //Header
    $content = $content.'<h1 align="center">Faculty Feedback Report</h1><h1 align="left">School: '.$name.'</h1>';
    $content = $content.'<h1 align="left">Department: ';   
    $content = $content.$department;
    $content = $content.'</h1>';
    $content = $content.'<table cellpadding="2" border="1"><tr>';
    /*$content = $content.'<td width="3%">-</td><td width="%10">Lecture Hours/Lab</td><td width="13%">-</td>';
    foreach ($lecture as $l){
        $content = $content.'<td width="18">'.$l.'</td>';
    }
    $content = $content.'<td width="18">-</td><td width="18">-</td><td width="25">-</td></tr><tr>';*/
    for($i=0;$i<count($headings);$i++)
        //$this->Cell($w[$i],7,$header[$i],1,0,'C');
        if($i==0){
            $content = $content.'<td width="40%">'.$headings[$i].'</td>';
        }elseif($i==1){
            $content = $content.'<td width="10%">'.$headings[$i].'</td>';
        }elseif($i==2){
            $content = $content.'<td width="10%">'.$headings[$i].'</td>';
        }elseif($i==3){
            $content = $content.'<td width="15%">'.$headings[$i].'</td>';
        }
    //$this->Ln();
    $content = $content. '</tr>';
    //Data
    foreach($data->data as $row)
    {
        $content = $content. '<tr>';
        $i = 0;
       foreach($row as $col){
           if($i==0){
            $content = $content. '<td width="40%">'.$col.'</td>';
           }elseif($i==1){
            $content = $content. '<td width="10%">'.$col.'</td>';
           }elseif($i==2){
            $content = $content. '<td width="10%">'.$col.'</td>';
           }elseif($i==3){
            $content = $content.'<td width="15%">'.$col.'</td>';
           }
           $i = $i + 1;
       }
       $content = $content. '</tr>';
    }
    $content = $content. '</table>';
    //Closure line
    //$this->Cell(array_sum($w),0,'','T');
    //echo 'Hello';
    return  $content;
}

//end of pdf
function feedback_excelprint_detailed_head(&$worksheet, $xlsFormats, $items, $rowOffset) {
    global $fstring, $feedback;

    if(!$items) return;
    $colOffset = 0;

    // $worksheet->setFormat('<l><f><ru2>');

    $worksheet->write_string($rowOffset + 1, $colOffset, $fstring->idnumber, $xlsFormats->head2);
    $colOffset++;

    $worksheet->write_string($rowOffset + 1, $colOffset, $fstring->username, $xlsFormats->head2);
    $colOffset++;

    $worksheet->write_string($rowOffset + 1, $colOffset, $fstring->fullname, $xlsFormats->head2);
    $colOffset++;

    foreach($items as $item) {
        // $worksheet->setFormat('<l><f><ru2>');
        $worksheet->write_string($rowOffset, $colOffset, $item->name, $xlsFormats->head2);
        $worksheet->write_string($rowOffset + 1, $colOffset, $item->label, $xlsFormats->head2);
        $colOffset++;
    }

    // $worksheet->setFormat('<l><f><ru2>');
    $worksheet->write_string($rowOffset + 1, $colOffset, $fstring->courseid, $xlsFormats->head2);
    $colOffset++;

    // $worksheet->setFormat('<l><f><ru2>');
    $worksheet->write_string($rowOffset + 1, $colOffset, $fstring->course, $xlsFormats->head2);
    $colOffset++;

    return $rowOffset + 2;
}

function feedback_excelprint_detailed_items(&$worksheet, $xlsFormats, $completed, $items, $rowOffset) {
    global $DB, $fstring;

    if(!$items) return;
    $colOffset = 0;
    $courseid = 0;

    $feedback = $DB->get_record('feedback', array('id'=>$completed->feedback));
    //get the username
    //anonymous users are separated automatically because the userid in the completed is "0"
    // $worksheet->setFormat('<l><f><ru2>');
    if($user = $DB->get_record('user', array('id'=>$completed->userid))) {
        if ($completed->anonymous_response == FEEDBACK_ANONYMOUS_NO) {
            $worksheet->write_string($rowOffset, $colOffset, $user->idnumber, $xlsFormats->head2);
            $colOffset++;
            $userfullname = fullname($user);
            $worksheet->write_string($rowOffset, $colOffset, $user->username, $xlsFormats->head2);
            $colOffset++;
        } else {
            $userfullname = $fstring->anonymous_user;
            $worksheet->write_string($rowOffset, $colOffset, '-', $xlsFormats->head2);
            $colOffset++;
            $worksheet->write_string($rowOffset, $colOffset, '-', $xlsFormats->head2);
          $colOffset++;
        }
    }else {
        $userfullname = $fstring->anonymous_user;
        $worksheet->write_string($rowOffset, $colOffset, '-', $xlsFormats->head2);
        $colOffset++;
        $worksheet->write_string($rowOffset, $colOffset, '-', $xlsFormats->head2);
        $colOffset++;
    }

    $worksheet->write_string($rowOffset, $colOffset, $userfullname, $xlsFormats->head2);

    $colOffset++;
    foreach($items as $item) {
        $value = $DB->get_record('feedback_value', array('item'=>$item->id, 'completed'=>$completed->id));

        $itemobj = feedback_get_item_class($item->typ);
        $printval = $itemobj->get_printval($item, $value);
        $printval = trim($printval);

        // $worksheet->setFormat('<l><vo>');
        if(is_numeric($printval)) {
            $worksheet->write_number($rowOffset, $colOffset, $printval, $xlsFormats->default);
        } elseif($printval != '') {
            $worksheet->write_string($rowOffset, $colOffset, $printval, $xlsFormats->default);
        }
        $printval = '';
        $colOffset++;
        $courseid = isset($value->course_id) ? $value->course_id : 0;
        if($courseid == 0) $courseid = $feedback->course;
    }
    $worksheet->write_number($rowOffset, $colOffset, $courseid, $xlsFormats->default);
    $colOffset++;
    if(isset($courseid) AND $course = $DB->get_record('course', array('id'=>$courseid))) {
        $worksheet->write_string($rowOffset, $colOffset, $course->shortname, $xlsFormats->default);
    }
    return $rowOffset + 1;
}

