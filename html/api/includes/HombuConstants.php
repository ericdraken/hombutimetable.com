<?php
/**
 * Encapsulate constants used by the hombu calendar system
 * Eric Draken, 2012
 */
class HombuConstants {
	
	// Floors
	const HOMBU_4TH_FLOOR = "4";
	const HOMBU_3RD_FLOOR = "3";
	const HOMBU_2ND_FLOOR = "2";
	const ALL_FLOORS = "1";
	
	// Lesson types
	const REGULAR = "REGULAR";
	const BEGINNER = "BEGINNER";
	const WOMEN = "WOMEN";
	const CHILDREN = "CHILDREN";
	const GAKKO = "GAKKO";
	const UNKNOWN = "UNKNOWN";
	
	// Hombu filters
	const VALID_DAY = "VALID_DAY";
    const CLOSED_DAY = "CLOSED_DAY";
	const UNPLANNED_DAY = "UNPLANNED_DAY";
	const PURGED_DAY = "PURGED_DAY";
	const ALL_FAILED = "ALL_FAILED";
	
	// Event types
	const MULTIPLE_TIMES_EVENTS = "MULTIPLE_TIMES_EVENTS";
	const SINGLE_TIME_EVENTS = "SINGLE_TIME_EVENTS";
	const SINGLE_TIME_EVENTS_REVERSED = "SINGLE_TIME_EVENTS_REVERSED";

    // News tags
    const CLOSED_DAY_TAG = "CLOSED_DAY_TAG";

    // News IDs
    const TRAVEL_NEWS = 5;
    const INFO_NEWS = 2;
    const DEFAULT_NEWS = 1;
}
?>