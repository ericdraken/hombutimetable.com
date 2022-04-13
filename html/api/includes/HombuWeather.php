<?php
// Eric Draken - Get the useful info from Weather Underground

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');
require_once(__DIR__ . '/ZendCache.php');
require_once('curl/curl.php');

/*
/// TEST
$hw = new HombuWeather(new HombuLogger());
$response = $hw->getHombuWeather();
echo print_r($response, 1) . PHP_EOL;
 */


class HombuWeatherException extends HombuException {}

/**
 * @throws HombuWeatherException
 */
class HombuWeather {
    private $logger, $curl, $cache, $longCache, $backupCache, $backLimit;
    protected $api_url, $response;
    private $DEBUG;

    public function __construct(HombuLogger $logger) {

        /////// Debug /////////
        $this->DEBUG = FALSE;
        ///////////////////////

        // Logging
        $this->logger = $logger;

        // Set the params for the weather API
        $this->curl = new cURL(FALSE);	// Don't use cookies

        if(defined("DEVELOPMENT_ENVIRONMENT")) {
            // DEVELOPMENT KEY
            $api_key = "xxxxx";
        } else {
            // PRODUCTION KEY
            $api_key = "xxxxx";
        }

        $hombu_geoloc = "35.699181,139.714304";
        $this->hourly_api_url = "https://api.wunderground.com/api/{$api_key}/hourly10day/bestfct:1/pws:1/q/{$hombu_geoloc}.json";
        // e.g. https://api.wunderground.com/api/xxxxx/hourly10day/bestfct:1/pws:1/q/35.699181,139.714304.json

        $this->forecast_api_url_jp = "https://api.wunderground.com/api/{$api_key}/forecast/lang:JP/bestfct:1/pws:1/q/{$hombu_geoloc}.json";
        $this->forecast_api_url_en = "https://api.wunderground.com/api/{$api_key}/forecast/lang:EN/bestfct:1/pws:1/q/{$hombu_geoloc}.json";
        // e.g. https://api.wunderground.com/api/xxxxx/forecast/lang:JP/bestfct:1/pws:1/q/35.699181,139.714304.json

        // Set the params for the caching - 6 minutes
        // Wunderground free account: 500 calls/day, 10 calls/minute
        // Every 3 or so minutes a weather call can be made
        $this->cache = new ZendCache(60 * 6, true, "wunderground/hourlyCache");

        // Keep a long cache so the whole day has hourly weather info
        $this->backLimit = 16;
        $this->longCache = new ZendCache(60*60*48, true, "wunderground/longHourlyCache");

        // Keep a long-term cache in case access to the API is spotty
        $this->backupCache = new ZendCache(9999999999, true, "wunderground/backupHourlyCache");

        // Keep long-term weather forecast descriptions cache
        $this->forecastsCache = new ZendCache(9999999999, true, "wunderground/forecastsCache");
    }

    public function getHombuHourlyWeatherCache() {
        if($cached = $this->cache->loadCache()) {
            return $cached; // Short-term cache
        } else if($cached = $this->backupCache->loadCache()) {
            return $cached; // Backup cache
        } else {
            return null;
        }
    }

    public function getHombuWeatherForecastsCache() {
        if($cached = $this->forecastsCache->loadCache()) {
            return $cached; // Text description of weather cache
        } else {
            return null;
        }
    }

    private function getWeatherForecastFromAPI($api_url = "") {
        // Set a small timeout
        self::accessCurlObj()->setTimeout(10);
        $num_tries_limit = 5;

        /**
         * Try to access the API a few times if there are timeouts
         */
        for($try_num = 1; $try_num <= $num_tries_limit; $try_num++) {

            $response = self::accessCurlObj()->get($api_url);
            $responsecode = (int)self::accessCurlObj()->getResponseCode();

			//die($responsecode . ' - ' . $response);
			
            if($responsecode < 400 && $response && strlen($response) > 1000) {
                return $response;
            } else if($responsecode > 0 && $response && strlen($response) > 1000) {
                throw new HombuWeatherException("Hombu Weather response code was {$responsecode} with response: {$response}. ");
            } else {

                // Extend the execution time
                if(function_exists("set_time_limit")) {
                    set_time_limit(100);    // Add 100 more seconds for effect
                }

                // Chill for a few seconds and try again
                $this->logger->info("Waiting " . $try_num * 10 . " more seconds ");
                sleep($try_num * 10);
            }
        }

        // Failed. Throw exception
        throw new HombuWeatherException("Hombu Weather API {$api_url} failed {$num_tries_limit} tries");
    }

