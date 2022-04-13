<?php

/**
 * Hombu Sync Controller
 * Eric Draken, 2012
 * 
 * This object is the public interface to accept requests to sync
 * certain days of the hombu schedule. This controls the scraping,
 * parsing and DB actions of the hombu timetable
 */

 // Includes
 require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
 require_once(__DIR__ . '/HombuException.php');	    // Base exception class
 require_once(__DIR__ . '/HombuValidators.php');
 require_once(__DIR__ . '/HombuScheduleScraper.php');
 require_once(__DIR__ . '/HombuScheduleParser.php');
 require_once(__DIR__ . '/HombuEventsAnalyzer.php');
 require_once(__DIR__ . '/HombuDBInterface.php');
 require_once(__DIR__ . '/HombuDefaultSchedule.php');


 class HombuSyncException extends HombuException {}
 
 /**
  * HombuSyncController class
  * @throws HombuSyncException
  */
 class HombuSyncController {
 	
	// Params
	protected $date_range_limit = 60;	// Do not sync over 60 days in the past or future
	protected $logger = null;

	/**
	 * Include a logger
	 */
	function __construct(HombuLogger $logger) {
		$this->logger = $logger;
	}
	
	function __destruct() {
		
	}
	
	/**
	 * Scrape and sync the given day to the database
	 * @param $start: 0 = today, 1 = tomorrow, -1 = yesterday
	 * @param $end: 0 = today, 1 = tomorrow, -1 = yesterday
	 * @param delay between site scrapes in seconds
	 */
	function syncDays($start = 0, $end = NULL, $delay = 5) {

		if(!isset($end)) {
			$end = $start;
		}
		
		// Confirm within range
		HombuValidators::dateRangeCheck($start, $end, $this->date_range_limit, $this->logger);
		
		$hss = new HombuScheduleScraper($this->logger);
		$hsp = new HombuScheduleParser($this->logger);
		$hea = new HombuEventsAnalyzer($this->logger);
		$hdb = new HombuDBInterface(array(), $this->logger);
        $hds = new HombuDefaultSchedule($this->logger);

        try {
            // Load the default schedules before syncing the scraped data
            $defaultSchedulesArray = $hds->getDefaultSchedules();
        } catch(HombuException $e) {
            $this->logger->error($e->getMessage());
            $defaultSchedulesArray = array();   // Present the error, but don't trash the process
        }

		// Sync these days
		for($i = $start; $i <= $end; $i++){
			$new_date = date(strtotime("+$i days"));
			
			$this->logger->info( "<strong>(" . $i . " of " . $end .  ") " . date("D Y/m/d",strtotime("+$i days")) . "</strong>");

			try {
                $this->logger->info( ">> Checking the English site now..." );
				$response = $hss->scrapeSchedule(date("Y/m/d", $new_date), false);
    			$e_data = $hsp->parseScrapedSchedule($response);

                $this->logger->info( ">> Checking the Japanese site now..." );
                $response = $hss->scrapeSchedule(date("Y/m/d", $new_date), true);
                $j_data = $hsp->parseScrapedSchedule($response);

			} catch(HombuException $e) {
				
				// TODO
				throw new HombuSyncException($e);
			}

			/**
			 * TODO: Compare the English events to the Japanese events
			 * and decide which is more likely to be correct
			 */
			$dayData = $hea->analyzeEvents($e_data, $j_data);
			//$this->logger->debugArray($dayData);

            // 2014-11-04 - Add the default lessons here only if the day is valid.
            // Remember, the server is using JST, so the date in ms reflects that as a base.
            // Default lessons are added outside of the found parsed lessons results
            // in their own array so as not to confuse the found events counter assert checks.
            // Also, default events are only included if a matching lesson is found which is not default
            $dayData["defaultEvents"] = array();
            if($dayData["status"] == HombuConstants::VALID_DAY) {

                // Find the first valid default schedule
                foreach ($defaultSchedulesArray as $scheduleObject) {

                    $validFromDateMs = $scheduleObject->validFromDateMS;
                    $validUntilDateMs = $scheduleObject->validUntilDateMS;
                    $schedule = $scheduleObject->schedule;

                    // Sanity check to catch a bad array
                    Assert($validUntilDateMs > 1000 && $validFromDateMs > 1000);

                    $dayDateMS = strtotime($dayData["date"]);
                    if($validUntilDateMs > $dayDateMS && $validFromDateMs <= $dayDateMS) {

                        // Now find the day of the week in this valid default schedule
                        $isoDayOrdinal = date("N", $new_date);

                        // Next, get the valid day of the week default schedule
                        $defaultDaySchedule = $schedule[$isoDayOrdinal];

                        // Find the default lesson for this day and time
                        foreach ($dayData["events"] as $event) {

                            // Get the hash for each lesson
                            $eventHash = HombuFormatters::hashLesson($event["start"], $event["end"], $event["type"]);
                            $foundDefaultLesson = $defaultDaySchedule[$eventHash];
                            if($foundDefaultLesson && count(array_diff($event["teachers"], $foundDefaultLesson[1])) > 0) {
                                $defaultEvent = $event; // Copy this array

                                // Change the event title and the teachers
                                $defaultEvent["teachers"] = $foundDefaultLesson[1];
                                $defaultEvent["title"] = $foundDefaultLesson[0];

                                // Add this default lesson to the defaultEvents section of the Day object array
                                $dayData["defaultEvents"][] = $defaultEvent;
                            }
                        }

                        break;
                    }
                }
            }

			/**
			 * Send the events to the DB for update/storage
			 */
            try {
			    $hdb->addUpdateHombuDay($dayData);
            } catch(HombuDBInterfaceException $e) {

                // MySQL server has timed out or gone away? Try again
                $hdb = null;
                $hdb = new HombuDBInterface(array(), $this->logger);
                $hdb->addUpdateHombuDay($dayData);
            }
			/**
			 * TODO: Deal with events that had their time slightly changed e.g. 06:31 --> 06:30
			 * Perhaps check for overlapping events of the same type, and force the start/end times
			 * to match those of the most recent details
			 * SOLVED: Using hash on start hour and end hour to catch this
			 */
			
			/**
			 * TODO: Detect deleted events
			 * SOLVED: Save the number of events found on each update, and only retrieve
			 * that number from the most recent details entries
			 */

			//$this->logger->debugArray( $hdb->getHombuDayEvents(date("Y-m-d", $new_date)) );
			 
			$this->logger->info( "Checked!" );
			
			if($i != $end && $delay > 0 && $delay <= 60) {
				sleep($delay);
			}
		}
		
		$this->logger->info( "FINISHED!" );
	}

 }
 
 
 

?>