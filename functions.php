<?php

//Generate this token on an account with full administrative privileges.
// You should probably consider making an API account, as tying the token to a person
// could have negative effects when the person leaves.
$access_token = "YOUR-CANVAS-TOKEN";

//Where on this machine are the SIS files located?
//It's easiest to create a Samba share and have the SIS dump CSV files into it.
$samba_share = "/srv/samba/canvas/";

//Canvas Base URL
// Change this to be your Canvas Base URL
$canvas_base_url="https://YOURINSTITUTION.instructure.com";

// check file freshness
function file_fresh ($file) {
	$diff=time()-filemtime($file);
	if (file_exists($file)) {
		if ($diff < 604800) {
			return TRUE;			
		}
		else {
			return FALSE;
		}
	}
	else {
		return FALSE;
	}
}
 
 // create_user - this will make a new user accoutn in Canvas
function create_user ( $ID, $first_name, $last_name, $email ) {
	global $access_token, $canvas_base_url;
	
	$pass=md5(uniqid($first_name.$last_name, true));
	$url=$canvas_base_url."/api/v1/accounts/1/users.json";
	system("curl $url -F 'user[name]=$first_name $last_name' -F 'user[short_name]=$first_name' -F 'pseudonym[unique_id]=$email' -F 'pseudonym[password]=$pass' -F 'pseudonym[sis_user_id]=$ID' -H 'Authorization: Bearer $access_token'");
	
}

// update_avatar - just pass in the ID number of your target as well as 'staff' if you're updating a faculty/staff photo 
function update_avatar ( $ID, $student_or_staff='student' ) {
	global $access_token, $canvas_base_url;
	
	$url=$canvas_base_url."/api/v1/users/sis_user_id:".$ID.".json";
	
	$nine_digit = str_pad($ID, 9, "0", STR_PAD_LEFT);

	if ($student_or_staff == 'staff') {
		$avatar_url = 'https://www.YOURINSTITUTION.edu/path/to/employee/pics/'.$ID;
	}
	else {
		$avatar_url = 'https://www.YOURINSTITUTION.edu/path/to/student/pics/'.$nine_digit.'.jpg';
	}

	system("curl $url -X PUT -F 'user[avatar][url]=$avatar_url' -H 'Authorization: Bearer $access_token'");
	
}
// remove passwords
// In our SIS file, we include passwords, but we don't pass those to Canvas. All our user accounts
// in Canvas have a null password. This is because we use Single Sign On for authentication.
// We have the passwords in the original file for an account provisioning process.
function remove_passwords($term) {
	global $samba_share;
	$file=$samba_share.$term.'_users_stu.csv';
	if (file_exists($file)) {

		$stu_file=file_get_contents($file);
		$student_users=explode("\n",$stu_file);
		$writeit="";
		foreach ($student_users as $line) {
			$line=preg_replace('/"1845(\d{4})"/', '""', $line);
			$line=preg_replace('/"status"(.*)$/','"status"',$line);
			$line=preg_replace('/"active"(.*)$/','"active"',$line);
			$line=preg_replace('/"deleted"(.*)$/','"deleted"',$line);
			if (strlen($line)>10) { $writeit .= $line."\n"; }
		}
		file_put_contents($samba_share.$term.'_users_students.csv', $writeit);
	
	}
}

// remove first line from a file
function remove_first_line ($filename) {
  $file = file($filename);
  $output = $file[0];
  unset($file[0]);
  $return=implode("",$file);
  return $return;
}

// combine stu_fac_enrollments
// We run this process because we send our enrollment file in batch mode. Sending separate faculty/staff/student 
// files in batch mode would create lots of problems
function combine_stu_fac_enrollments ($term) {
	global $samba_share;
	$files=array($samba_share.$term.'_enrollment_faculty.csv',$samba_share.$term.'_enrollment_student.csv');
	$result=$samba_share.$term.'_enrollment_combined.csv';
	if (file_exists($files[0]) && file_exists($files[1])) {
	    $wH = fopen($result, "w+");
	
		// Open first file
	    $fh = fopen($files[0], "r");
	    while(!feof($fh)) {
	       fwrite($wH, fgets($fh));
	    }
	    fclose($fh);
	    unset($fh);

		// Process second file
	    fwrite($wH, remove_first_line($files[1]));
	    

	    fclose($wH);
	    unset($wH);
	}
}

