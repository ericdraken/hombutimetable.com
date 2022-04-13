<?php
/**
 * Connect to the YouTube API v3 and format the JSON for the App
 * Eric Draken, 2015
 *
 * 2015.07.02 - Start
 */


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
////////////////////////////////////////////////////// 

require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/YouTubeAPI.php');
require_once(__DIR__ . '/includes/ZendCache.php');

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

// Video info
$json = @$_GET["json"];
$version = @$_GET["ver"];
$video = @$_GET["video"];
$force_compression = @$_GET["gzip"];
$errors = @$_GET["errors"];

// Clear all the video cache files and exit this file
$purge = @$_GET["purge"];
if(!isset($purge)) {
    $purge = 0;
} else {
    if(intval($purge) == 1) {
        YouTubeAPI::purgeCache();
        exit(0);
    }
}

// Check for a video string
if(!isset($video)) {
    die("No video set");
} else {
    // Sanitize video
    $video = preg_replace('/[^A-Za-z0-9_-]/', '', $video);
}

// Set the version. Default is ver 1 (HombuApp, not GData)
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

// Begin data processing
try {
    $hl = new HombuLogger();

    // Exceptions
    $exceptions = array();

    // Load the video details
    $ytApi = new YouTubeAPI($hl);
    $videoDetailsJSON = $ytApi->getVideoDetailsJSON($version, $video);

    if($json == 1) {
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');

        if($force_compression == 1) {
            ob_start();
            ob_start('ob_gzhandler');
        }

        echo $videoDetailsJSON;

        if($force_compression == 1) {
            ob_end_flush();
            header('Content-Encoding: gzip');
            header('Content-Length: '.ob_get_length());
            ob_end_flush();
        }
    } else {
        $hl->debugArray(json_decode($videoDetailsJSON));
    }
} catch(Exception $e){

    // Show errors
    if(isset($errors)) {
        echo $e . "<br/>";

        echo "<pre>"; print_r($e->getTrace());
        echo "</pre>";
    }

    error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxxx", "From: geterrors@hombutimetable.com");
}

?>
