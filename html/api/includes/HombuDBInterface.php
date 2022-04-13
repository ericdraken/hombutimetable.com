<?php
/**
 * Access the Hombu DB
 */

 
// Load the ezSQL core
require_once("ezSQL/shared/ez_sql_core.php");
require_once("ezSQL/mysql/ez_sql_mysql.php");

require_once(__DIR__ . "/WPFormattingUtils.php");

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');	// Base exception class
require_once(__DIR__ . '/HombuConstants.php');
require_once(__DIR__ . '/ProtocolPostProcessor.php');


class HombuDBInterfaceException extends HombuException {}

/**
 * Connect to the DB with minimal overhead
 */
final class HombuDBInterface {

	// Private variables
	private $args, $db, $logger;

    // How many tables are there = keep this current!
    private $num_tables = 6;

	private $defaults = array(
		'cache_dir' => "/hombu_db_cache",
		'cache_hours' => 1,					// Default to 1 hour of cache
		'cache_SQL_queries' => true,
		'do_not_cache' => 14,				// Don't cache from days 0 to 14
	);

	function __construct($args = array(), HombuLogger $logger) {
		$this->logger = $logger;

		// Sort out those $args
		$this->args = WPFormattingUtils::wp_parse_args($args, $this->defaults);

		// Constants defined in global_settings.php
		$this->db = new ezSQL_mysql();
		$this->db->connect(
			HOMBUTIMETABLE_DB_USER,
			HOMBUTIMETABLE_DB_PASSWORD,
			HOMBUTIMETABLE_DB_HOST);

		// If the database doesn't exist, create it here
		if(!$this->checkDBExistence()) {
			$logger->info("Database ".HOMBUTIMETABLE_DB_NAME." doesn't exist. Creating it now.");
			$this->createDB();
			
			// Confirm
			if($this->checkDBExistence()) {
				$logger->info("Database ".HOMBUTIMETABLE_DB_NAME." successfully created.");
			} else {
				throw new HombuDBInterfaceException("Database ".HOMBUTIMETABLE_DB_NAME." couldn't be created.", $logger);
			}
		}
		
		// Check if the tables exists or create them
		if(!$this->checkDBTablesExistence()) {
			$logger->info("Tables from database ".HOMBUTIMETABLE_DB_NAME." don't exist. Creating them now.");
			$this->createDBTables();
			
			// Confirm
			if($this->checkDBTablesExistence()) {
				$logger->info("Database tables for ".HOMBUTIMETABLE_DB_NAME." successfully created.");
			} else {
				throw new HombuDBInterfaceException("Database tables for ".HOMBUTIMETABLE_DB_NAME." couldn't be created.", $logger);
			}
		}

		// Connect to the database
		$this->db->select(HOMBUTIMETABLE_DB_NAME);
		$this->db->query("SET CHARACTER SET 'utf8'");	        // ??? otherwise
		$this->db->query("SET NAMES 'utf8'");			        // ??? otherwise
        $this->db->query("SET @@session.time_zone='+09:00'");	// Japan

		// Hold local results here		
		$this->setQueryCacheOptions();
	}

