<?php
/**
 * Class JSONPostProcessors
 * Format the output of a fetch request in various ways
 */

/*
 * NOTICE: In protocol v2 all dates are returned in UTC format,
 * however, some of the internal DB dates are in JST. If the DB is ever
 * upgraded and all the dates are changed to UTC internally, then the
 * methods below which do TZ conversion must be revisited.
 * - Eric
 */

require_once(__DIR__ . '/HombuConstants.php');
require_once(__DIR__ . '/HombuNews.php');
require_once(__DIR__ . '/HombuOverrides.php');


// Conforms to HombuTimetable 20130625.xcdatamodel
class ProtocolPostProcessor {

    // REF: http://stackoverflow.com/questions/952975/how-can-i-easily-convert-dates-from-utc-via-php
    public function convert_time_zone($date_time, $from_tz, $to_tz)
    {
        $time_object = new DateTime($date_time, new DateTimeZone($from_tz));
        $time_object->setTimezone(new DateTimeZone($to_tz));
        return $time_object->format('Y-m-d H:i:s');
    }

    // REF: http://stackoverflow.com/questions/952975/how-can-i-easily-convert-dates-from-utc-via-php
    public function convert_time_zone_epoch($date_time, $from_tz, $to_tz)
    {
        $time_object = new DateTime($date_time, new DateTimeZone($from_tz));
        $time_object->setTimezone(new DateTimeZone($to_tz));
        return $time_object->getTimestamp();
    }

