<?php
/**
 * Sync the weather near Hombu
 * Eric Draken, 2014
 */


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/HombuWeather.php');

$sync = @$_GET["sync"];
if(!isset($sync)) {
    die("Cannot sync the weather. Wrong parameter supplied?");
}
$sync = intval($sync);


// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

$hl = new HombuLogger();

try {

    // Increase the script time
    if(function_exists("set_time_limit")) {

        $hl->info("Adding 240 seconds more script time...");
        set_time_limit(240);
    }

    $hw = new HombuWeather($hl);

    // e.g. 1,3
    if($sync & (1 << 0)) {
        $hourly = $hw->getHombuHourlyWeather();
        echo "<pre>"; print_r($hourly); echo "</pre>";
    }

    // e.g. 2,3
    if($sync & (1 << 1)) {
        $textual = $hw->getHombuWeatherForecasts();
        echo "<pre>"; print_r($textual); echo "</pre>";
    }

} catch(Exception $e){

    echo $e . "<br/>";
    echo "<pre>"; print_r($e->getTrace());
    echo "</pre>";

    error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxxx", "From: syncerrors@hombutimetable.com");
}

?>