     // Extract icon url info
    // Before: http://icons-ak.wxug.com/i/c/k/nt_clear.gif
    // After:  nt_clear
    private function getIconNameFromURL($url = null) {
        $icon_name = "unknown";
        if(isset($url)) {
            $icon_name = basename($url, ".gif");

            // preg_match('/^http.+?\/([^\.\/]+?).gif/', $entry->icon_url, $matches)
            // $matches[1];
        }
        return $icon_name;
    }

    public function getHombuHourlyWeather() {
        $weatherHourlyResponse = null;
        if($this->DEBUG) {
            echo "DEBUG MODE - Hourly weather<br>" . PHP_EOL;
            $weatherHourlyResponse = file_get_contents(__DIR__ . "/testing/weatherHourly.json");
        } else {
            $weatherHourlyResponse = self::getWeatherForecastFromAPI($this->hourly_api_url);
        }

        // Parse the response data
        $parsedWeather = self::parseHourlyWeatherResponse($weatherHourlyResponse);

        if(count($parsedWeather) > 0) {
            // Sanity check
            $firstWeatherObject = $parsedWeather[0];
            $lastWeatherObject = $parsedWeather[count($parsedWeather)-1];
            if($firstWeatherObject->epoch + 86400 < time()) {
                throw new HombuWeatherException("ERROR: Hourly weather data is outdated. Ignoring.");
            } else if($lastWeatherObject->epoch - $firstWeatherObject->epoch >= (11 * 86400)) {
                throw new HombuWeatherException("ERROR: Hourly weather data spans more than 10 days. Ignoring.");
            } else {

                // Append previous weather info
                $cachedLong = $this->longCache->loadCache();
                if(!$cachedLong) {
                    $this->longCache->saveCache($parsedWeather);
                    $this->cache->saveCache($parsedWeather);    // We're done. No post-processing needed, yet

                    // Update the backup copy of the cache
                    $this->backupCache->saveCache($parsedWeather);

                    return $parsedWeather;
                } else {

                    // Try to add the last backLimit hours back to the current forecast
                    $firstLiveWeatherObjectEpoch = $parsedWeather[0]->epoch;

                    // Find the just before the first current entry in the long cache
                    $longLastOffset = 0;
                    for($i = 0; $i < count($cachedLong); $i++) {
                        $weatherLongObject = $cachedLong[$i];
                        if($weatherLongObject->epoch < $firstLiveWeatherObjectEpoch) {
                            $longLastOffset = $i;
                        } else {
                            // We've gone past the weather object we were looking for.
                            // Return the last long cache offset
                            break;
                        }
                    }

                    // Prepend long cache objects from the $longLastOffset point backwards
                    $preserve_keys = TRUE;
                    if($longLastOffset <= 0) {
                        // Nothing to do - both cache's are the same?
                        $this->cache->saveCache($parsedWeather);

                        // Update the backup copy of the cache
                        $this->backupCache->saveCache($parsedWeather);

                        return $parsedWeather;

                    } else if($longLastOffset < $this->backLimit) {
                        // Simple prepend - cache will grow until it is 10 days + backLimit hours
                        $mergedParsedWeather = array_merge( array_slice($cachedLong, 0, $longLastOffset+1, $preserve_keys), $parsedWeather);
                        $this->cache->saveCache($mergedParsedWeather);
                        $this->longCache->saveCache($mergedParsedWeather);

                        // Update the backup copy of the cache
                        $this->backupCache->saveCache($mergedParsedWeather);

                        return $mergedParsedWeather;

                    } else {
                        // Prepend only the past backLimit hours in front of the live weather forecast
                        $mergedParsedWeather = array_merge(array_slice($cachedLong, ($longLastOffset+1 - $this->backLimit), $this->backLimit, $preserve_keys), $parsedWeather);
                        $this->cache->saveCache($mergedParsedWeather);
                        $this->longCache->saveCache($mergedParsedWeather);

                        // Update the backup copy of the cache
                        $this->backupCache->saveCache($mergedParsedWeather);

                        return $mergedParsedWeather;
                    }
                }
            }
        }

        return null;
     }

