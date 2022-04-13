<?php
// Eric Draken - Load in the hombu news objects

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');


class HombuVideosException extends HombuException {}

/**
 * @throws HombuVideosException
 */
class HombuVideos {
    private $logger, $videoElements;

    public function __construct(HombuLogger $logger) {

        // Logging
        $this->logger = $logger;

        $filePath = HOMBU_API_PATH . '/hombudata/videos.xml';
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
                    throw new HombuVideosException( $errorMessage );
                }
            } else {
                $this->videoElements = $xml;
            }
        } else {
            throw new HombuVideosException("File not found at path {$filePath}");
        }
    }

    /**
     * Parse and return news objects
     **/
    public function getHombuVideos($version = 1) {

        if($this->videoElements){

            if($version >= 2) {
                //// VERSION 2 ////

                // REF: http://stackoverflow.com/questions/4079565/xpath-selecting-multiple-elements-with-predicates
                // Xpath 1.0
                $videosArray = array();
                $paramsArray = array();
                foreach ($this->videoElements->xpath('/videos/group') as $videoGroup) {

                    $groupAttribs = $videoGroup->attributes();
                    $groupID = (string)$groupAttribs["id"];

                    // REF: http://bytes.com/topic/net/answers/792720-xpath-non-empty-text-nodes-tdom
                    $groupArray = array();
                    foreach ($videoGroup->xpath('video[normalize-space(text()) != ""]') as $video) {
                        $groupArray[] = (string)$video;

                        // Add extra video parameters here, e.g. start
                        if(count($video->attributes()) > 0) {
                            $paramObject = new stdClass();
                            foreach($video->attributes() as $a => $b) {
                                $paramObject->$a = (string)$b;
                            }

                            if($version >= 3) {
                                // The group name is added to the param so duplicate videos can have different start times
                                $paramsArray[$groupID . "-" . (string)$video] = $paramObject;
                            } else {
                                $paramsArray[(string)$video] = $paramObject;
                            }
                        }
                    }

                    $videosArray[$groupID] = $groupArray;
                }

                return array($videosArray, $paramsArray);    // Return two joined arrays
            } else {
                //// VERSION 1 ////

                // REF: http://stackoverflow.com/questions/4079565/xpath-selecting-multiple-elements-with-predicates
                // Xpath 1.0
                $videosArray = array();
                foreach ($this->videoElements->xpath('/videos/group') as $videoGroup) {

                    // REF: http://bytes.com/topic/net/answers/792720-xpath-non-empty-text-nodes-tdom
                    $groupArray = array();
                    foreach ($videoGroup->xpath('video[normalize-space(text()) != ""]') as $video) {
                        $groupArray[] = (string)$video;
                    }

                    $groupAttribs = $videoGroup->attributes();
                    $groupID = (string)$groupAttribs["id"];
                    $videosArray[$groupID] = $groupArray;
                }

                return $videosArray;
            }
        } else {
            throw new HombuVideosException("newsElements not present");
        }
    }
}

/*
/// TEST
$hv = new HombuVideos(new HombuLogger());
$response = $hv->getHombuVideos();
echo print_r($response, 1) . PHP_EOL;
*/