// combine enrollments for student orientation
// We run this process because we send our enrollment file in batch mode. Sending separate faculty/staff/student 
// files in batch mode would create lots of problems
function combine_enrollments_orientation ($terms) {
	global $samba_share;
	$result=$samba_share.'orientation_enrollments.csv';
	$wH = fopen($result, "w+");
	
	//write header row
	fwrite($wH,"\"course_id\",\"user_id\",\"role\",\"section_id\",\"status\"\n");
	
	$i=1;
	foreach($terms as $term) {

	$users_file=$samba_share.$term.'_users_stu.csv';
	if (file_exists($users_file)) {
			$importer = new CsvImporter($users_file,true); 
			$data = $importer->get(); 
			foreach ($data as $user) {
				$userid=$user['user_id'];
				if (strlen($userid)>=5) {
					$line="\"\",\"".$userid."\",\"student\",\"ORIENTATION-STU\",\"active\"\n";
					fwrite($wH,$line);
				}
			}
			$i++;	
		}	
		$users_fac_file=$samba_share.$term.'_users.csv';
		if (file_exists($users_fac_file)) {
			$importer2 = new CsvImporter($users_fac_file,true); 
			$data = $importer2->get(); 
			foreach ($data as $user) {
				$userid=$user['user_id'];
				if (strlen($userid)>=5) {
					$line="\"\",\"".$userid."\",\"student\",\"ORIENTATION-STU\",\"active\"\n";
					fwrite($wH,$line);
				}
			}
			$i++;	
		}		
	}
	// We enroll our orientation teacher here, by hard-coding her into the script.
	fwrite($wH,"\"\",\"165203\",\"teacher\",\"ORIENTATION-STU\",\"active\"\n\n");
    fclose($wH);
    unset($wH);
}

// combine enrollments for faculty training
// We run this process because we send our enrollment file in batch mode. Sending separate faculty/staff/student 
// files in batch mode would create lots of problems
function combine_enrollments_faculty_training ($terms) {
	global $samba_share;
	$result=$samba_share.'faculty_training_enrollments.csv';
	$wH = fopen($result, "w+");
	
	//write header row
	fwrite($wH,"\"course_id\",\"user_id\",\"role\",\"section_id\",\"status\"\n");
	
	$i=1;
	foreach($terms as $term) {

		$users_fac_file=$samba_share.$term.'_users.csv';
		if (file_exists($users_fac_file)) {
			$importer2 = new CsvImporter($users_fac_file,true); 
			$data = $importer2->get(); 
			foreach ($data as $user) {
				$userid=$user['user_id'];
				if (strlen($userid)>=5) {
					$line="\"\",\"".$userid."\",\"student\",\"TRDE201\",\"active\"\n";
					fwrite($wH,$line);
				}
			}
			$i++;	
		}		
	}
	// We enroll our orientation teacher here, by hard-coding her into the script.
	fwrite($wH,"\"\",\"165203\",\"teacher\",\"TRDE201\",\"active\"\n\n");
    fclose($wH);
    unset($wH);
}

// sis_user_provisioning
// We create this file for our provisioning process. We use software called UMRA for account provisioning.
function sis_user_provisioning ($terms) {
	global $samba_share;
	$result='/srv/samba/umra/current_enrolled_students.csv';
	$wH = fopen($result, "w+");
	
	//write header row
	fwrite($wH,"\"id_num\",\"usrdzu\",\"passzu\",\"first_name\",\"last_name\",\"ADDR_LINE_1\",\"middle_initial\"\n");
	
	foreach($terms as $term) {

		$users_file=$samba_share.$term.'_users_stu.csv';
		if (file_exists($users_file)) {
			$importer = new CsvImporter($users_file,true); 
			$data = $importer->get(); 
			foreach ($data as $user) {
				$userid=$user['user_id'];
				$email=$user['alt_email'];
				$sadermail=$user['login_id'];
				$fname=$user['first_name'];
				$lname=$user['last_name'];
				$password=$user['password'];
				$username=explode('@',$sadermail);
				if (strlen($userid)>=3) {
					fwrite($wH,"\"".$userid."\",\"".$username[0]."\",\"".$password."\",\"".$fname."\",\"".$lname."\",\"".$email."\",\"\"\n");
				}
			}
		}		
	}
    fclose($wH);
    unset($wH);
}