    // Parse the Wunderground hourly data
    private function parseHourlyWeatherResponse($response) {
        // Array of parsed weather results
        $forecast_array = array();

        $parsed_json = json_decode($response);
        if($parsed_json->response) {
            $hourly_forecast = $parsed_json->hourly_forecast;
            if(isset($hourly_forecast)) {

                // Get each hourly forecast
                foreach ($hourly_forecast as $entry) {
                    $FCTTIME = $entry->FCTTIME;
                    if(isset($FCTTIME)) {
                        $WeatherInfo = new StdClass();

                        // Params for this event
                        $WeatherInfo->epoch = intval($FCTTIME->epoch);
                        $WeatherInfo->condition = isset($entry->condition) ? $entry->condition : "Unknown";
                        $WeatherInfo->feelslike = isset($entry->feelslike) ? intval($entry->feelslike->metric) : -100;
                        $WeatherInfo->humidity = isset($entry->humidity) ? intval($entry->humidity) : -1;
                        $WeatherInfo->icon = self::getIconNameFromURL($entry->icon_url);
                        $WeatherInfo->pop = isset($entry->pop) ? intval($entry->pop) : -1;
                        $WeatherInfo->pressure = isset($entry->mslp) ? intval($entry->mslp->metric) : 900;
                        $WeatherInfo->temp = isset($entry->temp) ? intval($entry->temp->metric) : -100;
                        $WeatherInfo->uvi = isset($entry->uvi) ? intval($entry->uvi) : -1;
                        $WeatherInfo->windspeed = isset($entry->wspd) ? intval($entry->wspd->metric) : -1;

                        // Check that all weather items are in range
                        if($WeatherInfo->temp < -50 || $WeatherInfo->temp > 50)
                            $WeatherInfo->temp = 1;

                        if($WeatherInfo->feelslike < -50 || $WeatherInfo->feelslike > 50)
                            $WeatherInfo->feelslike = $WeatherInfo->temp;

                        if($WeatherInfo->humidity < 0 || $WeatherInfo->humidity > 100)
                            $WeatherInfo->humidity = 1;

                        if($WeatherInfo->pop < 0 || $WeatherInfo->pop > 100)
                            $WeatherInfo->pop = 1;

                        if($WeatherInfo->uvi < 0 || $WeatherInfo->uvi > 10)
                            $WeatherInfo->uvi = 1;

                        if($WeatherInfo->windspeed < 0 || $WeatherInfo->windspeed > 100)
                            $WeatherInfo->windspeed = 1;

                        if($WeatherInfo->pressure < 700 || $WeatherInfo->pressure > 1300)
                            $WeatherInfo->pressure = 1001;

                        $forecast_array[] = $WeatherInfo;
                        //echo print_r($WeatherInfo, 1) . PHP_EOL;
                    }
                }
            } else {
                throw new HombuWeatherException("ERROR: hourly forecast was not found in the WU API parse");
            }
        } else {
            throw new HombuWeatherException("ERROR: Response attribute was not found in WU API parse");
        }

        return $forecast_array;
    }

    // Get the textual weather forecasts in English and Japanese
    public function getHombuWeatherForecasts() {
        $weatherForecastEnglish = null;
        $weatherForecastJapanese = null;

        if($this->DEBUG) {
            echo "DEBUG MODE - Textual weather<br>" . PHP_EOL;
            $weatherForecastEnglish = file_get_contents(__DIR__ . "/testing/weatherForecastEnglish.json");
            $weatherForecastJapanese = file_get_contents(__DIR__ . "/testing/weatherForecastJapanese.json");
        } else {
            $weatherForecastEnglish = self::getWeatherForecastFromAPI($this->forecast_api_url_en);
            $weatherForecastJapanese = self::getWeatherForecastFromAPI($this->forecast_api_url_jp);
        }

        // Parse the response data
        $parsedWeatherForecastEnglish = self::parseWeatherForecastResponse($weatherForecastEnglish);
        if(!$parsedWeatherForecastEnglish) {
            $parsedWeatherForecastEnglish = array();
        } else if(count($parsedWeatherForecastEnglish) > 0) {
            // Sanity check
            $firstWeatherObject = $parsedWeatherForecastEnglish[0];
            if($firstWeatherObject->epoch + 86400 < time()) {
                throw new HombuWeatherException("ERROR: English weather forecast data is outdated. Ignoring.");
            }
        }

        $parsedWeatherForecastJapanese = self::parseWeatherForecastResponse($weatherForecastJapanese);
        if(!$parsedWeatherForecastJapanese) {
            $parsedWeatherForecastJapanese = array();
        } else if(count($parsedWeatherForecastJapanese) > 0) {
            // Sanity check
            $firstWeatherObject = $parsedWeatherForecastJapanese[0];
            if($firstWeatherObject->epoch + 86400 < time()) {
                throw new HombuWeatherException("ERROR: Japanese weather forecast data is outdated. Ignoring.");
            }
        }

        // Combine the two languages
        $combinedForecasts = array($parsedWeatherForecastEnglish, $parsedWeatherForecastJapanese);
        $this->forecastsCache->saveCache($combinedForecasts);

        return $combinedForecasts;
    }

