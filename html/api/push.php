<?php
/**
 * Check for lesson changes. If any, send a push notification
 * Eric Draken, 2013
 *
 * 2013.09.16 - Base file
 */

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/includes/HombuDBInterface.php');
require_once(__DIR__ . '/includes/HombuLogger.php');

// Push notification providers
require_once(__DIR__ . '/includes/UrbanAirship.php');
require_once(__DIR__ . '/includes/Parse.com.php');

// Pretty formatting for emailing results
require_once(__DIR__ . '/includes/Console_Table.php');

define('PUSH_NONSILTENT_ALERT_REPEAT_THRESHOLD_SECONDS', 7200);    // 2 hours

// UA Push will cease to function after this date
define('UA_PUSH_DISABLE_FROM_DATE', 1420037940);    // Dec 31, 23:59 JST

define('ERR_EMAIL', 'xxxxx');

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

$hl = new HombuLogger();

try {

    if(defined("DEVELOPMENT_ENVIRONMENT")) {
        // Development
        echo "Development environment... <br/>";
    } else {
        // Production
        echo "Production server... <br/>";
    }

    // Load the DB connection
    $hdbi = new HombuDBInterface(array(), $hl);
    $hdbi->accessDB()->cache_timeout = 0;   // Don't cache at all - this is a real-time system

    // Check the ht_details table. Get the counts. Compare to the last counts
    // If they are different, send a push notification of the difference
    $prefix = HOMBUTIMETABLE_TABLE_PREFIX;

    // This return all the details with duplicate event IDs which represent lessons with changes for ALL changes
    // SELECT SUM(changes.counts) AS changes FROM (SELECT COUNT(event_ID) AS counts FROM `ht_details` GROUP BY event_ID HAVING count(event_ID) > 1) as changes
    /*
    // SELECTED lesson changes
    SELECT
        SUM(changes.counts - 1)
    FROM
    (SELECT
             COUNT(inside.event_ID) AS counts
         FROM
         (SELECT
                 hd.event_ID, he.event_type
             FROM
                 `ht_events` he
             LEFT JOIN
                 `ht_details` hd
             ON
                 hd.event_ID = he.event_ID
             WHERE
                 he.event_type <> 'CHILDREN' AND he.event_type <> 'GAKKO'
             ) AS inside
         GROUP BY
             inside.event_ID
         HAVING
             count(inside.event_ID) > 1
         ) AS changes
        */

    // ANY change
    // $count_sql = "SELECT SUM(changes.counts) FROM (SELECT COUNT(event_ID) AS counts FROM `".$prefix."details` GROUP BY event_ID HAVING count(event_ID) > 1) as changes";

    // Not children, nor gakko changes
    $count_sql = "SELECT SUM(changes.counts - 1) FROM (SELECT COUNT(inside.event_ID) AS counts FROM (SELECT hd.event_ID, he.event_type FROM `".$prefix."events` he LEFT JOIN `".$prefix."details` hd ON hd.event_ID = he.event_ID WHERE he.event_type <> 'CHILDREN' AND he.event_type <> 'GAKKO' ) AS inside GROUP BY inside.event_ID HAVING count(inside.event_ID) > 1 ) AS changes";
    $actual_count = $hdbi->accessDB()->get_var($count_sql);

    // Get the reference count
    $ref_sql = "SELECT `variable_value` FROM `".$prefix."vars` WHERE `variable_name` = 'current_details_count';";
    $reference_count = $hdbi->accessDB()->get_var($ref_sql);

    // Add the current_details_count variable if it doesn't exist
    if(!isset($reference_count)) {
        $hdbi->accessDB()->query("INSERT INTO  `".HOMBUTIMETABLE_TABLE_PREFIX."vars` (
          `variable_ID` , `variable_name` , `variable_value`
        ) VALUES (NULL ,  'current_details_count',  '1')");
        $reference_count = 0;
    }

    // Update the reference count if Hombu deleted events
    if($actual_count >= 0 && $actual_count < $reference_count) {
        // Update the reference count
        $update_sql = "UPDATE `".$prefix."vars` SET `variable_value` = ". $actual_count ." WHERE `variable_name` = 'current_details_count';";
        $hdbi->accessDB()->query($update_sql);
        $reference_count = $actual_count;

        error_log("The actual count was lower than the reference count. FYI", 1, ERR_EMAIL, "From: HombuTimetable Push <pushinfo@hombutimetable.com>");
        echo "Sent notice about reduced count";
    }

    // Send a push notification
    else if($actual_count >= 0 && $actual_count > $reference_count) {
        $number_of_updates = $actual_count - $reference_count;
        echo "There are {$number_of_updates} updates to the schedule";

        // Update the reference count
        $update_sql = "UPDATE `".$prefix."vars` SET `variable_value` = ". $actual_count ." WHERE `variable_name` = 'current_details_count';";
        $hdbi->accessDB()->query($update_sql);

        // Check when the last push message was sent. If it was sent recently, then send a silent notification
        $timestamp_sql = "SELECT `variable_value` FROM `".$prefix."vars` WHERE `variable_name` = 'last_push_sent_time';";
        $last_push_sent_time = $hdbi->accessDB()->get_var($timestamp_sql);

        // Add the current_details_count variable if it doesn't exist
        if(!$last_push_sent_time) {
            $hdbi->accessDB()->query("INSERT INTO  `".HOMBUTIMETABLE_TABLE_PREFIX."vars` (
          `variable_ID` , `variable_name` , `variable_value`
        ) VALUES (NULL ,  'last_push_sent_time',  ".time().")");
            $last_push_sent_time = 0;   // Never been pushed
        }

        $push_info = array();

        $time_diff = time() - $last_push_sent_time;
        if($time_diff > PUSH_NONSILTENT_ALERT_REPEAT_THRESHOLD_SECONDS){

            // Reset the delta for manually accumulating pushes to the updates just detected
            $delta_update_sql = "INSERT INTO `".$prefix."vars` (`variable_name`, `variable_value`) VALUES ('push_delta', ". $number_of_updates .") ON DUPLICATE KEY UPDATE `variable_value` = ". $number_of_updates .";";
            $hdbi->accessDB()->query($delta_update_sql);

            if(defined("DEVELOPMENT_ENVIRONMENT")) {
                // Development push messages only
                $push_info["Parse"] = ParsePush::pushNotification($number_of_updates, false, true);

                // UA has a cutoff date
                if(UA_PUSH_DISABLE_FROM_DATE - time() > 0) {
                    $push_info["UA"] = UAPush::pushNotification($number_of_updates, false, true);
                }
            } else {
                // Send production push notification here as well
                $push_info["Parse"] = ParsePush::pushNotification($number_of_updates, false, false);

                // UA has a cutoff date
                if(UA_PUSH_DISABLE_FROM_DATE - time() > 0) {
                    $push_info["UA"] = UAPush::pushNotification($number_of_updates, false, false);
                }
            }
        } else {

            // Get the number of changes sent in the last push message in order to maintain a delta
            $delta_sql = "SELECT `variable_value` FROM `".$prefix."vars` WHERE `variable_name` = 'push_delta';";
            $delta_count = $hdbi->accessDB()->get_var($delta_sql);

            $number_of_updates_plus_delta = $number_of_updates + intval($delta_count);

            $delta_update_sql = "INSERT INTO `".$prefix."vars` (`variable_name`, `variable_value`) VALUES ('push_delta', ". $number_of_updates_plus_delta .") ON DUPLICATE KEY UPDATE `variable_value` = ". $number_of_updates_plus_delta .";";
            $hdbi->accessDB()->query($delta_update_sql);

            if(defined("DEVELOPMENT_ENVIRONMENT")) {
                // Silent development push messages
                $push_info["Parse"] = ParsePush::pushNotification($number_of_updates_plus_delta, true, true);

                // UA has a cutoff date
                if(UA_PUSH_DISABLE_FROM_DATE - time() > 0) {
                    // UA will auto increment the badges
                    $push_info["UA"] = UAPush::pushNotification($number_of_updates, true, true);
                }
            } else {
                // Send silent production push notification here too
                $push_info["Parse"] = ParsePush::pushNotification($number_of_updates_plus_delta, true, false);

                // UA has a cutoff date
                if(UA_PUSH_DISABLE_FROM_DATE - time() > 0) {
                    // UA will auto increment the badges
                    $push_info["UA"] = UAPush::pushNotification($number_of_updates, true, false);
                }
            }
        }

        if($push_info["Parse"]["success"] == TRUE || @$push_info["UA"]["success"] == TRUE) {
            $update_sql = "UPDATE `".$prefix."vars` SET `variable_value` = ". time() ." WHERE `variable_name` = 'last_push_sent_time';";
            $hdbi->accessDB()->query($update_sql);
            echo "<p>Push notification successfully sent<br /><pre>" . PHP_EOL . print_r($push_info, TRUE) . "</pre></p>";
        } else {
            echo "<p>Unsuccessful push notification<br /><pre>" . PHP_EOL . print_r($push_info, TRUE) . "</pre></p>";
        }

        // Send out an email about this push
        if(!defined("DEVELOPMENT_ENVIRONMENT") || defined("PRODUCTION_ENVIRONMENT")) {
            $headers   = array();
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-type: text/plain; charset=iso-8859-1";
            $headers[] = "From: HombuTimetable Push <" . $_SERVER["REAL_USERNAME"] . "@" . gethostname() . ">";
            $headers[] = "Reply-To: HombuTimetable Push <pushinfo@hombutimetable.com>";
            $headers[] = "Subject: Push message sent: " . $number_of_updates . " updates";
            $headers[] = "X-Mailer: PHP/".phpversion();

            // Send email notifying me that a push was sent
            $to      = ERR_EMAIL;
            $subject = 'Push message sent: ' . $number_of_updates . ' updates';
            $message = print_r($push_info, 1) . PHP_EOL . PHP_EOL;

            // Return the actual changes
            // SELECT changes.event_ID, ID, date, start_datetime, floor, teacher_names, checked_timestamp FROM (SELECT ID, event_ID, teacher_names FROM ht_details WHERE event_ID IN (SELECT DISTINCT event_ID AS counts FROM `ht_details` GROUP BY event_ID HAVING count(event_ID) > 1)) AS changes JOIN `ht_events` ON ht_events.event_ID = changes.event_ID ORDER BY changes.event_ID DESC, ID ASC LIMIT 0, 80
            $changes_sql = "SELECT changes.event_ID, ID, date, start_datetime, floor, teacher_names FROM (SELECT ID, event_ID, teacher_names FROM ".$prefix."details WHERE event_ID IN (SELECT DISTINCT event_ID AS counts FROM `".$prefix."details` GROUP BY event_ID HAVING count(event_ID) > 1)) AS changes JOIN `".$prefix."events` ON ".$prefix."events.event_ID = changes.event_ID ORDER BY changes.event_ID DESC, ID ASC LIMIT 0, 80";
            $results = $hdbi->accessDB()->get_results($changes_sql);
            $tbl = new Console_Table();
            $tbl->setHeaders(array("event_ID", "ID", "date", "start_datetime", "floor", "teacher_names"));
            foreach ($results as $row) {
                $tbl->addRow(get_object_vars($row));
            }
            $message .= PHP_EOL . $tbl->getTable();

            // Try to email the results
            if (mail($to, $subject, $message, implode("\r\n", $headers))) {
                echo("<p>Email successfully sent!</p>");
            } else {
                echo("<p>Email delivery failed...</p>");
                error_log("Push email failed to send with info: " . print_r($message, 1), 1, ERR_EMAIL, "From: pusherrors@hombutimetable.com");
            }
        }

    } else {
        echo "There are no schedule updates";
    }
} catch(Exception $e){

    echo $e . "<br/>";
    echo "<pre>"; print_r($e->getTrace());
    echo "</pre>";

    error_log($e . PHP_EOL . print_r($e->getTrace(), TRUE), 1, ERR_EMAIL, "From: pusherrors@hombutimetable.com");
}
