<?php
/**
 * Get the Hombu schedule from the database
 * Eric Draken, 2012
 *
 * 2012.08.05 - Added shihan filter with the ?shi=name param
 */


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
////////////////////////////////////////////////////// 

require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/HombuVideos.php');
require_once(__DIR__ . '/includes/ZendCache.php');

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

$hl = new HombuLogger();

// Schedule request information
$json = @$_GET["json"];
$version = @$_GET["ver"];
$force_compression = @$_GET["gzip"];

// Set the version
if(!isset($version)) {
    $version = 1;
} else {
    $version = intval($version);
}

// Set the encoding
if(!isset($json)) {
    $json = 1;
} else {
    $json = intval($json);
    if($json != 0 && $json != 1) {
        $json = 1;
    }
}

try {

    // Exceptions
    $exceptions = array();

    // Load the video objects
    $videoObjectsArray = null;
    if($version >= 1) {
        try {
            $hv = new HombuVideos($hl);
            $videoObjectsArray = $hv->getHombuVideos($version);
        } catch(Exception $e) {
            $exceptions[] = (string)$e;
        }
    }

    if($json == 1) {
//            header('Cache-Control: no-cache, must-revalidate');
//            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');

        if($force_compression == 1) {
            ob_start();
            ob_start('ob_gzhandler');
        }

        echo json_encode($videoObjectsArray);

        if($force_compression == 1) {
            ob_end_flush();
            header('Content-Encoding: gzip');
            header('Content-Length: '.ob_get_length());
            ob_end_flush();
        }
    } else {
        $hl->debugArray($videoObjectsArray);
    }
} catch(Exception $e){

    echo $e . "<br/>";
    echo "<pre>"; print_r($e->getTrace());
    echo "</pre>";

    error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxxx", "From: geterrors@hombutimetable.com");
}

?>
