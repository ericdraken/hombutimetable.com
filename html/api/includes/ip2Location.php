<?php

require_once('curl/curl.php');

final class ip2location_lite{
    protected $errors = array();
    protected $service = 'api.ipinfodb.com';
    protected $version = 'v3';
    protected $apiKey = 'xxxxx';
    protected $curl;

    public function __construct(){
        $this->curl = new cURL(FALSE);	// Don't use cookies
    }

    public function __destruct(){}

    public function setKey($key){
        if(!empty($key)) $this->apiKey = $key;
    }

    public function getError(){
        return implode("\n", $this->errors);
    }

    public function getCountry($ip){
        return $this->getResult($ip, 'ip-country');
    }

    public function getCity($ip){
        return $this->getResult($ip, 'ip-city');
    }

    private function getResult($ip, $name){

        if(filter_var($ip, FILTER_VALIDATE_IP)){

            $api_url = 'http://' . $this->service . '/' . $this->version . '/' . $name . '/?key=' . $this->apiKey . '&ip=' . $ip . '&format=xml';

            // Set a small timeout
            self::accessCurlObj()->setTimeout(10);
            $num_tries_limit = 5;

            /**
             * Try to access the API a few times if there are timeouts
             */
            for($try_num = 1; $try_num <= $num_tries_limit; $try_num++) {

                $response = self::accessCurlObj()->get($api_url);
                $responsecode = (int)self::accessCurlObj()->getResponseCode();
                if($responsecode < 400 && $response && strlen($response) > 50) {

                    if (get_magic_quotes_runtime()){
                        $response = stripslashes($response);
                    }

                    try{
                        $response = @new SimpleXMLElement($response);

                        $result = array();
                        foreach($response as $field=>$value){
                            $result[(string)$field] = (string)$value;
                        }

                        return $result;
                    }
                    catch(Exception $e){
                        $this->errors[] = $e->getMessage();
                    }

                    return null;

                } else if($responsecode > 0 && $response && strlen($response) > 1000) {
                    $this->errors[] = "GeoIP ".$this->service." response code was {$responsecode} with response: {$response}. ";
                } else {

                    // Extend the execution time
                    if(function_exists("set_time_limit")) {
                        set_time_limit(100);    // Add 100 more seconds for effect
                    }

                    // Chill for a few seconds and try again
                    echo("Waiting " . $try_num * 5 . " more seconds ");
                    sleep($try_num * 5);
                }
            }

            // Failed. Throw exception
            $this->errors[] = "GeoIP API {$api_url} failed {$num_tries_limit} tries";
            return null;
        }

        $this->errors[] = '"' . $ip . '" is not a valid IP address.';
        return null;
    }

    //// HELPERS ////

    protected function accessCurlObj() {
        return $this->curl;
    }
}
?>