// sis_user_provisioning_facstaff
// We create this file for our provisioning process. We use software called UMRA for account provisioning.
function sis_user_provisioning_facstaff ($terms) {
	global $samba_share;
	$result='/srv/samba/umra/current_enrolled_facstaff.csv';
	$wH = fopen($result, "w+");
	
	//write header row
	fwrite($wH,"\"id_num\",\"usrdzu\",\"first_name\",\"last_name\"\n");
	
	foreach($terms as $term) {

		$users_file=$samba_share.$term.'_users.csv';
		if (file_exists($users_file)) {
			$importer = new CsvImporter($users_file,true); 
			$data = $importer->get(); 
			foreach ($data as $user) {
				$userid=$user['user_id'];
				$email=$user['login_id'];
				$fname=$user['first_name'];
				$lname=$user['last_name'];
				$username=explode('@',$email);
				if (strlen($userid)>=3) {
					fwrite($wH,"\"".$userid."\",\"".$username[0]."\",\"".$fname."\",\"".$lname."\"\n");
				}
			}
		}		
	}
    fclose($wH);
    unset($wH);
}

// find_user
// This takes an SIS ID for a user and returns the Canvas ID number for that user
function find_user ( $ID ){
	global $access_token, $canvas_base_url;
	
	$url=$canvas_base_url."/api/v1/accounts/1/users.json?access_token=".$access_token;
	
	$data=array('search_term'=>$ID);
	
	// Get cURL resource
	$curl = curl_init();
	// Set some options - we are passing in a useragent too here
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_CUSTOMREQUEST => 'GET',
	    CURLOPT_URL => $url,
	    CURLOPT_POSTFIELDS => $data,
	    CURLOPT_USERAGENT => 'Matt Loves cURLing Things'
	));
	// Send the request & save response to $resp
	$resp = json_decode(curl_exec($curl));
	// Close request to clear up some resources
	curl_close($curl);
	
	$canvasID=$resp[0]->id;
	
	return $canvasID;
}

// list enrollments
// This lists all the students enrolled in a course. Pass the Canvas Course ID to this function.
function list_enrollments ( $CourseID ){
	global $access_token, $canvas_base_url;
	
	//get it ready for later
	$enrolled=array();
	
	$url=$canvas_base_url."/api/v1/courses/".$CourseID."/enrollments?per_page=100&access_token=".$access_token;
	
	// Get cURL resource
	$curl = curl_init();
	// Set some options - we are passing in a useragent too here
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_CUSTOMREQUEST => 'GET',
	    CURLOPT_URL => $url,
	    CURLOPT_USERAGENT => 'Matt Loves cURLing Things'
	));
	// Send the request & save response to $resp
	$resp = json_decode(curl_exec($curl));
	
	//print_r($resp);
	
	// Close request to clear up some resources
	curl_close($curl);

	$counter=0;
	foreach ($resp as $enrollment) {
		if ($enrollment->type=="StudentEnrollment" && $enrollment->user->name != "Test Student") {
			$enrolled[$enrollment->user->sis_user_id]=array('name'=>$enrollment->user->name,'id'=>$enrollment->user->sis_user_id,'participations'=>0);
		}
		$counter++;
	}	
	return $enrolled;
}

