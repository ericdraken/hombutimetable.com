<?php
/**
 * Sync the Hombu schedule to the database
 * Eric Draken, 2012
 */


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');	
////////////////////////////////////////////////////// 
 
require_once(__DIR__ . '/includes/HombuSyncController.php');
require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/testing/HombuValidatorsTest.php');
 
// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); } 

$hl = new HombuLogger();

$start = @$_GET["start"];
$end = @$_GET["end"];

if(!isset($start)) {
	$start = 0;
}

if(!isset($end)) {
	$end = $start;
}

// Clear all the temp cache files and exit this file
$purge = @$_GET["purge"];
if(!isset($purge)) {
    $purge = 0;
} else {
    if(intval($purge) == 1) {

        // Load the DB connection
        $hdbi = new HombuDBInterface(array(), $hl);

        // Purge the DB cache
        $hdbi->purgeDBCache();
        $hdbi = null;
        exit(0);
    }
}


// Upper limit for day searching
if($end > 60) {
    $end = 60;
}

try {

	// Increase the script time
	if(function_exists("set_time_limit")) {

		$hl->info("Adding 240 seconds more script time...");
		set_time_limit(240);
	}	

	$hsc = new HombuSyncController($hl);
	$hsc->syncDays($start,$end,10);	// Delay of 10 seconds between site grabs
	
	// Testing
	//$tester = new HombuValidatorsTest($hl);
	//$tester->test();

} catch(Exception $e){
	
	echo $e . "<br/>";
	echo "<pre>"; print_r($e->getTrace());
	echo "</pre>";
	
	error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxxx", "From: syncerrors@hombutimetable.com");
}

?>
