<?php
/*
 * sis_import_users.php
 * It's pretty simple. All this file does is import users from our SIS
 * 
 */ 
 
require 'functions.php';
include 'uploads_by_month.inc';

$url=$canvas_base_url."/api/v1/accounts/1/sis_imports.json?import_type=instructure_csv";

foreach ($files as $file) {

	/*
	 * First, let's bring in faculty users
	 * The CSV file is automagically generated by a job on the EX Database server
	 */

	$faculty_file=$samba_share.$file.'_users.csv';
	if (file_fresh($faculty_file)) {
		echo "\n\nDebug Data:\n=======================================================================================================\nFile: ".$file."\nFaculty File: ".$faculty_file."\n\n";
	
		// now, let's send Canvas the faculty users file for the term we're uploading	
		echo "\n\nNow uploading: Faculty Users ".$file."_users.csv\n=======================================================================================================\nURL: ".$url."\n\n";	
		system("curl -F attachment=@$faculty_file -H 'Authorization: Bearer $access_token' $url");
	}
	/*
	 * Then, let's import students
	 * The CSV file is automagically generated by a job on the EX Database server
	 */  
	
	// First, lets copy the users file sans passwords
	remove_passwords($file);
	
	// We'll use our newly created students file
	$student_file=$samba_share.$file.'_users_students.csv';
	
	// Doesn't make much sense to check freshness on the file we just created. Let's look at the one that came from the SIS
	$sis_student_file=$samba_share.$file.'_users_stu.csv';

	if (file_fresh($sis_student_file)) {
		echo "\n\nDebug Data:\n=======================================================================================================\nFile: ".$file."\nStudent File: ".$student_file."\n\n";
		
		// now, let's send Canvas the student users file for the term we're uploading	
		echo "\n\nNow uploading: Student Users ".$file."_users_students.csv\n=======================================================================================================\nURL: ".$url."\n\n";	
		system("curl -F attachment=@$student_file -H 'Authorization: Bearer $access_token' $url");
	}
	
}

/*
 * Finally, let's bring in users that have been newly created by UMRA
 * This step is necessary since the new users are still listed with a non-UMHB email address in EX until the first class day. If we create the user in Canvas with the email
 * from EX, they won't be able to log-in, since Single Sign On uses Sadermail as the log-in ID for students. 
 * 
 */

$newly_created_file=$samba_share.'newly_created_students.csv';

	if (file_fresh($newly_created_file)) {
		echo "\n\nDebug Data:\n=======================================================================================================\nFile: ".$file."\nStudent File: ".$student_file."\n\n";
		
		// now, let's send Canvas the student users file for the term we're uploading	
		echo "\n\nNow uploading: Newly Created Student Users newly_created_students.csv\n=======================================================================================================\nURL: ".$url."\n\n";	
		system("curl -F attachment=@$newly_created_file -H 'Authorization: Bearer $access_token' $url");
	}

?>