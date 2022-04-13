<?php
/**
 * Collection of validation functions
 */

 require_once(__DIR__ . '/HombuException.php');	// Base exception class
 
 class HombuValidationException extends HombuException {}
 
 /**
  * Static validators
  */
 class HombuValidators {
 
	static protected $validDayTemplate = array(
		"date" => "%(?:19|20)[0-9]{2}-(?:0[1-9]|1[012])-(?:0[1-9]|[12][0-9]|3[01])%",	// SQL format
		"status" => "%[A-Z1-4_]{5,}%",
		"events" => array()
		);
 
 	static protected $validEventTemplate = array(
 		"date" => "%(?:19|20)[0-9]{2}-(?:0[1-9]|1[012])-(?:0[1-9]|[12][0-9]|3[01])%",	// SQL format
		"start" => "%[0-9]{2}\:[0-9]{2}%",			// 00:00
		"end" => "%[0-9]{2}\:[0-9]{2}%",			// 00:00
		"title" => "%.{5,}%",						// Any string
		"floor" => "%[1-4]%",						// 4
		"type" => "%[A-Z1-4_]{5,}%",				// WOMEN
		"teachers" => array()
	); 
 
 	/**
	 * Validate a day and events inside the structure
	 */
	static public function validateHombuDay(array &$day, &$logger = null) {
		
		// Check for differences in the array structure first
		if( count(array_diff_key(self::$validDayTemplate, $day)) == 0 ) {
		
			// Check formatting of each element next
			foreach (self::$validDayTemplate as $key => &$value) {
				if(gettype($value) == "string" ) {
					// Do regex check
					if(preg_match(self::$validDayTemplate[$key], $day[$key]) == 0) {
						throw new HombuValidationException("Hombu day array pattern match failed on {$key} with {$day[$key]} given.\n<br /><pre>" . print_r($day, true) . "</pre>", $logger);
					} 					
				} else if(gettype($value) == "array" && gettype($value) == gettype($day[$key])) {
					
					// A valid day should have events
					if(count($day[$key]) <= 0 && $day["status"] == HombuConstants::VALID_DAY) {
						throw new HombuValidationException("Hombu valid day has no events.\n<br /><pre>" . print_r($day, true) . "</pre>", $logger);

					// Closed days, purged days and unplanned days have no events
					} else if(count($day[$key]) > 0 && $day["status"] != HombuConstants::VALID_DAY) {
						throw new HombuValidationException("Hombu non-valid day should have no events.\n<br /><pre>" . print_r($day, true) . "</pre>", $logger);	
	
					} else {
						// Validate each event	
						foreach ($day[$key] as $key2 => &$value2) {
							self::validateHombuEvent($value2);
						}
					}
				} else {
					// General error
					throw new HombuValidationException("Unknown day validation error. Type mismatch?\n<br /><pre>" . print_r($day, true) . "</pre>", $logger);
				}
			}
		} else {
			throw new HombuValidationException("Hombu day elements mismatched or missing.\n<br /><pre>" . print_r($day, true) . "</pre>", $logger);
		}
		
		return true;		
	}
 
 	/**
	 * Confirm that the events array is well-formatted according to the above template
	 */
 	static public function validateHombuEvent(array &$event, &$logger = null) {
 		
		// Check for differences in the array structure first
		if( count(array_diff_key(self::$validEventTemplate, $event)) == 0 ) {
		
			// Check formatting of each element next
			foreach (self::$validEventTemplate as $key => $value) {
				if(gettype($value) == "string" ) {
					// Do regex check
					if(preg_match(self::$validEventTemplate[$key], $event[$key]) == 0) {
						throw new HombuValidationException("Event array pattern match failed on {$key} with {$event[$key]} given.\n<br /><pre>" . print_r($event, true) . "</pre>", $logger);
					} 					
				} else if(gettype($value) == "array" && gettype($value) == gettype($event[$key])) {
					// Do array size check
					if(count($event[$key]) <= 0) {
						throw new HombuValidationException("Event array has no teachers.\n<br /><pre>" . print_r($event, true) . "</pre>", $logger);
					}
				} else {
					// General error
					throw new HombuValidationException("Unknown event validation error. Type mismatch?\n<br /><pre>" . print_r($event, true) . "</pre>", $logger);
				}
			}
		} else {
			throw new HombuValidationException("Event array elements mismatched or missing.\n<br /><pre>" . print_r($event, true) . "</pre>", $logger);
		}
		
		return true;
 	} 
 
 	/**
	 * Confirm the date range is correct when syncing hombu days
	 */
	static public function dateRangeCheck($start, $end, $lim, &$logger = null) {
		
		if($lim < 0) {
			throw new HombuValidationException("Limit of {$lim} doesn't make sense");
		}
		
		if((int)$start > $end) {
			throw new HombuValidationException("Ending date '{$end}' exceeds starting date '{$start}.'", $logger);
		}

		if((int)$start > $lim || (int)$start < (-1)*$lim) {
			throw new HombuValidationException("Starting date '{$start}' exceeds the date range limit of {$lim} days.", $logger);
		}

		if((int)$end > $lim || (int)$end < (-1)*$lim) {
			throw new HombuValidationException("Ending date '{$end}' exceeds the date range limit of {$lim} days.", $logger);
		}
		
		return true;
	} 	
	
	/**
	 * Confirm the format of the hombu date
	 *  Date yyyy/mm/dd
	 *	1900/01/01 through 2099/12/31
	 */
	static public function hombuDateFormatCheck($hombu_date, &$logger = null) {
		

		//Matches invalid dates such as February 31st
		$pattern = '%^((?:19|20)[0-9]{2})\/(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])$%';
	
		if(!preg_match($pattern, $hombu_date, $matches)) {
			throw new HombuValidationException("Invalid date supplied, was '{$hombu_date}'.", $logger);
		} else if(!checkdate( $matches[2], $matches[3], $matches[1])) {
			throw new HombuValidationException("Date is formatted correctly, but is not a real date - was '{$hombu_datetime}'.", $logger);
		}
		
		return true;
	}	

	/**
	 * Confirm the format of the hombu date
	 *  Date yyyy/mm/dd hh:mm
	 *	1900/01/01 through 2099/12/31
	 */
	static public function hombuDateTimeFormatCheck($hombu_datetime, &$logger = null) {

		//Matches invalid dates such as February 31st
		$pattern = '%^((?:19|20)[0-9]{2})\/(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01]) [0-2][0-9]\:[0-5][0-9]$%';
	
		if(!preg_match($pattern, $hombu_datetime, $matches)) {
			throw new HombuValidationException("Invalid date supplied, was '{$hombu_datetime}'.", $logger);
		} else if(!checkdate( $matches[2], $matches[3], $matches[1])) {
			throw new HombuValidationException("Date is formatted correctly, but is not a real date - was '{$hombu_datetime}'.", $logger);
		}
		
		return true;
	}

	/**
	 * Confirm the format of the sql date
	 *  Date yyyy-mm-dd
	 *	1900-01-01 through 2099-12-31
	 */
	static public function sqlDateFormatCheck($date, &$logger = null) {
		
		//Matches invalid dates such as February 31st
		$pattern = '%^((?:19|20)[0-9]{2})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$%';
	
		if(!preg_match($pattern, $date, $matches)) {
			throw new HombuValidationException("Invalid SQL date supplied, was '{$date}'.", $logger);
		} else if(!checkdate( $matches[2], $matches[3], $matches[1])) {
			throw new HombuValidationException("Date is SQL formatted correctly, but is not a real date - was '{$date}'.", $logger);
		}
		
		return true;
	}

	/**
	 * Confirm the format of the hombu date
	 *  Date yyyy/mm/dd hh:mm
	 *	1900/01/01 through 2099/12/31
	 */
	static public function sqlDateTimeFormatCheck($sql_datetime, &$logger = null) {

		//Matches invalid dates such as February 31st
		$pattern = '%^((?:19|20)[0-9]{2})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01]) [0-2][0-9]\:[0-5][0-9]$%';
	
		if(!preg_match($pattern, $sql_datetime, $matches)) {
			throw new HombuValidationException("Invalid SQL datetime supplied, was '{$sql_datetime}'.", $logger);
		} else if(!checkdate( $matches[2], $matches[3], $matches[1])) {
			throw new HombuValidationException("SQL datetime is formatted correctly, but is not a real date - was '{$sql_datetime}'.", $logger);
		}
		
		return true;
	}
 }

?>