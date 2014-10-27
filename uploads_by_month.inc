<?php

 /* 
 * Check the date to see what terms we be sending. 
 * No sense sending everything if the data isn't current.
 * Our terms are May Minimester, Summer 1, Summer 2, Fall, and Spring.
 * All of our CSVs are prefixed with the term identifier (ie: sp_)
 * 
 */

	$month=date('n');
	
	switch ($month) {
		case 1:
			$files = array('sp');
			break;
		case 2:
			$files = array('sp');
			break;
		case 3:
			$files = array('sp');
			break;
		case 4:
			$files = array('ma','s1','s2','sp');
			break;
		case 5:
			$files = array('ma','s1','s2');
			break;
		case 6:
			$files = array('s1','s2');
			break;
		case 7:
			$files = array('fa','s1','s2');
			break;
		case 8:
			$files = array('fa');
			break;
		case 9:
			$files = array('fa');
			break;
		case 10:
			$files = array('fa');
			break;
		case 11:
			$files = array('fa','sp');
			break;
		case 12:
			$files = array('fa','sp');
			break;
	}
?>