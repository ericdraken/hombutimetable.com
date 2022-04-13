<?php
// Eric Draken - Load in the hombu news objects

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');

/*
/// TEST
$hw = new HombuWeather(new HombuLogger());
$response = $hw->getHombuWeather();
echo print_r($response, 1) . PHP_EOL;
 */


class HombuNewsException extends HombuException {}

/**
 * @throws HombuNewsException
 */
class HombuNews {
    private $logger, $newsElements;

    public function __construct(HombuLogger $logger) {

        // Logging
        $this->logger = $logger;

        $filePath = HOMBU_API_PATH . '/hombudata/news.xml';
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
                    throw new HombuNewsException( $errorMessage );
                }
            } else {
                $this->newsElements = $xml;
            }
        } else {
            throw new HombuNewsException("File not found at path {$filePath}");
        }
    }

    static function outdatedAppWarningNews() {
        $WarningNews = new StdClass();
        $WarningNews->infoEn = "\xF0\x9F\x94\xB7 This app version is outdated. Please upgrade.";
        $WarningNews->infoJa = "\xF0\x9F\x94\xB7 このアプリは古いバージョンです";
        $WarningNews->newsId = HombuConstants::INFO_NEWS;
        return $WarningNews;
    }

    static function outdatedAppSeriousWarningNews() {
        $WarningNews = new StdClass();
        $WarningNews->infoEn = "\xF0\x9F\x94\xB7 This app version is VERY outdated. Please upgrade.";
        $WarningNews->infoJa = "\xF0\x9F\x94\xB7 このアプリは古いバージョンです";
        $WarningNews->newsId = HombuConstants::INFO_NEWS;
        return $WarningNews;
    }

    /**
     * Parse and return news objects
     **/
    public function getHombuNews($epoch) {

        if($this->newsElements){
            // Find the xml element with the date attribute
            $dateAttribute = self::convert_epoch_to_date_with_TZ($epoch, "JST");

            // REF: http://stackoverflow.com/questions/4079565/xpath-selecting-multiple-elements-with-predicates
            // Xpath 1.0
            $newsArray = array();
            foreach ($this->newsElements->xpath('/root/*[self::news|self::travel][contains(@date,\'' . $dateAttribute . '\')]') as $newsItem) {
                $News = new StdClass();
                $News->infoEn = (string)$newsItem->infoEn;
                $News->infoJa = (string)$newsItem->infoJa;

                switch($newsItem->getName()) {
                    case "news":
                        $News->newsId = HombuConstants::INFO_NEWS;
                        break;

                    case "travel":
                        $News->newsId = HombuConstants::TRAVEL_NEWS;
                        break;

                    default:
                        $News->newsId = HombuConstants::DEFAULT_NEWS;
                }

                // Corrections
                if(!$News->infoEn || strlen($News->infoEn) < 3) {
                    $News->infoEn = "--- missing ---";
                }

                if(!$News->infoJa || strlen($News->infoJa) < 3) {
                    $News->infoJa = $News->infoEn;
                }

                // Add the teacher id this news item pertains to
                foreach($newsItem->attributes() as $a => $b) {
                    if($a == "tag" && strlen($b) > 0) {
                        $News->tag = (string)$b;
                    }
                }

                $newsArray[] = $News;
            }

            return $newsArray;
        } else {
            throw new HombuNewsException("newsElements not present");
        }
    }

    private function convert_epoch_to_date_with_TZ($epochString, $to_tz)
    {
        // REF: http://stackoverflow.com/questions/9401332/how-to-convert-timestamp-into-date-time-string-in-php
        $time_object = new DateTime("@$epochString");
        $time_object->setTimezone(new DateTimeZone($to_tz));
        return $time_object->format('Y-m-d');
    }
}