    public function addAnalytics($device_ID, $filters, $app_version, $lang, $play_count, $machine_name, $os_version, $country, $city, $longitude, $latitude, $ip_address) {

        // App version
        if(!isset($app_version)) {
            $app_version = "?";
        } else {
            $app_version = substr(self::stripNonAscii($app_version), 0, 10);
        }

        // App lang
        if(!isset($lang)) {
            $lang = "?";
        } else {
            $lang = substr(strtolower(self::stripNonAscii($lang)), 0, 2);
        }

        // Apple device
        if(!isset($machine_name)) {
            $machine_name = "?";
        } else {
            $machine_name = substr(strtolower(self::stripNonAscii($machine_name)), 0, 12);
        }

        // Apple OS
        if(!isset($os_version)) {
            $os_version = "?";
        } else {
            $os_version = substr(strtolower(self::stripNonAscii($os_version)), 0, 6);
        }

        // Videos play count
        if(!isset($play_count)) {
            $play_count = 0;
        } else {
            $play_count = intval(self::stripNonAscii($play_count));
        }

        // Country
        if(!isset($country) || strlen($country) < 3) {
            $country = "xx";
        } else {
            $country = ucwords(strtolower(substr(trim($country), 0, 30)));
        }

        // City
        if(!isset($city) || strlen($city) < 3) {
            $city = "xx";
        } else {
            $city = ucwords(strtolower(substr(trim($city), 0, 30)));
        }

        // Longitude
        if(!isset($longitude)) {
            $longitude = 0;
        } else {
            $longitude = floatval(substr(self::stripNonAscii($longitude), 0, 10));
        }

        // Latitude
        if(!isset($latitude)) {
            $latitude = 0;
        } else {
            $latitude = floatval(substr(self::stripNonAscii($latitude), 0, 10));
        }

        // IP address
        if(!isset($ip_address)) {
            $ip_address = "0.0.0.0";
        } else {
            // REF: http://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
            // 45 chars max for ipv6
            $ip_address = substr(self::stripNonAscii($ip_address), 0, 45);
        }

        // Format check
        if(isset($device_ID) && preg_match("/[0-9]{1,10}/", trim($device_ID)) && isset($filters) && preg_match("/[a-f0-9]{8}/", trim($filters))) {

            // Device ID
            $device_ID = substr(self::stripNonAscii($device_ID), 0, 10); // 32-bit crc32 number-string

            // Filter string
            $filters = substr(self::stripNonAscii($filters), 0, 8);      // 4-byte hex string

            // Caching
            $this->db->cache_queries = false;	// Cache not needed on insert

    		$sql = "INSERT INTO `".HOMBUTIMETABLE_TABLE_PREFIX."analytics` (
    		            `device_ID`,
                        `filters`,
                        `app_version`,
                        `machine_name`,
                        `os_version`,
                        `lang`,
                        `country`,
                        `city`,
                        `longitude`,
                        `latitude`,
                        `ip_address`,
                        `play_count`)
                    VALUES (
                        '".$device_ID."',
                        '".$filters."',
                        '".$app_version."',
                        '".$machine_name."',
                        '".$os_version."',
                        '".$lang."',
                        '".$country."',
                        '".$city."',
                        '".$longitude."',
                        '".$latitude."',
                        '".$ip_address."',
                        '".$play_count."')
                    ON DUPLICATE KEY UPDATE
                        `access` = `access` + 1,
                        `filters` = '".$filters."',
                        `app_version` = '".$app_version."',
                        `machine_name` = '".$machine_name."',
                        `os_version` = '".$os_version."',
                        `lang` = '".$lang."',
                        `country` = '".$country."',
                        `city` = '".$city."',
                        `longitude` = '".$longitude."',
                        `latitude` = '".$latitude."',
                        `ip_address` = '".$ip_address."',
                        `play_count` = '".$play_count."',
                        `updated_timestamp` = CURRENT_TIMESTAMP;";

            if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
                throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
            }

            // Update deltas
            $this->updateAnalyticsDeltas($device_ID);

