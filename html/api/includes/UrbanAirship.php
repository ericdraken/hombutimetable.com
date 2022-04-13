<?php

// REF: More tones from http://www.zedge.net/ringtones/2111/apple-iphone-3g-ringtones/0-1-6-notification/?cursor=1..145

class UAPush {

    private static $APPKEY_DEV = "xxxxx";
    private static $PUSHSECRET_DEV = "xxxxx";

    private static $APPKEY_PRODUCTION = "xxxxx";
    private static $PUSHSECRET_PRODUCTION = "xxxxx";

    private static $PUSHURL = "https://go.urbanairship.com/api/push/broadcast/";
    private static $ERR_EMAIL = "xxxxx";

    public static function pushNotification($count = 1, $silent = false, $dev = false) {

        $contents = array();
        $contents['badge'] = "+" . $count;

        // Turn on or off visible / audio alert
        if(!$silent) {
            $contents['alert'] = array(
                                    "loc-key" => "push_msg_changes",
                                    "loc-args" => array($count)
                                );
            $contents['sound'] = "soft_bells.mp3";    // Prolonged soft bells
        }

        $push = array(
            "aps" => $contents,
            "device_types" => array("ios")
        );

        $json = json_encode($push);

        $session = curl_init(self::$PUSHURL);

        if(!$dev) {
            curl_setopt($session, CURLOPT_USERPWD, self::$APPKEY_PRODUCTION . ':' . self::$PUSHSECRET_PRODUCTION);
        } else {
            curl_setopt($session, CURLOPT_USERPWD, self::$APPKEY_DEV . ':' . self::$PUSHSECRET_DEV);
        }

        curl_setopt($session, CURLOPT_POST, True);
        curl_setopt($session, CURLOPT_POSTFIELDS, $json);
        curl_setopt($session, CURLOPT_HEADER, False);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, True);
        curl_setopt($session, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $content = curl_exec($session);

        //echo '<br/>' . $content . '<br/>'; // just for testing what was sent

        // Check if any error occurred
        $response = curl_getinfo($session);
        $success = FALSE;
        if($response['http_code'] != 200) {
            // Send me an error message
            error_log(print_r($response, TRUE), 1, self::$ERR_EMAIL, "From: UApusherrors@hombutimetable.com");
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
