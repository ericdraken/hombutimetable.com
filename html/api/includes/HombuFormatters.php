<?php
/**
 * Various formatters for the Hombu timetable sytstem
 */

 require_once(__DIR__ . '/HombuValidators.php');
 
 class HombuFormatters {

     /**
      * Create a hash of the start hour and end hour
      * to minimize duplicate events due to some administrator
      * doing something like 06:31 --> 06:30 resulting in duplicate events
      * This has is ONLY unique per Day
      */
     static public function hashLesson($lessonStart, $lessonEnd, $lessonType) {
         return hash(
             "crc32",
             HombuFormatters::extractRoundedTimeFromSqlDatetime($lessonStart) . HombuFormatters::extractRoundedTimeFromSqlDatetime($lessonEnd) . $lessonType,
             FALSE);
     }	// 8 chars

     /**
      * Like hashLesson, but no date checking. Use only if you are sure to input HH and mm properly
      */
     static public function hashLessonTime($lessonStartTime, $lessonEndTime, $lessonType) {

        // Dummy dates are needed since we only care about the hours and minutes
         return hash(
             "crc32",
             HombuFormatters::extractRoundedTimeFromSqlDatetime("2001-01-15 " . $lessonStartTime) . HombuFormatters::extractRoundedTimeFromSqlDatetime("2001-01-15 " . $lessonEndTime) . $lessonType,
             FALSE);
     }	// 8 chars

     /**
	 * Get the hour from a SQL date string
	 */
	static public function extractHourFromSqlDatetime($date) {
		HombuValidators::sqlDateTimeFormatCheck($date);
		return date("H", strtotime($date));
	}

     /**
      * Get the minutes from a SQL date string, rounding down to either 0, 15, 30 or 45 minutes
      */
     static public function extractRoundedMinutesFromSqlDatetime($date) {
         HombuValidators::sqlDateTimeFormatCheck($date);
         $minutes = intval(date("i", strtotime($date)));

         if($minutes >= 0 && $minutes < 15) {
             $minutes = 0;
         } else if($minutes >= 15 && $minutes < 30) {
             $minutes = 15;
         } else if($minutes >= 30 && $minutes < 45) {
             $minutes = 30;
         } else if($minutes >= 45 && $minutes <= 59) {
             $minutes = 45;
         }
         return $minutes;
     }

     // Extract a rounded hours-minute time
     static public function extractRoundedTimeFromSqlDatetime($date) {
        return HombuFormatters::extractHourFromSqlDatetime($date) . HombuFormatters::extractRoundedMinutesFromSqlDatetime($date);
     }

     /**
	 * Remove non-alpha chars and limit the length, and only lowercase
	 */
	static public function formatTeacherName($str, $len = 14) {
		return strtolower( substr( preg_replace("%[^a-zA-Z_]%", "", $str), 0, $len) );
	}

	/**
	 * Convert 2012/07/15 to 2012-07-15
	 */
	static public function hombuToSqlDate($hombu_date) {
		$new_date = str_replace("/", "-", $hombu_date);
		HombuValidators::sqlDateFormatCheck($new_date);
		return $new_date;
	}

	/**
	 * Hombu used EUC, but the rest of the world uses UTF-8
	 */ 	
	static public function eucToUtf($str) {
		return mb_convert_encoding($str, "UTF-8", "EUC-JP");		
	}
	
	/**
	 * Format the output as YYYY/MM/DD hh:mm
	 */
	static public function formatAsYMDHM2($hombu_date, $hr, $mn) {
		return $hombu_date . " " . HombuFormatters::add_leading_zeros($hr, 2) . ":" . $mn;	
	}

	/**
	 * Format the output as YYYY/MM/DD hh:mm
	 */
	static public function formatAsYMDHM($hombu_date, $hm_time) {
		return $hombu_date . " " . HombuFormatters::add_leading_zeros($hm_time, 5);	
	}
	
	// Add a leading zero to dates like 9:30 -> 09:30
	static public function add_leading_zeros($input, $str_length) {
		return str_pad($input, $str_length, '0', STR_PAD_LEFT);
	}	
 }
 
?>