            return true;
        }

        return false;
    }

    /**
     * Update the running deltas to keep an accurate rate of app use statistic
     * @throws HombuDBInterfaceException
     */
    private function updateAnalyticsDeltas($device_ID = 0) {

        // Add an entry to the analytics detlas table
        $sql = "INSERT INTO `".HOMBUTIMETABLE_TABLE_PREFIX."analytics_deltas` (`device_ID`) VALUES ('".$device_ID."') ON DUPLICATE KEY UPDATE device_ID = device_ID;";
        if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
            throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
        }

        // Get the row for this device ID
        $sql = "SELECT * FROM `".HOMBUTIMETABLE_TABLE_PREFIX."analytics_deltas` WHERE `device_ID` = '".$device_ID."' LIMIT 1;";
        $deltas = $this->db->get_row($sql, ARRAY_A);

        // Update the deltas here
        $num_delta_columns = 10;
        if(is_array($deltas) && count($deltas) > 1) {
            $last_col = $last_delta_col_id = $deltas['last_delta_col_id'];

            // Is the the special case of the very first entry?
            $first_entry = intval($deltas['d0']);
            if($first_entry == 0) {

                // Set the first timestamp
                $sql = "UPDATE `".HOMBUTIMETABLE_TABLE_PREFIX."analytics_deltas` SET `d0` = '".time()."' WHERE `device_ID` = '".$device_ID."';";
                if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
                    throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
                }

                return; // There is nothing to do until the next timestamp comes
            }

            if($last_col < $num_delta_columns-1) {
                $last_delta_col_id++;     // move ahead
            } else {
                $last_delta_col_id = 0;   // cycle back to the start
            }

            // Add the new timestamp for this stats access
            $deltas['d'.$last_delta_col_id] = time();

            // Sum the rolling differences in time stamps
            $sum_of_differences = 0;
            $num_of_deltas_used = 1;
            for($i = 0; $i < $num_delta_columns; $i++) {
                $b = intval($deltas['d'.($i+1)%$num_delta_columns]);
                $a = intval($deltas['d'.$i]);
                $diff = $b - $a;
                $num_of_deltas_used = $i+1;
                if($diff > 0) {
                    $sum_of_differences += $diff;   // Only positive vales
                } else if($b == 0){
                    break;
                }
            }

            // Calculate the average access rate
            $avg_rate = round($sum_of_differences / $num_of_deltas_used);

            // Update the stats, timestamp and the delta pointer
            $sql = "UPDATE `".HOMBUTIMETABLE_TABLE_PREFIX."analytics_deltas` SET `d".$last_delta_col_id."` = '".$deltas['d'.$last_delta_col_id]."', `last_delta_col_id` = ".$last_delta_col_id.", `access_rate` = '".$avg_rate."' WHERE `device_ID` = '".$device_ID."';";
            if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
                throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
            }
        }
    }

	/**
	 * Create or update a hombu schedule date and its status
	 * @throws HombuDBInterfaceException
	 */
	public function addUpdateHombuDay(array &$day_array) {

		// Validity		
		HombuValidators::validateHombuDay($day_array);
		HombuValidators::sqlDateFormatCheck($day_array["date"]);

		// Caching
		$this->db->cache_queries = false;	// Cache not needed on insert

		$sql_date = $day_array["date"];
		$valid_status = $this->db->escape($day_array["status"]);
		
		/**
		 * When a day is purged, don't delete it, but just change the status
		 */
		if($valid_status == HombuConstants::PURGED_DAY) {
			$this->logger->info("Purged day detected on ".$day_array["date"]." with status ".$day_array["status"]." so aborting DB update.");
			return;
		}
		
		// Create/update a day and it's status
		$sql = "INSERT INTO `".HOMBUTIMETABLE_TABLE_PREFIX."days` (`date`, `num_events`, `status`) VALUES ('".$sql_date."', '".count($day_array["events"])."', '".$valid_status."') 
				ON DUPLICATE KEY UPDATE 
					`status` = '".$valid_status."',
					`num_events` = '".count($day_array["events"])."',
					`checked_timestamp` = CURRENT_TIMESTAMP;";
		
		if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
			//$this->logger->debugArray($this->db->debug(false));
			throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
		}
		
		$this->logger->info("Adding/updating ".$day_array["date"]." with status ".$day_array["status"]);

        // 2014-11-04 - Add default events first
        if(isset($day_array["defaultEvents"])) {
            foreach ($day_array["defaultEvents"] as $key => $value) {
                echo "-- Default teacher found: " . $value["teachers"][0] . "<br>";

                $this->addUpdateEvent($value, false);    // ignore duplicate default events. i.e. don't update the checked timestamp of these
            }
        }

		// ... then add found and parsed events next events
		foreach ($day_array["events"] as $key => $value) {
			$this->addUpdateEvent($value, true);   // update the timestamp of these event details
		}
	}

	// Access the ezSQL connection
	public function accessDB() { return $this->db; }

	// Convenience function
	public function get_results($str, $output = OBJECT) { return $this->db->get_results($str, $output); }


	/**
	 * Determine if this date should be cached or not 
	 */
	public function shouldCache($date) {
		
		/**
		 * If the requested date is not 'near' today's date,
		 * cache the search query
		 */
		HombuValidators::sqlDateFormatCheck($date);
		$today_date = new DateTime('today');
		$date_test = new DateTime($date);
		$interval = $today_date->diff($date_test);
		$should_cache = false;
		
		// Cache past days, and over 14 days
		if($interval->invert || $interval->days > $this->args["do_not_cache"]) {
			$should_cache = true;
		}

		return $should_cache;	
	}



	////// PRIVATE ///////

	/**
	 * Get the event offset useful for building a calendar
	 */
	private function getEventOffset($start_datetime_ms, $today_time_ms) {
		$onehr = 3600;		
		$time = ($start_datetime_ms - $today_time_ms) / $onehr;
		return $time;
	}
	 
	/**
	 * Sort by time and floor
	 */
	private function day_event_timefloor_sort(stdClass $a, stdClass $b) {
		
		// Same start time?
	    if ($a->start_datetime_ms == $b->start_datetime_ms) {
	    	
			// Same floor?
			if($a->floor == $b->floor) {
				return 0;	
			}
			
			// Compare floors
			return ($a->floor < $b->floor) ? -1 : 1;
 
	    } else {
	    	
			// Compare start times
	    	return ($a->start_datetime_ms < $b->start_datetime_ms) ? -1 : 1;
	    }
	}

	/**
	 * Add/update an event in the database 
	 */
	private function addUpdateEvent(array &$event, $update_details_timestamp = true) {

		// Validity
		HombuValidators::validateHombuEvent($event);
		HombuValidators::sqlDateFormatCheck($event["date"]);

		/**
		 * Create a hash of the start hour and end hour
		 * to minimize duplicate events due to some administrator
		 * doing something like 06:31 --> 06:30 resulting in duplicate events
		 */
		$startendtype_hash = HombuFormatters::hashLesson($event["start"], $event["end"], $event["type"]);	// 8 chars
		
		/**
		 * Create/update a day and it's status
		 * If the starting time or ending time of an event is 'fudged' a bit by the schedule updater human,
		 * Then use the above hash to detect this and simply change the event back to the new time 
		 */
		$sql = "INSERT INTO `".HOMBUTIMETABLE_TABLE_PREFIX."events` (
					`date`, 
					`start_datetime`,
					`start_datetime_ms`, 
					`end_datetime`,
					`startendtype_hash`,
					`event_type`, 
					`floor`)
				VALUES (
					'".$event["date"]."', 
					'".$event["start"]."',
					'".strtotime($event["start"])."',
					'".$event["end"]."', 
					'".$startendtype_hash."', 
					'".$event["type"]."', 
					'".$event["floor"]."')
				ON DUPLICATE KEY UPDATE
					`start_datetime` = '".$event["start"]."', 
					`start_datetime_ms` = '".strtotime($event["start"])."', 
					`end_datetime` = '".$event["end"]."', 
					`checked_timestamp` = CURRENT_TIMESTAMP;";

		$event_ID = 0;
        if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
            //$this->logger->debugArray($this->db->debug(false));
            throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
		} else {
			
			// Be very, very clear on this to avoid collisions due to bad floor detection
			$event_sql = "SELECT event_ID FROM `".HOMBUTIMETABLE_TABLE_PREFIX."events` WHERE 
				date = '".$event["date"]."' AND 
				start_datetime = '".$event["start"]."' AND 
				event_type = '".$event["type"]."' AND 
				floor = '".$event["floor"]."'";
			$event_ID = $this->db->get_var($event_sql);
		}
		
		$this->logger->info("Updating ".$event["title"]." from ".$event["start"]." to ".$event["end"]." (event_ID = {$event_ID})");

		/**
		 * There can only be one event type per day, per hour and per floor
		 * Also, event details are unique.
		 * So, multiple announcements can be stored in the event details easily
		 */
		
		// Lessons for a valid day
		if($event["type"] != HombuConstants::ALL_FLOORS) {
	
			// Add event details
			$teachers = array();
			foreach ($event["teachers"] as $key => $teacher) {
				$teachers[] = $teacher;
			}
			$this->updateEventDetails($event_ID, $teachers, $event["title"], $update_details_timestamp);
			
		} else {
			$this->logger->info("This event is on ".$event["type"]." so no teacher details will be updated.");
		}
	}

    /**
     * Get a hombu date events from the DB
     * Fine for the web service - last checked 2013-06-24
     */
    public function getHombuDayEvents($sql_date, $shi = "") {

        // Validity
        HombuValidators::sqlDateFormatCheck($sql_date);

        // Clean shihan filter request
        $shi = preg_replace("/[^a-z]/", '', strtolower($shi));

        // Caching
        $this->db->cache_queries = $this->shouldCache($sql_date);

        $prefix = HOMBUTIMETABLE_TABLE_PREFIX;

        /**
         * Day container
         */
        $day_sql = "SELECT * FROM `".$prefix."days` WHERE `".$prefix."days`.`date` = '".$sql_date."';";
        $dayObj = $this->db->get_row($day_sql);

        //$this->db->debug();
        //die();

        /**
         * Events for the day
         */
        if($dayObj) {

            /**
             * Get last updated timestamp as UTC time
             */
            $dayObj->checked_utc_timestamp = date('c', strtotime($dayObj->checked_timestamp)) . " " . date_default_timezone_get() . " " . date("Y-m-d H:i:s");

            /**
             * Get the valid event IDs as a list for further processing
             */
            $valid_events_IDs_sql = "SELECT
					`".$prefix."events`.`event_ID`
				FROM
					`".$prefix."events`
				WHERE
					`".$prefix."events`.`date` = '".$sql_date."'
				ORDER BY
					`".$prefix."events`.`checked_timestamp` DESC
				LIMIT 0, ".$dayObj->num_events."
				";

            /**
             * Confirm there are events for this day, or return just the day object
             */
            $tmp_results = $this->db->get_results($valid_events_IDs_sql);
            if(isset($tmp_results)) {

                $valid_events_IDs = trim(array_reduce($tmp_results, function($sum, $elem){
                    return $sum .= "," . $elem->event_ID;
                }, ""), ",");

                // Sort the event IDs
                $valid_events_IDs = explode(",", $valid_events_IDs);
                sort($valid_events_IDs);
                $valid_events_IDs = implode(",", $valid_events_IDs);

                /**
                 * Get the events that are valid
                 */
                $valid_events_sql = "SELECT
						`".$prefix."events`.`event_ID`,
						`start_datetime`,
						`start_datetime_ms`,
						`end_datetime`,
						`event_type`,
						`floor`,
						`raw_event_info`,
						`teacher_names`,
						`".$prefix."details`.`checked_timestamp` AS checked_date,
						UNIX_TIMESTAMP(`".$prefix."details`.`checked_timestamp`) as checked_timestamp
					FROM
						`".$prefix."events`
					LEFT JOIN
						`".$prefix."details`
					ON
						`".$prefix."events`.`event_ID` = `".$prefix."details`.`event_ID`
					WHERE
						`".$prefix."events`.`date` = '".$sql_date."' AND
						`".$prefix."events`.`event_ID` IN(".$valid_events_IDs.")
					ORDER BY
						`".$prefix."events`.`event_ID` ASC,
						checked_timestamp DESC
					";

                $eventsObj = $this->db->get_results($valid_events_sql);

                //$this->db->debug();
                //die();

                /**
                 * Split out updates from primary event details
                 * This relies on details with the same event_ID being adjacent to each other
                 */
                $valid_count = $dayObj->num_events;
                $event_counter = 0;
                if($eventsObj) {

                    // Add more info useful to the client javascript
                    $today_time_ms = strtotime($dayObj->date);
                    foreach ($eventsObj as $event) {
                        // Start time
                        $event->start_timeoffset = $this->getEventOffset($event->start_datetime_ms, $today_time_ms);

                        // End time
                        $event->end_timeoffset = $this->getEventOffset(strtotime($event->end_datetime), $today_time_ms);
                    }

                    $count = count($eventsObj);
                    if($count > 1) {

                        // Scan the array for duplicate	event IDs
                        $detailsObj = array();
                        $tmp_arr = array();
                        $base_event_ind = 0;
                        $curr_event_ID = $eventsObj[0]->event_ID;

                        for($i = 1; $i < $count; $i++) {

                            if($curr_event_ID == $eventsObj[$i]->event_ID) {
                                $tmp_arr[] = array(
                                    "raw_event_info" => $eventsObj[$i]->raw_event_info,
                                    "teacher_names" => $eventsObj[$i]->teacher_names,
                                    "checked_timestamp" => $eventsObj[$i]->checked_timestamp,
                                    "checked_date" => $eventsObj[$i]->checked_date

                                );
                            }

                            if($curr_event_ID != $eventsObj[$i]->event_ID || $i >= $count-1) {
                                $detailsObj = $eventsObj[$base_event_ind];

                                // Add changes if there are some
                                if(count($tmp_arr) > 0) {
                                    $detailsObj->changes = $tmp_arr;

                                    // Reset temp array
                                    unset($tmp_arr);
                                    $tmp_arr = array();
                                }

                                // Decide if valid or invalid event
                                if($event_counter < $valid_count) {
                                    $dayObj->events[] = $detailsObj;
                                } else {
                                    $dayObj->removed_events[] = $detailsObj;
                                }

                                // Take care of the last element
                                if($i >= $count-1 && $curr_event_ID != $eventsObj[$i]->event_ID) {
                                    if($event_counter < $valid_count) {
                                        $dayObj->events[] = $eventsObj[$count-1];
                                    } else {
                                        $dayObj->removed_events[] = $eventsObj[$count-1];
                                    }
                                }

                                // Advance the base index
                                $base_event_ind = $i;
                                $curr_event_ID = $eventsObj[$i]->event_ID;
                                $event_counter++;
                            }
                        }
                    } else {
                        if($valid_count > 0) {
                            $dayObj->events = $eventsObj;
                        } else {
                            $dayObj->removed_events = $eventsObj;
                        }
                    }
                }

                /**
                 * Filter the results by shi
                 */
                if($shi != "") {
                    $dayObj->events = array_filter($dayObj->events, function($v) use($shi) {
                        return (strpos($v->teacher_names, $shi) !== false);
                    });

                    // Empty days under a filter are killed
                    if(count($dayObj->events) <= 0) {
                        return null;
                    }
                }

                // Empty days are not sorted
                if(count($dayObj->events) > 0) {
                    /**
                     * Sort the above events and removed events by time
                     */
                    if(isset($dayObj->events)) {
                        usort($dayObj->events, array($this, 'day_event_timefloor_sort'));
                    }

                    if(isset($dayObj->removed_events)) {
                        usort($dayObj->removed_events, array($this, 'day_event_timefloor_sort'));
                    }
                }
            }
        } else {
            $dayObj = null;
        }

        return $dayObj;
    }

	/**
	 * Update an event's details
	 */
	private function updateEventDetails($event_ID, $teachers, $raw_title, $update_timestamp = true) {

		// Sort the array for consistency
		sort($teachers);

        // The database is already set for Japan timezone
        // e.g. $this->db->query("SET @@session.time_zone='+09:00'");	// Japan
        // and
        // The PHP synch.php script already sets the TZ to Tokyo
        // $timezone = "Asia/Tokyo";
        // if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }
        // so the DB and the PHP script should be in sync
        // 2015-07-31
        // This is implemented to make sure the timestamp of one event is always ahead of another event
        $time = new DateTime("now");
        $time->add(new DateInterval('PT5S'));   // Add 5 seconds
        $timestamp = $time->format('Y-m-d H:i:s');

		// Create/update a day and it's status
		$sql = "INSERT INTO `".HOMBUTIMETABLE_TABLE_PREFIX."details` (
					`event_ID`, 
					`teacher_names`, 
					`raw_event_info`)
				VALUES (
					'".$event_ID."',
					'".implode(",", array_map("HombuFormatters::formatTeacherName", $teachers))."', 
					'".$raw_title."')
				ON DUPLICATE KEY UPDATE 
					" .
            (!$update_timestamp ?
                "checked_timestamp = checked_timestamp" :
                "checked_timestamp = '".$timestamp."'"); // ignore duplicates or update the timestamp

            // NOTE: +5 seconds is to ensure that events that are supposed to be updated always come after default events

        if(!$this->db->query($sql) && $this->db->last_error != null && strlen($this->db->last_error) > 5) {
            //$this->logger->debugArray($this->db->debug(false));
            throw new HombuDBInterfaceException("{$sql} failed. Result: *** " . $this->db->last_error . PHP_EOL . " *** AND " . PHP_EOL . print_r($this->db->captured_errors, 1));
        }

        //$this->logger->debugArray($this->db->debug(false));

		// Messages
		if(count($teachers) == 1) {
			$this->logger->info("  Teacher is ".$teachers[0]);
		} else {
			$this->logger->info("  Teachers are <pre>".print_r($teachers, TRUE)."</pre>");
		}
	}
	
	/**
	 * Cache the SQL queries to minimize database connections
	 */
	private function setQueryCacheOptions() {

		if($this->args["cache_SQL_queries"] === true) {

			// (1. You must create this dir. first!)
			// (2. Might need to do chmod 775)

			// Create the zendcache dirs if they don't exist
			if(!is_dir(HOMBU_CACHE_PATH . $this->args["cache_dir"])) {

				if (!@mkdir(HOMBU_CACHE_PATH . $this->args["cache_dir"])) {
	    			$error = error_get_last();
	    			throw new HombuDBInterfaceException($error);
					return;
				}
			}

			// Cache expiry in hours
			// TODO: Should this value be small or large?
			$this->db->cache_timeout = $this->args["cache_hours"];
			$this->db->cache_dir = HOMBU_CACHE_PATH . $this->args["cache_dir"];

			// Global override setting to turn disc caching off
			// (but not on)
			$this->db->use_disk_cache = true;

			// By wrapping up queries you can ensure that the default
			// is NOT to cache unless specified
			$this->db->cache_queries = true;
		}
	}

    public function purgeDBCache() {

        echo "Purging DB cache at " . HOMBU_CACHE_PATH . $this->args["cache_dir"] . PHP_EOL . "<br />";

        if(strlen($this->args["cache_dir"]) > 2) {
            $files = glob(HOMBU_CACHE_PATH . $this->args["cache_dir"] . "/?????*"); // files at least 5 chars long
            foreach ($files as $filename) {
                echo $filename . (unlink($filename) ? " - deleted" : " - error") . PHP_EOL . "<br />";
            }
            echo "<br />Done";
        }
    }

	/**
	 * Check that the database exists
	 */
	private function checkDBExistence() {
		return (bool)count( $this->db->get_row("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".HOMBUTIMETABLE_DB_NAME."'"));
	}

	/**
	 * Check that the database tables exist
	 */
	private function checkDBTablesExistence() {
		// REF: http://forums.mysql.com/read.php?52,126022,213655#msg-213655
		return $this->num_tables <= $this->db->get_var("SELECT COUNT(*) TABLES FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".HOMBUTIMETABLE_DB_NAME."' GROUP BY TABLE_SCHEMA");
	}

	/** 
	 * Create the hombu timetable database
	 */
	private function createDB() {
		$this->db->query("CREATE DATABASE `".HOMBUTIMETABLE_DB_NAME."` DEFAULT CHARACTER SET latin1 COLLATE latin1_general_ci");
	}

	/** 
	 * Create the hombu timetable database tables
	 */
	private function createDBTables() {
		$this->db->query("USE `".HOMBUTIMETABLE_DB_NAME."`");

        // Create the analytics table
        $this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."analytics` (
          `device_ID` int(10) unsigned NOT NULL,
          `access` int(10) unsigned NOT NULL,
          `play_count` int(10) unsigned NOT NULL,
          `filters` char(8) CHARACTER SET ascii NOT NULL,
          `lang` char(2) CHARACTER SET ascii NOT NULL,
          `country` char(30) CHARACTER SET ascii NOT NULL,
          `city` char(30) CHARACTER SET ascii NOT NULL,
          `longitude` double NOT NULL,
          `latitude` double NOT NULL,
          `ip_address` char(45) CHARACTER SET ascii NOT NULL,
          `app_version` char(10) CHARACTER SET ascii NOT NULL,
          `machine_name` char(12) CHARACTER SET ascii NOT NULL,
          `os_version` char(6) CHARACTER SET ascii NOT NULL,
          `updated_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`device_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci");

        // Create a table of deltas for each unique user
        $this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."analytics_deltas` (
          `device_ID` int(10) unsigned NOT NULL default '0',
          `d0` int(10) unsigned NOT NULL default '0',
          `d1` int(10) unsigned NOT NULL default '0',
          `d2` int(10) unsigned NOT NULL default '0',
          `d3` int(10) unsigned NOT NULL default '0',
          `d4` int(10) unsigned NOT NULL default '0',
          `d5` int(10) unsigned NOT NULL default '0',
          `d6` int(10) unsigned NOT NULL default '0',
          `d7` int(10) unsigned NOT NULL default '0',
          `d8` int(10) unsigned NOT NULL default '0',
          `d9` int(10) unsigned NOT NULL default '0',
          `last_delta_col_id` tinyint(4) NOT NULL default '0',
          `access_rate` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY  (`device_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;");

        // Create the variables table
        $this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."vars` (
          `variable_ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `variable_name` varchar(64) CHARACTER SET ascii NOT NULL,
          `variable_value` int(10) unsigned NOT NULL,
          PRIMARY KEY (`variable_ID`),
          UNIQUE KEY `variable_name` (`variable_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."days` (
		  `date` date NOT NULL,
		  `num_events` int(2) NOT NULL,
		  `status` set('".HombuConstants::VALID_DAY."','".HombuConstants::CLOSED_DAY."','".HombuConstants::UNPLANNED_DAY."','".HombuConstants::PURGED_DAY."') NOT NULL,
		  `checked_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  PRIMARY KEY `date` (`date`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci");
		
		$this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."events` (
		  `event_ID` int(11) NOT NULL AUTO_INCREMENT,
		  `date` date NOT NULL,
		  `start_datetime` datetime NOT NULL,
		  `start_datetime_ms` int(11) NOT NULL,
		  `end_datetime` datetime NOT NULL,
		  `startendtype_hash` VARCHAR(8) CHARACTER SET ascii NOT NULL,
		  `event_type` set('".HombuConstants::BEGINNER."','".HombuConstants::CHILDREN."','".HombuConstants::GAKKO."','".HombuConstants::REGULAR."','".HombuConstants::WOMEN."','".HombuConstants::UNKNOWN."') NOT NULL,
		  `floor` TINYINT NOT NULL,
		  `checked_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  PRIMARY KEY (`event_ID`),
		  KEY `date` (`date`),
		  KEY `start_datetime` (`start_datetime`),
		  CONSTRAINT `date_startendtype_hash` UNIQUE (`date`, `startendtype_hash`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".HOMBUTIMETABLE_TABLE_PREFIX."details` (
		  `ID` int(11) NOT NULL AUTO_INCREMENT,
		  `event_ID` int(11) NOT NULL,
		  `raw_event_info` TINYTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
		  `teacher_names` VARCHAR(80) CHARACTER SET ascii NOT NULL,
		  `checked_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY (`ID`),
		  KEY `event_ID` (`event_ID`),
		  CONSTRAINT `event_ID_teacher_names` UNIQUE (`event_ID`, `teacher_names`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");		
	}

    private function stripNonAscii($str) {
        return trim(preg_replace( '/[^[:print:]]/', '', $str));
    }
}

?>