    // Until 2013.10.25
    // Crippled on 2015.01.05 to display unknown teachers
    static public function formatForProtocolV2($dayObj) {

        $dayVars = get_object_vars($dayObj);

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        $Day->date = ProtocolPostProcessor::convert_time_zone($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        //$Day->lastCheckedDate = $dayVars["checked_timestamp"];          // UTC
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Sanity check
        Assert($dayObj->num_events == count($dayObj->events));

        // Add lessons to this day
        $Day->lessons = array();
        if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();

                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];
                $Lesson->lessonId = $lessonVars["event_ID"];

                 // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one leaason
                break;
            }
        }

        // Add news to this day
        $Day->news = array();

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = ProtocolPostProcessor::convert_time_zone($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        return $Day;
    }

    // From 2013.10.26
    // Crippled on 2015.01.05 to display unknown teachers
    static public function formatForProtocolV3($dayObj) {

        $dayVars = get_object_vars($dayObj);

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        $Day->date = ProtocolPostProcessor::convert_time_zone($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Sanity check
        Assert($dayObj->num_events == count($dayObj->events));

        // Add lessons to this day
        $Day->lessons = array();
        if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();

                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one leaason
                break;
            }
        }

        // Add news to this day
        $Day->news = array();

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = ProtocolPostProcessor::convert_time_zone($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        return $Day;
    }

    // From 2013.11.07
    // Crippled on 2015.01.05 to display unknown teachers
    static public function formatForProtocolV4($dayObj, $weatherObjectsArray = null) {

        $dayVars = get_object_vars($dayObj);

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Sanity check
        Assert($dayObj->num_events == count($dayObj->events));

        // Add lessons to this day
        $Day->lessons = array();
        if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();

                //$Lesson->manualEdit = 1;
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                // Add weather info to the Lesson
                if($weatherObjectsArray && is_array($weatherObjectsArray)) {
                    $Lesson->weather = array();
                    $lessonStartEpoch = $Lesson->startDate;

                    // Prevent scanning the array if the Lesson is beyond the forecast
                    if(count($weatherObjectsArray) > 0) {
                        $lastWeatherObject = $weatherObjectsArray[count($weatherObjectsArray)-1];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($weatherObjectsArray as $weatherObject) {
                                //echo $weatherObject->epoch . " vs " . $lessonStartEpoch . PHP_EOL;
                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Return the previous weather object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one leaason
                break;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        return $Day;
    }

    // From 2013.12.19 - v1.5 until version 2.0
    // Crippled on 2015.01.05 to display unknown teachers
    static public function formatForProtocolV5($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        $dayVars = get_object_vars($dayObj);

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Add news to this day
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if($Day->dayStatus == HombuConstants::UNPLANNED_DAY && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if($newsItem->tag && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // ***** OUTDATED WARNING *****
            // Add a warning that this version is outdated
            if(is_array($Day->news)) {
                $Day->news[] = HombuNews::outdatedAppSeriousWarningNews();
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;
                
                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

        // Add lessons to this day
        $Day->lessons = array();
        if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();

                //$Lesson->manualEdit = 1;
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = "unknown";
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one lessson
                break;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        return $Day;
    }

    // From 2014.06.11 - v2.0 and up
    // Crippled on 2015.09.12 to display unknown teachers
    static public function formatForProtocolV6($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        $dayVars = get_object_vars($dayObj);
        $lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Add news to this day
        $awayTeachers = array();
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if(($Day->dayStatus == HombuConstants::UNPLANNED_DAY || $Day->dayStatus == HombuConstants::VALID_DAY) && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if(isset($newsItem->tag) && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // Check the news for any teachers that should be away and note them
            foreach($Day->news as $newsItem) {
                if(isset($newsItem->tag) && isset($newsItem->newsId) && $newsItem->newsId == HombuConstants::TRAVEL_NEWS) {
                    $awayTeachers[] = $newsItem->tag;
                }
            }

            // ***** OUTDATED WARNING *****
            // Add a warning that this version is outdated
            if(is_array($Day->news)) {
                $Day->news[] = HombuNews::outdatedAppSeriousWarningNews();
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

        // Add lessons to this day
        $Day->lessons = array();

        // Don't add lessons to Days which are marked as closed
        if($Day->dayStatus == HombuConstants::CLOSED_DAY) {
            $Day->numEvents = 0;    // Wipe this to be safe

        } else if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();

                // Set the manual edit flag
                // $Lesson->manualEdit = 1;

                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = "unknown";
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                // Detect if an absent teacher (on a teaching trip) is present here
                // If so, add a manual change
                // But, ignore this change if the time now is ahead of the would-be lesson
                $shouldBeAbsentTeachers = array_intersect($teachers, $awayTeachers);
                if(count($shouldBeAbsentTeachers) > 0 && ($Lesson->startDate - $lastCheckedDate) >= 0) {

                    // Move the current teachers to a Change
                    $ManualChange = new StdClass();
                    $ManualChange->updatedDate = $Lesson->startDate;
                    $ManualChange->teachers = $Lesson->teachers;  // See above

                    // Insert the change to the beginning of the changes array
                    array_unshift($Lesson->changes, $ManualChange);

                    unset($Lesson->teachers);    // Remove the previous entries
                    $Lesson->teachers = array();

                    // Make only the current away teacher(s) unknown
                    // This is best used when there are multiple children teachers, but only one is supposed to be away
                    foreach($teachers as $teacher){
                        $Teacher = new StdClass();
                        if(in_array($teacher, $shouldBeAbsentTeachers, true)) {
                            $Teacher->uniqueId = "unknown";
                        } else {
                            $Teacher->uniqueId = $teacher;
                        }
                        $Lesson->teachers[] = $Teacher;
                    }
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one lessson
                break;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = $lastCheckedDate;

        return $Day;
    }

    // From 2014.10.29
    // Crippled on 2015.09.12 to display unknown teachers
    static public function formatForProtocolV7($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null, array $hombuOverridesArray = null) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        if(!isset($hombuOverridesArray) || !is_array($hombuOverridesArray)) {
            $hombuOverridesArray = array();
        }

        $dayVars = get_object_vars($dayObj);
        $lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Add news to this day
        $awayTeachers = array();
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if(($Day->dayStatus == HombuConstants::UNPLANNED_DAY || $Day->dayStatus == HombuConstants::VALID_DAY) && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if(isset($newsItem->tag) && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // Check the news for any teachers that should be away and note them
            foreach($Day->news as $newsItem) {
                if(isset($newsItem->tag) && isset($newsItem->newsId) && $newsItem->newsId == HombuConstants::TRAVEL_NEWS) {
                    $awayTeachers[] = $newsItem->tag;
                }
            }

            // ***** OUTDATED WARNING *****
            // Add a warning that this version is outdated
            if(is_array($Day->news)) {
                $Day->news[] = HombuNews::outdatedAppSeriousWarningNews();
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

//        $logger = new HombuLogger();
//        $logger->debugArray( $dayObj );

        // Add lessons to this day
        $Day->lessons = array();

        // Don't add lessons to Days which are marked as closed
        if($Day->dayStatus == HombuConstants::CLOSED_DAY) {
            $Day->numEvents = 0;    // Wipe this to be safe

        } else if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = "unknown";
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                // Detect if an absent teacher (on a teaching trip) is present here
                // If so, add a manual change
                // But, ignore this change if the time now is ahead of the would-be lesson
                $shouldBeAbsentTeachers = array_intersect($teachers, $awayTeachers);
                if(count($shouldBeAbsentTeachers) > 0 && ($Lesson->startDate - $lastCheckedDate) >= 0) {

                    // Move the current teachers to a Change
                    $ManualChange = new StdClass();
                    $ManualChange->updatedDate = $Lesson->startDate;
                    $ManualChange->teachers = $Lesson->teachers;  // See above

                    // Insert the change to the beginning of the changes array
                    array_unshift($Lesson->changes, $ManualChange);

                    unset($Lesson->teachers);    // Remove the previous entries
                    $Lesson->teachers = array();

                    // Make only the current away teacher(s) unknown
                    // This is best used when there are multiple children teachers, but only one is supposed to be away
                    foreach($teachers as $teacher){
                        $Teacher = new StdClass();
                        if(in_array($teacher, $shouldBeAbsentTeachers, true)) {
                            $Teacher->uniqueId = "unknown";
                        } else {
                            $Teacher->uniqueId = $teacher;
                        }
                        $Lesson->teachers[] = $Teacher;
                    }
                }

                // Check if there are manual edits for this lesson
                $Override = $hombuOverridesArray[$Lesson->lessonId];
                if($Override && is_object($Override) && ($Lesson->startDate - $lastCheckedDate) < 0) {

                    // Sanity check
                    if($Override->wasId != $Override->teacherId &&
                        count($Lesson->teachers) == 1 &&
                        $Lesson->teachers[0]->uniqueId != $Override->teacherId) {

                        // Move the current teachers to a Change
                        $ManualChange = new StdClass();
                        $ManualChange->updatedDate = $Lesson->endDate;
                        $ManualChange->teachers = $Lesson->teachers;  // See above

                        // Insert the change to the beginning of the changes array
                        array_unshift($Lesson->changes, $ManualChange);

                        unset($Lesson->teachers);    // Remove the previous entries

                        $Teacher = new StdClass();
                        $Teacher->uniqueId = $Override->teacherId;
                        $Lesson->teachers = array($Teacher);

                        // Set the manual edit flag
                        $Lesson->manualEdit = 1;
                    }
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one lesson
                break;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = $lastCheckedDate;

        return $Day;
    }

    // From 2014.12.02 - v3.0 and up
    // Added outdated warning on 2015.09.12
    // Crippled on 2015.09.23
    static public function formatForProtocolV8($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null, array $hombuOverridesArray = null) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        if(!isset($hombuOverridesArray) || !is_array($hombuOverridesArray)) {
            $hombuOverridesArray = array();
        }

        $dayVars = get_object_vars($dayObj);
        $lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated

        //$Day->numEvents = $dayVars["num_events"];
        $Day->numEvents = 1;    // because crippled

        $Day->dayHash = null;

        // Add news to this day
        $awayTeachers = array();
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if(($Day->dayStatus == HombuConstants::UNPLANNED_DAY || $Day->dayStatus == HombuConstants::VALID_DAY) && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if(isset($newsItem->tag) && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // Check the news for any teachers that should be away and note them
            foreach($Day->news as $newsItem) {
                if(isset($newsItem->tag) && isset($newsItem->newsId) && $newsItem->newsId == HombuConstants::TRAVEL_NEWS) {
                    $awayTeachers[] = $newsItem->tag;
                }
            }

            // ***** OUTDATED WARNING *****
            // Add a warning that this version is outdated
            if(is_array($Day->news)) {
                $Day->news[] = HombuNews::outdatedAppSeriousWarningNews();
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

//        $logger = new HombuLogger();
//        $logger->debugArray( $dayObj );

        // Add lessons to this day
        $Day->lessons = array();

        // Don't add lessons to Days which are marked as closed
        if($Day->dayStatus == HombuConstants::CLOSED_DAY) {
            $Day->numEvents = 0;    // Wipe this to be safe

        } else if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = "unknown";
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = "unknown";
                    $Lesson->teachers[] = $Teacher;
                }

                // Detect if an absent teacher (on a teaching trip) is present here
                // If so, add a manual change
                // But, ignore this change if the time now is ahead of the would-be lesson
                $shouldBeAbsentTeachers = array_intersect($teachers, $awayTeachers);
                if(count($shouldBeAbsentTeachers) > 0 && ($Lesson->startDate - $lastCheckedDate) >= 0) {

                    // Move the current teachers to a Change
                    $ManualChange = new StdClass();
                    $ManualChange->updatedDate = $Lesson->startDate;
                    $ManualChange->teachers = $Lesson->teachers;  // See above

                    // Insert the change to the beginning of the changes array
                    array_unshift($Lesson->changes, $ManualChange);

                    unset($Lesson->teachers);    // Remove the previous entries
                    $Lesson->teachers = array();

                    // Make only the current away teacher(s) unknown
                    // This is best used when there are multiple children teachers, but only one is supposed to be away
                    foreach($teachers as $teacher){
                        $Teacher = new StdClass();
                        if(in_array($teacher, $shouldBeAbsentTeachers, true)) {
                            $Teacher->uniqueId = "unknown";
                        } else {
                            $Teacher->uniqueId = $teacher;
                        }
                        $Lesson->teachers[] = $Teacher;
                    }
                }

                // Check if there are manual edits for this lesson
                $Override = $hombuOverridesArray[$Lesson->lessonId];
                if($Override && is_object($Override) && ($Lesson->startDate - $lastCheckedDate) < 0) {

                    // Sanity check
                    if($Override->wasId != $Override->teacherId &&
                        count($Lesson->teachers) == 1 &&
                        $Lesson->teachers[0]->uniqueId != $Override->teacherId) {

                        // Move the current teachers to a Change
                        $ManualChange = new StdClass();
                        $ManualChange->updatedDate = $Lesson->endDate;
                        $ManualChange->teachers = $Lesson->teachers;  // See above

                        // Insert the change to the beginning of the changes array
                        array_unshift($Lesson->changes, $ManualChange);

                        unset($Lesson->teachers);    // Remove the previous entries

                        $Teacher = new StdClass();
                        $Teacher->uniqueId = $Override->teacherId;
                        $Lesson->teachers = array($Teacher);

                        // Set the manual edit flag
                        $Lesson->manualEdit = 1;
                    }
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;

                // Since this is deprecated, only allow one lesson
                break;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = $lastCheckedDate;

        return $Day;
    }




    ///// THE BELOW VERSIONS STILL WORK AS OF 2015.09.23 ///////




    // From 2015.07.03 - v4.0 and up
    // Add outdated message warning on 2015.09.23
    static public function formatForProtocolV9($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null, array $hombuOverridesArray = null, $showWarning = FALSE) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        if(!isset($hombuOverridesArray) || !is_array($hombuOverridesArray)) {
            $hombuOverridesArray = array();
        }

        $dayVars = get_object_vars($dayObj);
        $lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated
        $Day->numEvents = $dayVars["num_events"];
        $Day->dayHash = null;

        // Add news to this day
        $awayTeachers = array();
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if(($Day->dayStatus == HombuConstants::UNPLANNED_DAY || $Day->dayStatus == HombuConstants::VALID_DAY) && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if(isset($newsItem->tag) && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // Check the news for any teachers that should be away and note them
            foreach($Day->news as $newsItem) {
                if(isset($newsItem->tag) && isset($newsItem->newsId) && $newsItem->newsId == HombuConstants::TRAVEL_NEWS) {
                    $awayTeachers[] = $newsItem->tag;
                }
            }

            // ***** OUTDATED WARNING *****
            // Add a warning that this version is outdated
            if($showWarning && is_array($Day->news)) {
                $Day->news[] = HombuNews::outdatedAppWarningNews();
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

//        $logger = new HombuLogger();
//        $logger->debugArray( $dayObj );

        // Add lessons to this day
        $Day->lessons = array();

        // Don't add lessons to Days which are marked as closed
        if($Day->dayStatus == HombuConstants::CLOSED_DAY) {
            $Day->numEvents = 0;    // Wipe this to be safe

        } else if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = $teacher;
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = $teacher;
                    $Lesson->teachers[] = $Teacher;
                }

                // Detect if an absent teacher (on a teaching trip) is present here
                // If so, add a manual change
                // But, ignore this change if the time now is ahead of the would-be lesson
                $shouldBeAbsentTeachers = array_intersect($teachers, $awayTeachers);
                if(count($shouldBeAbsentTeachers) > 0 && ($Lesson->startDate - $lastCheckedDate) >= 0) {

                    // Move the current teachers to a Change
                    $ManualChange = new StdClass();
                    $ManualChange->updatedDate = $Lesson->startDate;
                    $ManualChange->teachers = $Lesson->teachers;  // See above

                    // Insert the change to the beginning of the changes array
                    array_unshift($Lesson->changes, $ManualChange);

                    unset($Lesson->teachers);    // Remove the previous entries
                    $Lesson->teachers = array();

                    // Make only the current away teacher(s) unknown
                    // This is best used when there are multiple children teachers, but only one is supposed to be away
                    foreach($teachers as $teacher){
                        $Teacher = new StdClass();
                        if(in_array($teacher, $shouldBeAbsentTeachers, true)) {
                            $Teacher->uniqueId = "unknown";
                        } else {
                            $Teacher->uniqueId = $teacher;
                        }
                        $Lesson->teachers[] = $Teacher;
                    }
                }

                // Check if there are manual edits for this lesson
                $Override = $hombuOverridesArray[$Lesson->lessonId];
                if($Override && is_object($Override) && ($Lesson->startDate - $lastCheckedDate) < 0) {

                    // Sanity check
                    if($Override->wasId != $Override->teacherId &&
                        count($Lesson->teachers) == 1 &&
                        $Lesson->teachers[0]->uniqueId != $Override->teacherId) {

                        // Move the current teachers to a Change
                        $ManualChange = new StdClass();
                        $ManualChange->updatedDate = $Lesson->endDate;
                        $ManualChange->teachers = $Lesson->teachers;  // See above

                        // Insert the change to the beginning of the changes array
                        array_unshift($Lesson->changes, $ManualChange);

                        unset($Lesson->teachers);    // Remove the previous entries

                        $Teacher = new StdClass();
                        $Teacher->uniqueId = $Override->teacherId;
                        $Lesson->teachers = array($Teacher);

                        // Set the manual edit flag
                        $Lesson->manualEdit = 1;
                    }
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = $lastCheckedDate;

        return $Day;
    }



    // From 2015.09.10 - v5.0 and up
    static public function formatForProtocolV10($dayObj, $weatherObjectsArray = null, HombuNews $hombuNews = null, array $hombuOverridesArray = null) {

        // Split the weather objects array into the hourly and textual forecasts
        $hourlyWeatherObjectsArray = null;
        $textualWeatherForecastsArray = null;
        if(isset($weatherObjectsArray) && is_array($weatherObjectsArray) && count($weatherObjectsArray) == 2) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray[0];
            $textualWeatherForecastsArray = $weatherObjectsArray[1];
        } else if(isset($weatherObjectsArray) && is_array($weatherObjectsArray)) {
            $hourlyWeatherObjectsArray = $weatherObjectsArray;  // Legacy support so v5 still works
        }

        if(!isset($hombuOverridesArray) || !is_array($hombuOverridesArray)) {
            $hombuOverridesArray = array();
        }

        $dayVars = get_object_vars($dayObj);
        $lastCheckedDate = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["checked_timestamp"], "JST", "UTC"); // JST to UTC

        // Conform the Day object to the Day entity
        $Day = new StdClass();

        // Get the date as a unix epoch
        $Day->date = ProtocolPostProcessor::convert_time_zone_epoch($dayVars["date"], "JST", "UTC"); // UTC

        $Day->dayStatus = $dayVars["status"];
        $Day->lastCheckedDate = null; // See below when the hash is calculated
        $Day->numEvents = $dayVars["num_events"];
        $Day->dayHash = null;

        // Add news to this day
        $awayTeachers = array();
        if($hombuNews) {
            $Day->news = $hombuNews->getHombuNews($Day->date);   // Get the JST date from the epoch string

            // Hombu doesn't make a distinction between CLOSED days and UNPLANNED_DAYS,
            // so use the news tags as helpers. If there is a News item for an "UNPLANNED_DAY"
            // with a "closed" tag, then treat this Day as a CLOSED day instead.
            if(($Day->dayStatus == HombuConstants::UNPLANNED_DAY || $Day->dayStatus == HombuConstants::VALID_DAY) && $Day->news) {
                foreach($Day->news as $newsItem) {
                    if(isset($newsItem->tag) && $newsItem->tag == HombuConstants::CLOSED_DAY_TAG) {
                        $Day->dayStatus = HombuConstants::CLOSED_DAY;
                        break;
                    }
                }
            }

            // Check the news for any teachers that should be away and note them
            foreach($Day->news as $newsItem) {
                if(isset($newsItem->tag) && isset($newsItem->newsId) && $newsItem->newsId == HombuConstants::TRAVEL_NEWS) {
                    $awayTeachers[] = $newsItem->tag;
                }
            }
        }

        // Add textural weather forecast to this day
        if($textualWeatherForecastsArray && is_array($textualWeatherForecastsArray)) {
            $WeatherForecast = new StdClass();
            $dayEpoch = $Day->date;

            // English forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[0], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoEnDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoEnNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Japanese forecasts
            $forecastObjects = self::getTextualForecastForDayEpoch($textualWeatherForecastsArray[1], $dayEpoch);
            if(count($forecastObjects) > 0) {
                $WeatherForecast->iconDay = @$forecastObjects->day->icon;
                $WeatherForecast->iconNight = @$forecastObjects->night->icon;

                $WeatherForecast->infoJaDay = @$forecastObjects->day->title . " - " . @$forecastObjects->day->forecast;
                $WeatherForecast->infoJaNight = @$forecastObjects->night->title . " - " . @$forecastObjects->night->forecast;
            }

            // Add the results back to the Day object
            if(isset($WeatherForecast->infoJaDay) && isset($WeatherForecast->infoEnDay)) {
                $Day->weather = $WeatherForecast;
            }
        }

        // Events sanity check
        Assert($dayObj->num_events == count($dayObj->events));

//        $logger = new HombuLogger();
//        $logger->debugArray( $dayObj );

        // Add lessons to this day
        $Day->lessons = array();

        // Don't add lessons to Days which are marked as closed
        if($Day->dayStatus == HombuConstants::CLOSED_DAY) {
            $Day->numEvents = 0;    // Wipe this to be safe

        } else if(isset($dayVars["events"])){
            foreach($dayVars["events"] as $lesson) {
                $lessonVars = get_object_vars($lesson);

                // Conform Lesson objects to Lesson entities
                $Lesson = new StdClass();
                $Lesson->startDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["start_datetime"], "JST", "UTC"); // UTC
                $Lesson->endDate = ProtocolPostProcessor::convert_time_zone_epoch($lessonVars["end_datetime"], "JST", "UTC"); // UTC
                $Lesson->lessonFloor = $lessonVars["floor"];
                $Lesson->lessonType = $lessonVars["event_type"];

                // From 2013.10.26 - This is a unique hash for a lesson
                $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor);
                if($crc == 0) {
                    $crc = crc32($Lesson->startDate . "-" . $Lesson->lessonFloor . "-");
                }
                $Lesson->lessonId = $crc;

                // Add changes to the lesson
                $Lesson->changes = array();
                if(isset($lessonVars["changes"])){
                    foreach($lessonVars["changes"] as $change) {

                        // Handle objects or arrays
                        $changeVars = null;
                        if(is_object($change)) {
                            $changeVars = get_object_vars($change);
                        } else {
                            $changeVars = $change;
                        }

                        // Conform Change objects to Change entities
                        $Change = new StdClass();
                        $Change->updatedDate = ProtocolPostProcessor::convert_time_zone_epoch($changeVars["checked_date"], "JST", "UTC"); // UTC

                        // Add teachers to the changed lesson
                        $Change->teachers = array();
                        $teachers = explode(",", $changeVars["teacher_names"]);  // This is just a string of comma-separated ids
                        foreach($teachers as $teacher){
                            $Teacher = new StdClass();
                            $Teacher->uniqueId = $teacher;
                            $Change->teachers[] = $Teacher;
                        }
                        $Lesson->changes[] = $Change;
                    }
                }

                // Add teachers to the lesson
                $Lesson->teachers = array();
                $teachers = explode(",", $lessonVars["teacher_names"]);  // This is just a string of comma-separated ids
                foreach($teachers as $teacher){
                    $Teacher = new StdClass();
                    $Teacher->uniqueId = $teacher;
                    $Lesson->teachers[] = $Teacher;
                }

                // Detect if an absent teacher (on a teaching trip) is present here
                // If so, add a manual change
                // But, ignore this change if the time now is ahead of the would-be lesson
                $shouldBeAbsentTeachers = array_intersect($teachers, $awayTeachers);
                if(count($shouldBeAbsentTeachers) > 0 && ($Lesson->startDate - $lastCheckedDate) >= 0) {

                    // Move the current teachers to a Change
                    $ManualChange = new StdClass();
                    $ManualChange->updatedDate = $Lesson->startDate;
                    $ManualChange->teachers = $Lesson->teachers;  // See above

                    // Insert the change to the beginning of the changes array
                    array_unshift($Lesson->changes, $ManualChange);

                    unset($Lesson->teachers);    // Remove the previous entries
                    $Lesson->teachers = array();

                    // Make only the current away teacher(s) unknown
                    // This is best used when there are multiple children teachers, but only one is supposed to be away
                    foreach($teachers as $teacher){
                        $Teacher = new StdClass();
                        if(in_array($teacher, $shouldBeAbsentTeachers, true)) {
                            $Teacher->uniqueId = "unknown";
                        } else {
                            $Teacher->uniqueId = $teacher;
                        }
                        $Lesson->teachers[] = $Teacher;
                    }
                }

                // Check if there are manual edits for this lesson
                $Override = $hombuOverridesArray[$Lesson->lessonId];
                if($Override && is_object($Override) && ($Lesson->startDate - $lastCheckedDate) < 0) {

                    // Sanity check
                    if($Override->wasId != $Override->teacherId &&
                        count($Lesson->teachers) == 1 &&
                        $Lesson->teachers[0]->uniqueId != $Override->teacherId) {

                        // Move the current teachers to a Change
                        $ManualChange = new StdClass();
                        $ManualChange->updatedDate = $Lesson->endDate;
                        $ManualChange->teachers = $Lesson->teachers;  // See above

                        // Insert the change to the beginning of the changes array
                        array_unshift($Lesson->changes, $ManualChange);

                        unset($Lesson->teachers);    // Remove the previous entries

                        $Teacher = new StdClass();
                        $Teacher->uniqueId = $Override->teacherId;
                        $Lesson->teachers = array($Teacher);

                        // Set the manual edit flag
                        $Lesson->manualEdit = 1;
                    }
                }

                // Add weather info to the Lesson
                if($hourlyWeatherObjectsArray && is_array($hourlyWeatherObjectsArray)) {
                    // echo "<pre>"; print_r($hourlyWeatherObjectsArray); echo "</pre>";

                    // Prevent scanning the array if the Lesson is outside the forecast window
                    if(count($hourlyWeatherObjectsArray) > 0) {
                        $lessonStartEpoch = $Lesson->startDate;
                        $lastWeatherObject = $hourlyWeatherObjectsArray[count($hourlyWeatherObjectsArray)-1];
                        $firstWeatherObject = $hourlyWeatherObjectsArray[0];
                        if($lastWeatherObject->epoch >= $lessonStartEpoch && $firstWeatherObject->epoch <= $lessonStartEpoch) {

                            // Find the weather information in the parsed weather object array
                            $previousWeatherObject = array();
                            foreach($hourlyWeatherObjectsArray as $weatherObject) {
                                // echo date('r', $weatherObject->epoch) . " vs " . date('r', $lessonStartEpoch) . PHP_EOL;

                                if($weatherObject->epoch <= $lessonStartEpoch) {
                                    $previousWeatherObject = $weatherObject;
                                } else {
                                    // We've gone past the weather object we were looking for.
                                    // Add the previous weather object to the Lesson object
                                    $Lesson->weather = $previousWeatherObject;
                                    break;
                                }
                            }
                        }
                    }
                }

                $Day->lessons[] = $Lesson;
            }
        }

        // Add day hash value except for the updated date
        $Day->dayHash = md5(serialize($Day));

        // Add the last checked date back. Don't change the hash just because the LCD changed
        $Day->lastCheckedDate = $lastCheckedDate;

        return $Day;
    }


    /// Helpers ///

    private function getTextualForecastForDayEpoch($textualWeatherForecastsArray, $dayEpoch) {
        $adjustedEpoch = $dayEpoch + 86400; // One day off, since the epoch of the these forecasts is something like 7 PM that day

        // Prevent scanning the array if the Day is beyond the forecast
        if(isset($textualWeatherForecastsArray) && count($textualWeatherForecastsArray) > 0) {
            $lastWeatherObject = $textualWeatherForecastsArray[count($textualWeatherForecastsArray)-1];
            if($lastWeatherObject->epoch >= $adjustedEpoch) {

                // Find the weather information in the parsed weather object array
                $previousWeatherObject = array();
                foreach($textualWeatherForecastsArray as $weatherObject) {
                    //echo $weatherObject->epoch . " vs " . $adjustedEpoch . PHP_EOL;
                    if($weatherObject->epoch <= $adjustedEpoch) {
                        $previousWeatherObject = $weatherObject;
                    } else {
                        // We've gone past the weather object we were looking for.
                        // Return the previous weather object
                        return $previousWeatherObject;
                        break;
                    }
                }
            }
        }

        return array();
    }
}

?>