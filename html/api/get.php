<?php
/**
 * Get the Hombu schedule from the database
 * Eric Draken, 2012
 * 
 * 2012.08.05 - Added shihan filter with the ?shi=name param
 */


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__. '/global_settings.php');
////////////////////////////////////////////////////// 
 
require_once(__DIR__ . '/includes/HombuValidators.php'); 
require_once(__DIR__ . '/includes/HombuDBInterface.php');
require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/HombuWeather.php');
require_once(__DIR__ . '/includes/HombuNews.php');
require_once(__DIR__ . '/includes/HombuOverrides.php');
require_once(__DIR__ . '/includes/ProtocolPostProcessor.php');
require_once(__DIR__ . '/includes/ZendCache.php');
 
// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); } 

$hl = new HombuLogger();

// Schedule request information
$start = @$_GET["start"];
$end = @$_GET["end"];
//$shi = @$_GET["shi"];
$json = @$_GET["json"];
$version = @$_GET["ver"];
$force_compression = @$_GET["gzip"];
$purge = @$_GET["purge"];

if(isset($start)) {
	$start = intval($start);

    // Set the version
    if(!isset($version)) {
        $version = 1;
    } else {
        $version = intval($version);
    }

	// Set the end date if not present
	if(!isset($end)) {
		$end = $start;
	} else {
		$end = intval($end);
	}

    // Upper limit for day searching
    if($end > 60) {
        $end = 60;
    }

	// Filter the shihan filter
    //if(!isset($shi)) {
    //    $shi = "";
    //} else {
    //    $shi = preg_replace("/[^a-z]/", '', strtolower($shi));
    //}

    // Set the encoding
    if(!isset($json)) {
        $json = 1;
    } else {
        $json = intval($json);
        if($json != 0 && $json != 1) {
            $json = 1;
        }
    }

    if(!isset($purge)) {
        $purge = 0;
    } else {
        $purge = intval($purge);
    }

	//$hl->debug("Start: {$start}, End: {$end}, Shi: {$shi}");
	//die();

	try {

		// Limitter
		if(abs($start - $end) >= 60) {
			die("Too many days are requested: requested from {$start} to {$end}");
		}

		// Load the DB connection
		$hdbi = new HombuDBInterface(array(), $hl);

        // Purge the DB cache
        if($purge == 1) {
            $hdbi->purgeDBCache();
        }

		// Loop through the dates and return the day objects
		$results = array();
		$today_date = new DateTime('today');

        // Exceptions
        $exceptions = array();

        // Get the weather objects
        $weatherObjectsArray = array(array(), array());
        if($version >= 4) {
            $hw = new HombuWeather($hl);

            // Get the hourly weather
            try {
                // This should already be set by cron job activity
                $hourlyWeatherObjectsArray = $hw->getHombuHourlyWeatherCache();
                if(!isset($hourlyWeatherObjectsArray)) {
                    // Insurance - try to call the weather API if no cache was found
                    $hourlyWeatherObjectsArray = $hw->getHombuHourlyWeather();
                }
                $weatherObjectsArray[0] = $hourlyWeatherObjectsArray;
            } catch(Exception $e) {
                $exceptions[] = (string)$e;
            }

            // Get the textual weather
            try {
                // This should already be set by cron activity
                $textualWeatherObjectsArray = $hw->getHombuWeatherForecastsCache();
                if(!isset($textualWeatherObjectsArray)) {
                    // Insurance - try to call the weather API if no cache was found
                    $textualWeatherObjectsArray = $hw->getHombuWeatherForecasts();
                }
                $weatherObjectsArray[1] = $textualWeatherObjectsArray;

            } catch(Exception $e) {
                $exceptions[] = (string)$e;
            }
        }

        // Load the news object
        $hombuNews = null;
        if($version >= 5) {
            try {
                $hombuNews = new HombuNews($hl);
            } catch(Exception $e) {
                $exceptions[] = (string)$e;
            }
        }

        // Load the schedule override objects in an array
        $hombuOverridesArray = array();
        if($version >= 6) {
            try {
                $hombuOverrides = new HombuOverrides($hl);
                $hombuOverridesArray = $hombuOverrides->getHombuOverrides();
            } catch(Exception $e) {
                $exceptions[] = (string)$e;
            }
        }

        ///////////////////////////

		$tmp = null;
		for($i = $start; $i <= $end; $i++) {
			$new_date = date("Y-m-d", strtotime("+$i days"));
			$tmp = $hdbi->getHombuDayEvents($new_date, "");

            // Prevent null entries
            // Days with no events are okay, however, as they may be closed days etc.
            if(!isset($tmp)) {
                continue;
            }

            switch($version) {
                case 11:
                case 10:
                default:
                    // 2015-09-10
                    $tmp = ProtocolPostProcessor::formatForProtocolV10($tmp, $weatherObjectsArray, $hombuNews, $hombuOverridesArray);
                    break;

                case 9:
                    // 2014-12-02
                    // Added outdated warning on 2015.09.23 for the TODAY day
                    $showWarning = $i === 0 ? TRUE : FALSE;
                    $tmp = ProtocolPostProcessor::formatForProtocolV9($tmp, $weatherObjectsArray, $hombuNews, $hombuOverridesArray, $showWarning);
                    break;

                case 8:
                    // 2014-12-02
                    // Added outdated warning on 2015.09.12 for the TODAY day
                    // Crippled on 2015.09.23 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV8($tmp, $weatherObjectsArray, $hombuNews, $hombuOverridesArray);
                    break;

                case 7:
                    // 2014-10-29
                    // Crippled on 2015.09.12 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV7($tmp, $weatherObjectsArray, $hombuNews, $hombuOverridesArray);
                    break;

                case 6:
                    // 2014-06-11
                    // Crippled on 2015.09.12 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV6($tmp, $weatherObjectsArray, $hombuNews);
                    break;

                case 5:
                    // 2013-12-19
                    // Crippled on 2015.01.05 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV5($tmp, $weatherObjectsArray, $hombuNews);
                    break;

                case 4:
                    // 2013-11-07
                    // Crippled on 2015.01.05 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV4($tmp, $weatherObjectsArray);
                    break;

                case 3:
                    // 2013-10-26
                    // Crippled on 2015.01.05 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV3($tmp);
                    break;

                case 2:
                    // 2013-10-25
                    // Crippled on 2015.01.05 to display unknown teachers
                    $tmp = ProtocolPostProcessor::formatForProtocolV2($tmp);
                    break;

                case 1:
                    break;
            }

			// Prevent null entries
			// Days with no events are okay, however, as they may be closed days etc.
			if(isset($tmp)/* && isset($tmp->events)*/) {

			    // Display exception information
				if(count($exceptions) > 0) {
				    $tmp->exceptions = $exceptions;
				}

				$results[] = $tmp;
			}
		}

        // Cache the results for short-term use
        // $results

		if($json == 1) {
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-type: application/json');

            if($force_compression == 1) {
                ob_start();
                ob_start('ob_gzhandler');
            }

			echo json_encode($results);

            if($force_compression == 1) {
                ob_end_flush();
                header('Content-Encoding: gzip');
                header('Content-Length: '.ob_get_length());
                ob_end_flush();
            }
		} else {
			$hl->debugArray($results);	
		}
	} catch(Exception $e){
		
		echo $e . "<br/>";
		echo "<pre>"; print_r($e->getTrace());
		echo "</pre>";
		
		error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxx", "From: geterrors@hombutimetable.com");
	}
}

?>
