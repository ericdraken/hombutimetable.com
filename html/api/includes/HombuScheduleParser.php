<?php
/**
 * Parse the hombu schedule
 * Eric Draken, 2012
 * 
 * Parse the scraped schedule meta results and try to 
 * intelligently figure out the floors and class types
 */

 require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
 require_once(__DIR__ . "/HombuException.php");
 require_once(__DIR__ . "/HombuConstants.php");
 require_once(__DIR__ . "/HombuValidators.php");
 require_once(__DIR__ . "/HombuFormatters.php");
 
 class HombuScheduleParserException extends HombuException {}
 
 /**
  * Parse the scraped schedule data
  * @throws HombuScheduleParserException
  */
 class HombuScheduleParser {
 	
	private $params, $shihans, $logger;
	
	/**
	 * Include an optional logger
	 */
	function __construct(HombuLogger $logger) {
		$this->logger = $logger;
		
		$this->shihans = HombuScheduleParams::homuShihansArray();
		$this->params = HombuScheduleParams::hombuParserParams();
	}
	
	public function parseScrapedSchedule(array $response) {

		// Filter the HTML
		$filter_results = $this->filter($response["response"], $this->params["filters"]);

        //$this->logger->debug(print_r($filter_results, true));

		// DEBUG - Confirm the date was given properly
		HombuValidators::hombuDateFormatCheck($response["date"], $this->logger);

		// Extracts the schedule information
		$replace_results = $this->replace($filter_results[1], $this->params["replacers"], $this->params["replacers_names"]);

        //$this->logger->debug(print_r($replace_results, true));

		$results = array(
			"date" => $response["date"],
			"parse_date" => $response["scrape_date"],
			"response_code" => $response["response_code"],
			"lastfilter" => ($filter_results[0] >= 0)?($this->params["filters_names"][$filter_results[0]]):(HombuConstants::ALL_FAILED),
			"entries" => $replace_results
			);

		//$this->logger->debug(print_r($results, true));

		try {
		    $formatted_events = $this->formatEvents($results);
        } catch (HombuException $e) {
            // Return the results in the throw message
            throw new HombuScheduleParserException($e->getMessage() . PHP_EOL . print_r($response, true));
        }

		//$this->logger->debug(print_r($formatted_events, true));

		return $formatted_events;
	}

     /**
      * Helper class to format an array of entries
      */
     public function entryFormater($date_start_ms, $date_end_ms, $title) {

         return array(
             "date" => date("Y-m-d", $date_start_ms),	// SQL date
             "start" => date("Y-m-d H:i", $date_start_ms),
             "end" => date("Y-m-d H:i", $date_end_ms),
             "title" => $title,
             "floor" => $this->guessFloor($title, $date_start_ms),
             "type" => $this->guessType($title),
             "teachers" => $this->extractTeachers($title)
         );
     }

	/////// PRIVATE /////////

	/**
	 * Helper class to format an array of entries
	 */
	private function dayFormater(array $results, $day_status, &$events) {
		
		// Sort the events
		//usort($events, array($this, 'day_event_sort'));
		
		return array(
			"date" => HombuFormatters::hombuToSqlDate($results["date"]),	// Confirm the date is SQL formatted
			"status" => $day_status,
			"events" => $events
			);
	}

	/**
	 * Parse the results and figure out the floor and times
	 */
	private function formatEvents(array $pre_results) {
		
		// Schedule, closed, or still planning?
		if( $pre_results["lastfilter"] == HombuConstants::VALID_DAY )
		{
			$this->logger->info( "This is a VALID day" );
			return $this->dayFormater($pre_results, $pre_results["lastfilter"], $this->validDayParser($pre_results));
		}
		else if($pre_results["lastfilter"] == HombuConstants::UNPLANNED_DAY)
		{
			$status = $this->unplannedDayParser($pre_results);
			$this->logger->info( "This is a {$status} day" );
			$arr = array();
			return $this->dayFormater($pre_results, $status, $arr);
		}
		else if($pre_results["lastfilter"] == HombuConstants::CLOSED_DAY)
		{
			$this->logger->info( "This day is CLOSED" );
			$arr = array();
			return $this->dayFormater($pre_results, $this->closedDayParser($pre_results), $arr);
		}
        else if($pre_results["lastfilter"] == HombuConstants::ALL_FAILED)
        {
            $status = $this->unplannedDayParser($pre_results);
            $this->logger->info( "This is a {$status} day - best guess from FAILED parsing" );
            $arr = array();
            return $this->dayFormater($pre_results, $status, $arr);
        }
		else
		{
			// Display debugging info here as well
			$this->logger->debugArray($pre_results);
			throw new HombuScheduleParserException("Last filter was " . $pre_results["lastfilter"] . " - " . print_r($pre_results, true));
		}	
	}
	
	/**
	 * Deal with closed days
	 */
	private function closedDayParser(array $pre_results) {
		return HombuConstants::CLOSED_DAY;
	}
	
	/**
	 * Deal with unplanned days properly
	 */
	private function unplannedDayParser(array $pre_results) {
		
		//2011.08.05 - Past or future?
		$is_past = FALSE;
		if( strtotime($pre_results["date"]) - strtotime("now") < 0 ) {
			$is_past = TRUE;
		}

		if($is_past) {
			return HombuConstants::PURGED_DAY;
		} else {
			return HombuConstants::UNPLANNED_DAY;
		}		
	}
	
	/**
	 * Parse events for a planned day
	 */
	private function validDayParser(array $pre_results) 
	{
		$events = array();

		// SINGLE_TIME_EVENTS
		if(array_key_exists(HombuConstants::SINGLE_TIME_EVENTS, $pre_results["entries"])) {

			$this->logger->info( "-- Single-time events found --" );

			$ste = $pre_results["entries"][HombuConstants::SINGLE_TIME_EVENTS];
			for($i = 0; $i < count($ste[0]); $i++) {
				
				$event_start_date = HombuFormatters::formatAsYMDHM2($pre_results["date"], $ste[1][$i], $ste[2][$i]);
				$event_end_date = HombuFormatters::formatAsYMDHM2($pre_results["date"], $ste[3][$i], $ste[4][$i]);				

				HombuValidators::hombuDateTimeFormatCheck($event_start_date, $this->logger);
				HombuValidators::hombuDateTimeFormatCheck($event_end_date, $this->logger);

				$event_start_date_ms = strtotime($event_start_date);
				$event_end_date_ms = strtotime($event_end_date);
				
				// 2011.12.12 - Handle events that cross midnight
				// If start_date is after end_date, advance the day of the end date
				$event_end_date_ms = $this->checkAdjustMultiDayEvents($event_start_date_ms, $event_end_date_ms);

                $event_title = trim($ste[5][$i]);

                // Final check for an event with teachers - no teachers, no event
				$tmpEvent = $this->entryFormater($event_start_date_ms, $event_end_date_ms, $event_title);
                if(count($tmpEvent["teachers"]) > 0) {
                    $events[] = $tmpEvent;
                }
			}
		}

		// SINGLE_TIME_EVENTS_REVERSED
		if(array_key_exists(HombuConstants::SINGLE_TIME_EVENTS_REVERSED, $pre_results["entries"])) {

			$this->logger->info( "-- Single-time events found, but time-reversed --" );

			$ster = $pre_results["entries"][HombuConstants::SINGLE_TIME_EVENTS_REVERSED];
			for($i = 0; $i < count($ster[0]); $i++) {

				$event_start_date = HombuFormatters::formatAsYMDHM2($pre_results["date"], $ster[2][$i], $ster[3][$i]);
				$event_end_date = HombuFormatters::formatAsYMDHM2($pre_results["date"], $ster[4][$i], $ster[5][$i]);

				HombuValidators::hombuDateTimeFormatCheck($event_start_date, $this->logger);
				HombuValidators::hombuDateTimeFormatCheck($event_end_date, $this->logger);

				$event_start_date_ms = strtotime($event_start_date);
				$event_end_date_ms = strtotime($event_end_date);

				// 2011.12.12 - Handle events that cross midnight
				// If start_date is after end_date, advance the day of the end date
				$event_end_date_ms = $this->checkAdjustMultiDayEvents($event_start_date_ms, $event_end_date_ms);

				// Force UTF because Hombu uses EUC
				$event_title = HombuFormatters::eucToUtf($ster[1][$i]);

                // Final check for an event with teachers - no teachers, no event
                $tmpEvent = $this->entryFormater($event_start_date_ms, $event_end_date_ms, $event_title);
                if(count($tmpEvent["teachers"]) > 0) {
                    $events[] = $tmpEvent;
                }
			}
		}

		// MULTIPLE_TIMES_EVENTS
		if(array_key_exists(HombuConstants::MULTIPLE_TIMES_EVENTS, $pre_results["entries"])) {

			$this->logger->info( "-- Multiple-time events found --" );

			$mte = $pre_results["entries"][HombuConstants::MULTIPLE_TIMES_EVENTS];
			for($i = 0; $i < count($mte[0]); $i++) {

				// Event 1
		
				$event_start_date1 = HombuFormatters::formatAsYMDHM2($pre_results["date"], $mte[2][$i], $mte[3][$i]);
				$event_end_date1 = HombuFormatters::formatAsYMDHM2($pre_results["date"], $mte[4][$i], $mte[5][$i]);

				HombuValidators::hombuDateTimeFormatCheck($event_start_date1, $this->logger);
				HombuValidators::hombuDateTimeFormatCheck($event_end_date1, $this->logger);

				$event_start_date_ms1 = strtotime($event_start_date1);
				$event_end_date_ms1 = strtotime($event_end_date1);

				// 2011.12.12 - Handle events that cross midnight
				// If start_date is after end_date, advance the day of the end date
				$event_end_date_ms1 = $this->checkAdjustMultiDayEvents($event_start_date_ms1, $event_end_date_ms1);


				// Event 2

				$event_start_date2 = HombuFormatters::formatAsYMDHM2($pre_results["date"], $mte[7][$i], $mte[8][$i]);
				$event_end_date2 = HombuFormatters::formatAsYMDHM2($pre_results["date"], $mte[9][$i], $mte[10][$i]);

				HombuValidators::hombuDateTimeFormatCheck($event_start_date2, $this->logger);
				HombuValidators::hombuDateTimeFormatCheck($event_end_date2, $this->logger);

				$event_start_date_ms2 = strtotime($event_start_date2);
				$event_end_date_ms2 = strtotime($event_end_date2);

				// 2011.12.12 - Handle events that cross midnight
				// If start_date is after end_date, advance the day of the end date
				$event_end_date_ms2 = $this->checkAdjustMultiDayEvents($event_start_date_ms2, $event_end_date_ms2);

				
				// Title
				
				// Force UTF because Hombu uses EUC
				$event_title = HombuFormatters::eucToUtf($mte[11][$i]);

                // Final check for an event with teachers - no teachers, no event
                $tmpEvent = $this->entryFormater($event_start_date_ms1, $event_end_date_ms1, $event_title);
                if(count($tmpEvent["teachers"]) > 0) {
                    $events[] = $tmpEvent;
                }

                // Final check for an event with teachers - no teachers, no event
                $tmpEvent = $this->entryFormater($event_start_date_ms2, $event_end_date_ms2, $event_title);
                if(count($tmpEvent["teachers"]) > 0) {
                    $events[] = $tmpEvent;
                }
			}
		}

		return $events;
	}

    // 2014.11.25 - This has been broken for 3 years!! Just fixed it. Wow
	// 2011.12.12 - Handle events that cross midnight
	// If start_date is after end_date, advance the day of the end date
	private function checkAdjustMultiDayEvents($event_start_date_ms, $event_end_date_ms) {
	
		// Move the end date ahead one day if it crosses midnight
		if($event_start_date_ms > $event_end_date_ms) {
            $event_end_date_ms = strtotime("+1 day", $event_end_date_ms);
		}
	
		return $event_end_date_ms;
	}	

	/**
	 * Try to figure out what the class type is
	 */
	public function guessType($title) {
		
		// Scan the list of types for a match
		foreach($this->params["type_filters"] AS $key => $val) {
			if(preg_match($val, $title) > 0) {
				return $key;
			}
		}

		return HombuConstants::UNKNOWN;
	}
	
	/**
	 * Try to figure out which shihan(s) are part of this event
	 * @return an array with a teacher array
	 */
	public function extractTeachers($title) {
		
		//$teachers = array();
		$teachers_quick = array();
		
		//mb_regex_encoding('UTF-8');
		//mb_internal_encoding("UTF-8");
		//$v = mb_split(' |　|、', $title);		// Split on space, Japanese space, japanese comma
		
		// Scan the list of shihans for a match
		foreach($this->shihans AS $key => $val) {
			if(preg_match('%'.$key.'%i', $title) > 0) {
				//$teachers[] = array(
				//	"name" => array("en" => $val[2], "ja" => $val[3]),
				//	"desc" => array("en" => $val[0], "ja" => $val[1])
				//);
				
				$teachers_quick[] = $val[2];
			}
		}

		return $teachers_quick;
	}
	
	// TODO: What to do if the floor is unknown?
	private function guessFloor($title, $start_date_as_ms) {

		//$this->logger->debug("Trying to guess the floor for '{$title}' on " . date("Y/m/d H:i", $start_date_as_ms));

		// 0 == Sunday, 1 == Monday, etc.
		$dw = date("w", $start_date_as_ms);
		if(empty($start_date_as_ms) || $dw < 0 || $dw > 6) {
			throw new HombuScheduleParserException("{$dw} is not in range.");
		}
		
		// Time
		$hr = date("H", $start_date_as_ms);
		if(empty($start_date_as_ms) || $hr < 6) {	// Hombu events start at 6:30 AM
			throw new HombuScheduleParserException($hr . " is not in range.");
		}

		// Children are always on the second floor
		if(preg_match($this->params["floor_filters"][0], $title, $matches))
			return HombuConstants::HOMBU_2ND_FLOOR;

		// Gakko
		if(preg_match($this->params["floor_filters"][1], $title, $matches))
			return HombuConstants::HOMBU_4TH_FLOOR;

		// Regular
		// 2012.03.30 added
		if(preg_match($this->params["floor_filters"][2], $title, $matches)) {
			//if($dw == 6 && $hr == 15) {
			//	return HombuConstants::HOMBU_2ND_FLOOR;	// Saturdays regular (Yokota?) at 3 PM (15:00) are on the second floor
			//} else {
				return HombuConstants::HOMBU_3RD_FLOOR;	// Otherwise regular classes are on the 3rd floor
			//}
		}
		
		// Beginner
		if(preg_match($this->params["floor_filters"][3], $title, $matches)) {

			// Sunday?
			if($dw == 0) {
				return HombuConstants::HOMBU_4TH_FLOOR;		// Sunday beginners are on the 4th floor
			} else {
				return HombuConstants::HOMBU_2ND_FLOOR;		// All other days not children are on the 2nd floor
			}
		}

		return HombuConstants::ALL_FLOORS;	// Sure, whatever
	}		
	
	private function filter($str, array $filters) {

		// Result holder
		$result = array(-1, $str);

		for ($i = 0; $i < sizeof($filters); $i++)
		{
			if(preg_match($filters[$i], $result[1], $matches, PREG_OFFSET_CAPTURE)) {

				$result[0] = $i;
				$result[1] = $matches[1][0];

                //print_r($result);
			}
		}

		return $result;
	}


	private function replace($str, array $replacers, array $replacers_names) {

		// Set the initial entry of the array
		$matches = array();

		for ($i = 0; $i < sizeof($replacers); $i++)
		{
			if(preg_match_all($replacers[$i], $str, $matches_tmp)) {

				$matches[$replacers_names[$i]] = $matches_tmp;

				// Remove this entry so it is not matches again
				$str = preg_replace($replacers[$i], "", $str);
			}
		}

		return $matches;
	}	

 }
 
?>