<?php
/*
Name: Scrape Hombu's schedule
Description: Scrape Hombu's schedule
Author: Eric Draken

2012.07.10 - Simplification
2011.07.20 - Heavy modification for Japanese support
2011.07.12 - Start
*/

////////////////////////////////////////////////////
require_once(__DIR__ . '/SimpleGetScraper.php');
require_once(__DIR__ . '/HombuException.php');
require_once(__DIR__ . '/HombuValidators.php');
require_once(__DIR__ . '/HombuScheduleParams.php');

 class HombuScheduleScraperException extends HombuException {}
	
/**
 * Extends the scraper object
 * @uses HombuScheduleParams
 * @throws HombuScheduleScraperException
 */
class HombuScheduleScraper extends SimpleGetScraper {

	private $logger;

	public function __construct(HombuLogger $logger) {
		$this->logger = $logger;
		parent::__construct(NULL, array(), NULL);
    }
	
	// Override and protect this
	public function scrape() {
		throw new HombuScheduleScraperException("Don't call scrape() from this inherited class - it won't work");
	}

	/**
	 * Scrape the schedule for the given date and language
	 * @param $hombu_date - 1900/01/01 through 2099/12/31
	 * @return an array
	 */
    public function scrapeSchedule($hombu_date, $is_japanese = true) {

		$this->rebuildHombuParams($hombu_date, $is_japanese);

		// Set a good timeout
		$this->accessCurlObj()->setTimeout(50);
		$num_tries = $num_tries_limit = 5;
        $min_html_chars_required = 200;

		/**
		 * Try to scrape the page a few times if there are timeouts
		 */
		while($num_tries > 0) {		
			$response = parent::scrape();
			$responsecode = (int)$this->getResponseCode();
			$num_tries--;
	
			$filter_results = "";
			$replace_results = array();
            if($responsecode >= 200 && $responsecode < 400 && $response && strlen($response) > $min_html_chars_required) {
		 		return array(
		 			"date" => $hombu_date, 
		 			"scrape_date" => strtotime("now"),
		 			"response_code" => $responsecode, 
		 			"response" => $response);
			} else if($responsecode > 0 && $response && strlen($response) > $min_html_chars_required) {
				throw new HombuScheduleScraperException("Hombu scraper for '{$hombu_date}' response code was {$responsecode} with response: {$response}. ");
			} else {
				
				// Chill for 5 seconds and try again
				sleep(5);
				continue;
			}
		}
		
		throw new HombuScheduleScraperException("Hombu scraper failed {$num_tries_limit} tries on '{$hombu_date}'");
    }


	//////// PRIVATE ///////
	
	private function rebuildHombuParams($hombu_date, $is_japanese) {
		
		HombuValidators::hombuDateFormatCheck($hombu_date, $this->logger);
		
		$params = HombuScheduleParams::hombuScraperParams();
		$url = $params["url"];
		$ref = $params["referer"];		
		
		if($is_japanese == true) {
			$querydata = array(
				"lang" => "JP",
				"date" => urlencode($hombu_date)
			);				
		} else {
            $querydata = array(
				"lang" => "EN",
				"date" => urlencode($hombu_date)
			);			
		}

		$this->rebuildParams($url, $querydata, $ref);
	}
	
}


?>