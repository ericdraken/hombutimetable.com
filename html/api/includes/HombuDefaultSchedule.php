<?php
// Eric Draken - Load in the hombu default schedule objects

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');


class HombuDefaultScheduleException extends HombuException {}

/**
 * @throws HombuDefaultScheduleException
 */
class HombuDefaultSchedule {
    private $logger, $defaultScheduleElements, $hsp;

    public function __construct(HombuLogger $logger) {

        // Logging
        $this->logger = $logger;
        $this->hsp = new HombuScheduleParser($this->logger);

        $filePath = HOMBU_API_PATH . '/hombudata/defaultSchedule.xml';
        if (file_exists($filePath)) {

            // Hold onto xml parse errors for now
            libxml_use_internal_errors(true);

            // Load the news file
            $xml = simplexml_load_file($filePath);

            // Check for parse errors here
            if(!$xml) {
                foreach (libxml_get_errors() as $error) {
                    // Handle errors here
                    $errorMessage = trim($error->message) . "  Line: $error->line" . "  Column: $error->column";

                    libxml_clear_errors();
                    throw new HombuDefaultScheduleException( $errorMessage );
                }
            } else {
                $this->defaultScheduleElements = $xml;
            }
        } else {
            throw new HombuDefaultScheduleException("File not found at path {$filePath}");
        }
    }

    /**
     * Parse and return override objects array
     **/
    public function getDefaultSchedules() {

        if($this->defaultScheduleElements){

            $defaultSchedules = array();

            // Search for an active default schedule given the date
            foreach ($this->defaultScheduleElements->xpath('/root/schedule') as $schedule) {

                // Check if this schedule is active
                $validUntil = $schedule->attributes()->validUntil;
                $validFrom = $schedule->attributes()->validFrom;
                if($validUntil && $validFrom) {

                    $defaultScheduleArray = array();

                    // iterate over the 7 days
                    foreach ($schedule->xpath('./day') as $day) {
                        $dayArray = array();
                        $dayOrdinal = (string)$day->attributes()->isoOrdinal;

                        // iterate over each lesson
                        foreach ($day->xpath('./lesson') as $lesson) {
                            $attrs = $lesson->attributes();
                            $lessonType = $this->hsp->guessType((string)$attrs->title);
                            $lessonHash = HombuFormatters::hashLessonTime((string)$attrs->start, (string)$attrs->end, $lessonType);
                            $dayArray[$lessonHash] = array((string)$attrs->title, $this->hsp->extractTeachers((string)$attrs->title));
                        }

                        $defaultScheduleArray[$dayOrdinal] = $dayArray;
                    }

                    // Add this schedule to the array of default schedules
                    $scheduleObject = new stdClass();
                    $scheduleObject->validUntilDateMS = strtotime((string)$validUntil);
                    $scheduleObject->validFromDateMS = strtotime((string)$validFrom);
                    $scheduleObject->schedule = $defaultScheduleArray;
                    $defaultSchedules[] = $scheduleObject;
                 }
            }

            // Sort the default schedule array(s)
            asort($defaultSchedules, SORT_NUMERIC);

            return $defaultSchedules;
        } else {
            throw new HombuDefaultScheduleException("default lessons elements not present");
        }
    }
}