// count_participations
// We use this for a weekly email for Academic Related Activities. 
// It simply counts how many times a user has participated in a course in a given time range.
function count_participations ( $CourseID, $enrolled, $start_date, $end_date ){
	global $access_token, $canvas_base_url;
	
	//echo "Total Enrollments for course: ".count($enrolled);
	
	$counter=0;
	foreach ($enrolled as $student) {
		
		$url = $canvas_base_url."/api/v1/courses/".$CourseID."/analytics/users/sis_user_id:".$student['id']."/activity";
		
		// Get cURL resource
		$curl = curl_init();
		// Set some options - we are passing in a useragent too here
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_CUSTOMREQUEST => 'GET',
		    CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $access_token), 
		    CURLOPT_URL => $url,
		    CURLOPT_USERAGENT => 'Matt Loves cURLing Things'
		));
		// Send the request & save response to $resp
		$resp = json_decode(curl_exec($curl));
		// Close request to clear up some resources
		curl_close($curl);
		
		foreach ($resp->participations as $singleparicipation) {
			$participation_time=strtotime($singleparicipation->created_at);
			
			//only count participations in the specified date range
			if ($participation_time >= strtotime($start_date) && $participation_time <= strtotime($end_date)) {
				$enrolled[$student['id']]['participations']=$enrolled[$student['id']]['participations']+1;
				$counter++;
			}	
		}
	}
	return $enrolled;

}


// enroll_user
function enroll_user ( $ID, $role, $course_id, $section_id=null ){
	global $access_token, $canvas_base_url;
	
	if ($section_id != null) {
		$section_stuff = "-F 'enrollment[course_section_id]=$section_id' ";
	}
	else {
		$section_stuff=null;
	}
	
	$url=$canvas_base_url."/api/v1/courses/sis_course_id:".$course_id."/enrollments";
	//acceptable roles are "StudentEnrollment" "TeacherEnrollment" "TaEnrollment" "ObserverEnrollment" and "DesignerEnrollment"
	
    system("curl -H 'Authorization: Bearer $access_token' -X POST -F 'enrollment[user_id]=$ID' -F 'enrollment[type]=$role'$section_stuff -F 'enrollment[enrollment_state]=active' -F 'enrollment[notify]=false' $url");

}

// delete_course
function delete_course ( $course_id ) {
	global $access_token, $canvas_base_url;
	
	$url=$canvas_base_url."/api/v1/courses/sis_course_id:".$course_id;
	system("curl $url -X DELETE -F 'event=delete' -H 'Authorization: Bearer $access_token'");
}

/*
 * 
 * CSV Importer Yo!
 * 
 */

class CsvImporter 
{ 
    private $fp; 
    private $parse_header; 
    private $header; 
    private $delimiter; 
    private $length; 
    //-------------------------------------------------------------------- 
    function __construct($file_name, $parse_header=false, $delimiter=",", $length=8000) 
    { 
        $this->fp = fopen($file_name, "r"); 
        $this->parse_header = $parse_header; 
        $this->delimiter = $delimiter; 
        $this->length = $length; 
        $this->lines = $lines; 

        if ($this->parse_header) 
        { 
           $this->header = fgetcsv($this->fp, $this->length, $this->delimiter); 
        } 

    } 
    //-------------------------------------------------------------------- 
    function __destruct() 
    { 
        if ($this->fp) 
        { 
            fclose($this->fp); 
        } 
    } 
    //-------------------------------------------------------------------- 
    function get($max_lines=0) 
    { 
        //if $max_lines is set to 0, then get all the data 

        $data = array(); 

        if ($max_lines > 0) 
            $line_count = 0; 
        else 
            $line_count = -1; // so loop limit is ignored 

        while ($line_count < $max_lines && ($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE) 
        { 
            if ($this->parse_header) 
            { 
                foreach ($this->header as $i => $heading_i) 
                { 
                    $row_new[$heading_i] = $row[$i]; 
                } 
                $data[] = $row_new; 
            } 
            else 
            { 
                $data[] = $row; 
            } 

            if ($max_lines > 0) 
                $line_count++; 
        } 
        return $data; 
    } 
    //-------------------------------------------------------------------- 

} 

?>