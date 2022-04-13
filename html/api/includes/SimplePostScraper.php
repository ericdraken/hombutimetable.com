<?php
/*******************
Create a scraping object that can be customized
-Eric Draken
********************/

require_once('curl/curl.php');

class SimplePostScraper {
	private $cc;
	protected $URL, $POSTs, $referer, $response;

	public function __construct($URL, array $POSTs, $referer) {

		$this->cc = new cURL(FALSE);	// Don't use cookies
		self::rebuildParams($URL, $POSTs, $referer);
    }

	public function rebuildParams($URL, array $POSTs, $referer) {

		$this->referer = $referer;
		$this->URL = $URL;
    	$this->POSTs = $POSTs;
    	$this->response = "-None-";
	}

	protected function accessCurlObj() {
		return $this->cc;
	}

	public function getResponseCode() {
		return $this->cc->getResponseCode();
	}

	public function scrape() {

		$post_data = '';
		foreach($this->POSTs as $name => $value) {
			if(!empty($value))
				$post_data .= $name . '=' . $value . '&';
		}

		$post_data = trim(substr($post_data, 0, -1));

		self::accessCurlObj()->setReferer($this->referer);
		$this->response = self::accessCurlObj()->post($this->URL, $post_data);

		return $this->response;
	}

	public function __toString() {
		return $this->response;
	}
}

?>