    // Parse the Wunderground data
    private function parseWeatherForecastResponse($response = null) {
        // Array of parsed weather results
        $forecast_array = array();

        $parsed_json = json_decode($response);
        if($parsed_json->response) {

            $forecast_object = $parsed_json->forecast;
            if(isset($forecast_object)) {
                // There are two tiers to access: txt_forecast, simpleforecast
                $txt_forecast_object = $forecast_object->txt_forecast;
                $simpleforecast = $forecast_object->simpleforecast;
                if(isset($txt_forecast_object) && isset($simpleforecast)) {

                    $forecastday_text_array = $txt_forecast_object->forecastday;
                    $forecastday_data_array = $simpleforecast->forecastday;
                    if(isset($forecastday_text_array) && isset($forecastday_data_array)) {

                        // Confirm the lengths of the two arrays
                        if(count($forecastday_text_array) == 2 * count($forecastday_data_array)) {

                            // For each data entry, parse 2 textual entries
                            for($i = 0; $i < count($forecastday_data_array); $i++) {
                                if($date_object = $forecastday_data_array[$i]->date) {
                                    $ForecastObject = new StdClass();

                                    // Epoch
                                    $ForecastObject->epoch = intval($date_object->epoch);

                                    // Pretty date
                                    $ForecastObject->prettyDate = isset($date_object->pretty) ? $date_object->pretty : "<Date>";

                                    // Textual info - AM
                                    $periodObject = $forecastday_text_array[$i*2];
                                    $MorningObject = new StdClass();
                                    $MorningObject->icon = self::getIconNameFromURL($periodObject->icon_url);
                                    $MorningObject->title = isset($periodObject->title) ? $periodObject->title : "<Title>";
                                    $MorningObject->forecast = isset($periodObject->fcttext_metric) ? $periodObject->fcttext_metric : "<Forecast>";

                                    // Textual info - PM
                                    $periodObject = $forecastday_text_array[($i*2)+1];
                                    $EveningObject = new StdClass();
                                    $EveningObject->icon = self::getIconNameFromURL($periodObject->icon_url);
                                    $EveningObject->title = isset($periodObject->title) ? $periodObject->title : "<Title>";
                                    $EveningObject->forecast = isset($periodObject->fcttext_metric) ? $periodObject->fcttext_metric : "<Forecast>";

                                    $ForecastObject->day = $MorningObject;
                                    $ForecastObject->night = $EveningObject;

                                    // Store the results
                                    $forecast_array[] = $ForecastObject;
                                } else {
                                    throw new HombuWeatherException("ERROR: date was not found in the WU forecast API parse");
                                }
                            }
                        } else {
                            throw new HombuWeatherException("ERROR: array sizes mismatch in the WU forecast API parse");
                        }
                    } else {
                        throw new HombuWeatherException("ERROR: array entries malformed in the WU forecast API parse");
                    }
                } else {
                    throw new HombuWeatherException("ERROR: array entries missing from the WU forecast API parse");
                }
            } else {
                throw new HombuWeatherException("ERROR: forecast object was not found in the WU forecast API parse");
            }
        } else {
            throw new HombuWeatherException("ERROR: Response attribute was not found in WU API forecast parse");
        }

        return $forecast_array;
    }

    //// HELPERS ////

    protected function accessCurlObj() {
        return $this->curl;
    }

    public function __toString() {
        return print_r(self::getHombuHourlyWeatherCache(), 1);
    }
}
?>
