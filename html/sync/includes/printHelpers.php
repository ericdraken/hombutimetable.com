<?php

// Detect cron jobs and only output changes
if(!defined("IS_CRON_JOB")) {
	//define("IS_CRON_JOB", (int)(!isset($_SERVER) || empty($_SERVER['DOCUMENT_ROOT'])) );
	
	// Output needs to be sent to the console/browser
	define("IS_CRON_JOB", 0 );
}

function echo_c( $str ) {
	if((int)constant("IS_CRON_JOB") != 1) { echo $str . "\n<br>\n"; }
}

function echo_c_red( $str ) {
	if((int)constant("IS_CRON_JOB") != 1) { echo "<font color=\"red\">" . $str . "</font>\n<br>\n"; }
}

function echo_c_nb( $str ) {
	if((int)constant("IS_CRON_JOB") != 1) { echo $str . " "; }
}

function print_r_c( $obj ) {
	if((int)constant("IS_CRON_JOB") != 1) { echo "\n<pre>\n"; print_r( $obj ); echo "\n</pre>\n"; }
}

function br() {
	if((int)constant("IS_CRON_JOB") != 1) {	echo "\n<br>\n"; }
}

?>