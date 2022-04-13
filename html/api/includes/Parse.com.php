<?php

class ParsePush {

    // From Parse.com
    private static $APP_ID_HEADER = "X-Parse-Application-Id: xxxxx";
    private static $REST_API_KEY = "X-Parse-REST-API-Key: xxxxx";
    private static $PUSHURL = "https://api.parse.com/1/push";
    private static $ERR_EMAIL = "xxxxx";

    // REF: More tones from http://www.zedge.net/ringtones/2111/apple-iphone-3g-ringtones/0-1-6-notification/?cursor=1..145

    public static function pushNotification($count = 1, $silent = false, $dev = false) {

        $contents = array();
        $contents["badge"] = $count;

        // Turn on or off visible / audio alert
        if(!$silent) {
            $contents["alert"] = array(
                "loc-key" => "push_msg_changes",
                "loc-args" => array($count)
            );
            //$contents['sound'] = "sparkle.mp3";    // Sparkling sound
            //$contents['sound'] = "strings.mp3";      // Strong strings
            $contents['sound'] = "soft_bells.mp3";    // Prolonged soft bells
        }

        $push = array(
            "expiration_interval" => 86400,         // One day expiry
            "data" => $contents,
            "where" => array("deviceType" => "ios")
        );

        $json = json_encode($push);

        //echo '<br/>' . $json . '<br/>'; // just for testing what was sent

        $session = curl_init(self::$PUSHURL);

        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, $json);
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_HTTPHEADER, array(self::$APP_ID_HEADER, self::$REST_API_KEY, 'Content-Type:application/json'));
        $content = curl_exec($session);

        //echo '<br/>' . $content . '<br/>'; // just for testing what was sent

        // Check if any error occurred
        $response = curl_getinfo($session);
        $success = FALSE;
        if($response['http_code'] != 200) {
            // Send me an error message
            error_log(print_r($response, TRUE), 1, self::$ERR_EMAIL, "From: Ppusherrors@hombutimetable.com");
        } else {
            $success = TRUE;
        }

        curl_close($session);

        return array(
            "json" => $json,
            "content" => $content,
            "response" => $response,
            "success" => $success
        );
    }

}
