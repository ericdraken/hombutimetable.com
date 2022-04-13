<?php
/**
 * Test suite for the hombu validators
 * Eric Draken, 2012
 */

 require_once(__DIR__ . "/../HombuValidators.php");
 require_once(__DIR__ . "/../HombuLogger.php");
 
 class HombuValidatorsTest {
 	
	protected $logger;
	
	private $good_event, $good_day;
	
	function __construct(HombuLogger $logger) {
		$this->logger = $logger;
		
		// Create a sample event
		$this->good_event = array(
			"date" => "2012-07-12",
			"start" => "2012-07-12 06:30",
			"end" => "2012-07-12 07:30",
			"title" => "一般 植芝",
			"floor" => "HOMBU_3RD_FLOOR",
			"type" => "REGULAR",
			"teachers" => array("Ueshiba")
		);
	
		$this->good_day = array(
			"date" => "2012-07-12",
			"status" => "VALID_DAY",
			"events" => array($this->good_event)
			);		
	}
	
	/////// TESTING ///////
	
	public function test() {
		
		$this->logger->info("Starting ".__CLASS__." tests now.");
		
		$this->test_dateRangeCheck();
		$this->test_hombuDateFormatCheck();
		$this->test_sqlDateFormatCheck();
		$this->test_hombuDateTimeFormatCheck();
		$this->test_validateHombuEvent();
		$this->test_validateHombuDay();
	}	
	
	/**
	 * Check that an entire day structure is correct
	 */
	private function test_validateHombuDay() {

		// Good day format
		try {
			assert(HombuValidators::validateHombuDay($this->good_day, $this->logger) == true); 
		} catch(HombuValidationException $e) {
			assert(false);
		} catch(Exception $e) {
			assert(false);
		};

		// No events and CLOSED_DAY
		$good_day2 = unserialize(serialize($this->good_day));
		$good_day2["events"] = array();
		$good_day2["status"] = HombuConstants::CLOSED_DAY;
		
		try {
			assert(HombuValidators::validateHombuDay($good_day2, $this->logger) == true); 
		} catch(HombuValidationException $e) {
			assert(false);
		} catch(Exception $e) {
			assert(false);
		};	
		
		///////


		// Bad date		
		$bad_day = unserialize(serialize($this->good_day));
		$bad_day["date"] = "12/7/12";
		
		try {
			assert(HombuValidators::validateHombuDay($bad_day, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};							

		// No events and VALID_DAY
		$bad_day = unserialize(serialize($this->good_day));
		$bad_day["events"] = array();
		$bad_day["status"] = HombuConstants::VALID_DAY;
		
		try {
			assert(HombuValidators::validateHombuDay($bad_day, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};	

		// Events present and CLOSED_DAY
		$bad_day = unserialize(serialize($this->good_day));
		$bad_day["events"] = array($this->good_event);
		$bad_day["status"] = HombuConstants::CLOSED_DAY;
		
		try {
			assert(HombuValidators::validateHombuDay($bad_day, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};	

		// Day with missing key
		$bad_day = unserialize(serialize($this->good_day));
		unset($bad_day["events"]);
		
		try {
			assert(HombuValidators::validateHombuDay($bad_day, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};	
	}
	
	/**
	 * Check that the event validator is working
	 */
	private function test_validateHombuEvent() {
		
		// Good event format
		try {
			assert(HombuValidators::validateHombuEvent($this->good_event, $this->logger) == true); 
		} catch(HombuValidationException $e) {
			assert(false);
		} catch(Exception $e) {
			assert(false);
		};
		
		// Added structure should be okay
		$good_event2 = unserialize(serialize($this->good_event));
		$good_event2["teachers_diff"] = array("Hino");
		
		try {
			assert(HombuValidators::validateHombuEvent($good_event2, $this->logger) == true); 
		} catch(HombuValidationException $e) {
			assert(false);
		} catch(Exception $e) {
			assert(false);
		};		
		
		
		///////


		// Bad start time		
		$bad_event = unserialize(serialize($this->good_event));
		$bad_event["start"] = "6:30";
		
		try {
			assert(HombuValidators::validateHombuEvent($bad_event, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};				
		
		// Bad title
		$bad_event = unserialize(serialize($this->good_event));
		$bad_event["title"] = "";
		
		try {
			assert(HombuValidators::validateHombuEvent($bad_event, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};		
		
		// Bad teachers
		$bad_event = unserialize(serialize($this->good_event));
		$bad_event["teachers"] = array();
		
		try {
			assert(HombuValidators::validateHombuEvent($bad_event, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};					

		// Missing structure
		$bad_event = unserialize(serialize($this->good_event));
		unset($bad_event["teachers"]);
		
		try {
			assert(HombuValidators::validateHombuEvent($bad_event, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};	
	}

	/**
	 * Check that the date format validator is working correctly
	 */
	private function test_hombuDateTimeFormatCheck() {

		assert(HombuValidators::hombuDateTimeFormatCheck("2099/12/31 00:00", $this->logger) == true);
		assert(HombuValidators::hombuDateTimeFormatCheck("1900/12/31 23:59", $this->logger) == true);
		
		// Bad date - 12/12/31
		try {
			assert(HombuValidators::hombuDateTimeFormatCheck("12/12/31", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad date - 2011/02/30 00:00 - not a real date
		try {
			assert(HombuValidators::hombuDateTimeFormatCheck("2011/02/30 00:00", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad time - 2012/12/31 0:00
		try {
			assert(HombuValidators::hombuDateTimeFormatCheck("2012/12/31 0:00", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};	
		
		// Bad date - 12/31/2012
		try {
			assert(HombuValidators::hombuDateTimeFormatCheck("12/31/2012", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};
	}
	
	/**
	 * Check that the date format validator is working correctly
	 */
	private function test_hombuDateFormatCheck() {

		assert(HombuValidators::hombuDateFormatCheck("2099/12/31", $this->logger) == true);
		assert(HombuValidators::hombuDateFormatCheck("1900/12/31", $this->logger) == true);
		
		// Bad date - 12/12/31
		try {
			assert(HombuValidators::hombuDateFormatCheck("12/12/31", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad date - 12/31/2012
		try {
			assert(HombuValidators::hombuDateFormatCheck("12/31/2012", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};
	}

	/**
	 * Check that the SQL date format validator is working correctly
	 */
	private function test_sqlDateFormatCheck() {

		assert(HombuValidators::sqlDateFormatCheck("2099-12-31", $this->logger) == true);
		assert(HombuValidators::sqlDateFormatCheck("1900-12-31", $this->logger) == true);
		
		// Bad date - 12-12-31
		try {
			assert(HombuValidators::sqlDateFormatCheck("12-12-31", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad date - 2011-02-30- not a real date
		try {
			assert(HombuValidators::hombuDateTimeFormatCheck("2011-02-30", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad date - 12-31-2012
		try {
			assert(HombuValidators::sqlDateFormatCheck("12-31-2012", $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};
	}
	
 	/**
	 * Check that the date range makes sense
	 */
	private function test_dateRangeCheck() {
		
		$lim = 1;

		// Today only
		assert(HombuValidators::dateRangeCheck(0, 0, $lim, $this->logger) == true);
		
		// Bad limit
		try {
			assert(HombuValidators::dateRangeCheck(0, 0, -1, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};
		
		// Bad starting date
		try {
			assert(HombuValidators::dateRangeCheck($lim+1, 0, $lim, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad starting date 2
		try {
			assert(HombuValidators::dateRangeCheck(0-$lim-1, 0, $lim, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad ending date
		try {
			assert(HombuValidators::dateRangeCheck(0, $lim+1, $lim, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad ending date 2
		try {
			assert(HombuValidators::dateRangeCheck(0, 0-$lim-1, $lim, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};

		// Bad ending date 3
		// The ending date must be the same or greater than the start date
		try {
			assert(HombuValidators::dateRangeCheck(0, -1, $lim, $this->logger) == false); 
		} catch(HombuValidationException $e) {
			assert(true);
		} catch(Exception $e) {
			assert(false);
		};
	}
}

?>