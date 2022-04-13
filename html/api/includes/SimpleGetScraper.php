<?php
/*******************
Create a scraping object that can be customized
-Eric Draken
 ********************/

require_once('curl/curl.php');

class SimpleGetScraper {
    private $cc;
    protected $URL, $params, $referer, $response;

    public function __construct($URL, array $params, $referer) {

        $this->cc = new cURL(FALSE);	// Don't use cookies
        self::rebuildParams($URL, $params, $referer);
    }

    public function rebuildParams($URL, array $params, $referer) {

        $this->referer = $referer;
        $this->URL = $URL;
        $this->params = $params;
        $this->response = "-None-";
    }

    protected function accessCurlObj() {
        return $this->cc;
    }

    public function getResponseCode() {
        return $this->cc->getResponseCode();
    }

    public function scrape() {

        $querystring = '?';
        foreach($this->params as $name => $value) {
            if(!empty($value))
                $querystring .= $name . '=' . $value . '&';
        }

        $querystring = trim(substr($querystring, 0, -1));

        self::accessCurlObj()->setReferer($this->referer);
        $this->response = self::accessCurlObj()->get($this->URL . $querystring);

        return $this->response;
    }

    public function __toString() {
        return $this->response;
    }
}

?>