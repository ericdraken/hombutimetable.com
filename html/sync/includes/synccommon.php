<?php

////// Global Settings ///////////////////////////////
require_once('define_root.php');
require_once(__ROOT__.'/global_settings.php');	
//////////////////////////////////////////////////////

require_once('curl.php');
require_once('printHelpers.php');
require_once('hombuCalendarRendererV5.php');


ignore_user_abort(true);

/*

starter: 0 - today, 1 = tomorrow, etc.

*/

function syncTheseDays($starter = 0, $finisher = 0) {

	@ob_implicit_flush(true);

    $cc = new cURL(FALSE);	// Don't use cookies
    $starter--; // For previous day access
    $calendar_json = $cc->get("http://hombutimetable.com/api/get.php?start={$starter}&end={$finisher}&json=1&ver=10");
    $starter++;

    $calendar_array = null;
    if(isset($calendar_json) && strlen($calendar_json) > 100) {
        $calendar_array = json_decode($calendar_json);
    }

    // Sanity check
    if(!isset($calendar_array) /*|| count($calendar_array) != ($finisher - $starter + 2)*/) {
        echo "API access failed. Got <pre>" . print_r($calendar_array, 1) . ": " . count($calendar_array) . " vs " . ($finisher - $starter + 2) . "</pre>";
        die();
    }

   // Prepare a calendar renderer
    $cal = new HombuCalRendererV5();

	// Render these days
	for($i = $starter+1; $i <= $finisher; $i++){

		$new_date = date(strtotime("+$i days"));
		br();
		echo_c( "<strong>" . $i . ") " . date("D Y/m/d",strtotime("+$i days")) . "</strong>");
		br();

		// Cache this day to disk as a rendered calendar file
		$cal->cacheRenderedCalendar($calendar_array[$i], "e", false);
        $cal->cacheRenderedCalendar($calendar_array[$i], "j", false);
	}

	// Clean up - moveOldCache, the rollover time is 9 pm, or 21:00.
	$cal->moveOldCache(24);

    // Rerender previous day as a simple calendar
	$cal->cacheRenderedCalendar($calendar_array[0], "e", true);
	$cal->cacheRenderedCalendar($calendar_array[0], "j", true);

	br();
	echo_c( "Clearing old cached days." );
	
	// Wipe the calendar cache in the shared cache directory
	destroy(HOMBU_CACHE_PATH . "/frontend/");
	
	br();
	echo_c( "FINISHED!" );
}

// REF: http://forums.codewalkers.com/php-coding-7/delete-all-files-in-directory-714057.html
function destroy($dir) {
    $mydir = opendir($dir);
    while(false !== ($file = readdir($mydir))) {
        if($file != "." && $file != "..") {
            chmod($dir.$file, 0777);
            if(is_dir($dir.$file)) {
                chdir('.');
                destroy($dir.$file.'/');
                rmdir($dir.$file) or DIE("couldn't delete $dir$file<br />");
            }
            else
                unlink($dir.$file) or DIE("couldn't delete $dir$file<br />");
        }
    }
    closedir($mydir);
}

?>