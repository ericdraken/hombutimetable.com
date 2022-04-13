<?php
/**
 * Anaylyze the events returned from both the Engish
 * and Japanese versions of the schedule parser
 * and compare them. If the English version doesn't
 * match the Japanese version, then use the Japanese
 * version instead.
 */

 // Includes
 require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
 require_once(__DIR__ . '/HombuException.php');	// Base exception class
  
 class HombuEventsAnalyzerException extends HombuException {} 
 
 class HombuEventsAnalyzer {
 	
	private $logger;
	
	/**
	 * Include an optional logger
	 */
	function __construct(HombuLogger $logger) {
		$this->logger = $logger;
	}	
	
	/**
	 * Return a single object that has a good chance of being the most accurate
	 */
	public function analyzeEvents(array &$e_data, array &$j_data) {
		
		// Deep copy of array structure
		$final_data = unserialize(serialize($j_data));
		
		// Confirm the status of both days
		if($e_data["status"] == $j_data["status"]) {
			
			// Confirm the number of events match
			if(count($e_data["events"]) == count($j_data["events"])) {
				
				/**
				 * Each event is sorted, so they should be in sync
				 */
				for($i = 0; $i < count($e_data["events"]); $i++) {

					// Go through each event looking for a descrepency
					$diff = array_diff($j_data["events"][$i]["teachers"], $e_data["events"][$i]["teachers"]);
					if(count($diff) > 0) {
						
						$this->logger->info("Differences detected between the English and Japanese events: {$diff}");
						
						// Add differences
						$final_data["events"][$i]["teachers_diff"] = $diff;
					}
				}
				
			/**
			 * The number of events don't match
			 */	
			} else {
				
				$this->logger->info("Number of events don't match: ".count($e_data["events"])." vs ".count($j_data["events"]));
				
				// TODO: What do to?
				// Answer: Just accept the larger data
				//throw new HombuEventsAnalyzerException("Number of events don't match: ".count($e_data["events"])." vs ".count($j_data["events"]), $this->logger);

                if(count($e_data["events"]) > count($j_data["events"])) {
                    // Deep copy of English data array structure
                    $final_data = unserialize(serialize($e_data));
                }

                // else, already using the japanese data
			}
			
		} else {
			// TODO: What do to?
			throw new HombuEventsAnalyzerException("Day statuses don't match: ".$e_data["status"]." vs ".$j_data["status"], $this->logger);
		}
		
		return $final_data;
	} 
 }
?>