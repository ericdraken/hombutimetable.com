<?php
/**
 *  Collect and return teacher stats

 e.g. http://hombutimetable.com/api/stats.php?app=1.5.131219&fltr=6ffb73e3&gzip=1&json=0&lang=en&ref=4177694765
 */

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/includes/HombuDBInterface.php');
require_once(__DIR__ . '/includes/HombuLogger.php');
require_once(__DIR__ . '/includes/ZendCache.php');

// GeoIP interface
require_once(__DIR__ . '/includes/ip2Location.php');

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

// There were previously included as querystring params
$device_ID = @$_GET["ref"];
$filters = @$_GET["fltr"];
$app_version = @$_GET["app"];
$machine_name = @$_GET["dev"];
$os_version = @$_GET["os"];
$lang = @$_GET["lang"];
$play_count = @$_GET["vpc"];

$country = null;
$city = null;
$longitude = null;
$latitude = null;
$ip_address = null;

// Encrypted statistics parameter
$aesParamString = @$_GET["s"];
$aesKey = "xxxxxxxx";

// Output hombu stats
$show = @$_GET["show"];
$period = @$_GET["period"];
$fmt = @$_GET["fmt"];
$force_compression = @$_GET["gzip"];

////////////////

try {

    // Load the DB connection
    $hl = new HombuLogger();
    $hdbi = new HombuDBInterface(array(), $hl);

    if(isset($show) && strlen($show) > 1) {
        // Show stats
        $results = array();

        // Time period
        if(!isset($period)) {
            $period = 14;
        } else {
            $period = intval($period);
            if($period < 1 || $period > 365) {
                $period = 14;
            }
        }

        switch($show) {
            case "unique":
            case "counts": {
                $sql = 'SELECT COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY';
                $results = $hdbi->accessDB()->get_row($sql, ARRAY_A);
                break;
            }

            case "langs": {
                $sql = 'SELECT `lang`, COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY GROUP BY `lang` ORDER BY count DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "ver": {
                // Exclude the simulator x86_64
                $sql = 'SELECT `app_version`, COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY AND `machine_name` NOT LIKE \'x%\' GROUP BY `app_version` ORDER BY `app_version` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "ver2": {
                // Exclude the simulator x86_64
                $sql = 'SELECT `app_version`, `os_version`, COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY AND `machine_name` NOT LIKE \'x%\' GROUP BY `app_version`, `os_version` ORDER BY `app_version` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "dev": {
                $sql = 'SELECT `machine_name`, COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY GROUP BY `machine_name` ORDER BY count DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "filters":
                $sql = 'SELECT `filters`, `app_version`, COUNT(*) as count FROM `ht_analytics` WHERE `app_version` NOT LIKE \'1%\' AND DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY GROUP BY `filters` ORDER BY `app_version` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;

            case "countries": {
                $sql = 'SELECT `country`, COUNT(*) as count FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY GROUP BY `country` ORDER BY count DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "geoip": {
                $sql = 'SELECT `latitude`, `longitude` FROM `ht_analytics` WHERE `latitude` <> 0 AND `longitude` <> 0 AND DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_N);
                break;
            }

            case "raw": {
                $sql = 'SELECT * FROM `ht_analytics` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY ORDER BY `ht_analytics`.`updated_timestamp` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "joined": {
                $sql = 'SELECT * FROM `ht_analytics` INNER JOIN `ht_analytics_deltas` ON `ht_analytics`.`device_ID` = `ht_analytics_deltas`.`device_ID` WHERE DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY ORDER BY `ht_analytics`.`updated_timestamp` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }

            case "stats": {
                $sql = 'SELECT `ht_analytics`.`device_ID`, `app_version`, `country`, `city`, `lang`, `access`, `play_count`, `access_rate` / 3600.0 AS `rate` FROM `ht_analytics` INNER JOIN `ht_analytics_deltas` ON `ht_analytics`.`device_ID` = `ht_analytics_deltas`.`device_ID` WHERE '.time().'<>0 AND `d9` > 0 AND DATE(`updated_timestamp`) > CURDATE() - INTERVAL '.$period.' DAY ORDER BY `ht_analytics`.`updated_timestamp` DESC';
                $results = $hdbi->accessDB()->get_results($sql, ARRAY_A);
                break;
            }
        }

        // Set the output encoding
        if(!isset($fmt)) {
            $fmt = 1;
        } else {
            $fmt = intval($fmt);
            if($fmt < 0 || $fmt > 3) {
                $fmt = 1;
            }
        }

        // Render the results
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        if($force_compression == 1) {
            ob_start();
            ob_start('ob_gzhandler');
        }

        if($fmt == 1) {
            // JSON
            header('Content-type: application/json');
            echo json_encode($results);

        } else if($fmt == 2 && is_array($results) && count($results) > 0) {
            // CVS
            header('Content-type: text/plain');
            $imploder = function($a) {
                echo implode(", ", $a) . PHP_EOL;
            };
            array_map($imploder, $results);

        } else if ($fmt == 0 && is_array($results)) {
            // Array dump
            header('Content-type: text/plain');
            echo print_r($results, true);

        } else {
            // HTML table of results
            header('Content-type: text/html');
            $hdbi->accessDB()->debug(true);
        }

        if($force_compression == 1) {
            ob_end_flush();
            header('Content-Encoding: gzip');
            header('Content-Length: '.ob_get_length());
            ob_end_flush();
        }

    } else {
        // Set stats

        // Decrypt the stats string if present
        if(isset($aesParamString)) {
            $aesParamStringBase64 = urldecode($aesParamString);
            $ciphertext = base64_decode($aesParamStringBase64);
            $decryptedString = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $aesKey, $ciphertext , MCRYPT_MODE_ECB); // no iv in ECB

            // Extract the variables
            parse_str($decryptedString, $stats);

            // Set them here
            $device_ID = @$stats["ref"];
            $filters = @$stats["fltr"];
            $app_version = @$stats["app"];
            $machine_name = @$stats["dev"];
            $os_version = @$stats["os"];
            $lang = @$stats["lang"];
            $play_count = @$stats["vpc"];
        }

        // Attempt to get the client IP address
        $ip_address = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER["REMOTE_ADDR"];

        // Do server-side GeoIP lookups here
        if($ip_address) {
            $ipLite = new ip2location_lite;
            $ipLite->setKey('xxxxx');

            // Get errors and locations
            $locations = $ipLite->getCity($ip_address);
            $errors = $ipLite->getError();

            if (empty($errors) && !empty($locations) && is_array($locations)) {
                $country = @$locations["countryName"];
                $city = @$locations["cityName"];
                $longitude = @$locations["longitude"];
                $latitude = @$locations["latitude"];

                // Clean up special characters
                $country = mysql_real_escape_string($country);
                $city = mysql_real_escape_string($city);
            }
        }

        // Add analytics
        if(isset($device_ID) && isset($filters)) {

            // Save the filters and various metrics for analytics
            if(true == $hdbi->addAnalytics($device_ID, $filters, $app_version, $lang, $play_count, $machine_name, $os_version, $country, $city, $longitude, $latitude, $ip_address)) {
                echo "OK";
            } else {
                echo "NG";
            }
        } else {
            echo "0";
        }
    }
} catch(Exception $e){

    echo $e . "<br/>";
    echo "<pre>"; print_r($e->getTrace());
    echo "</pre>";

    error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, "xxxxx", "From: statserrors@hombutimetable.com");
}

?>
