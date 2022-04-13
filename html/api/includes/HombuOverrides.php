<?php
// Eric Draken - Load in the hombu override objects

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');


class HombuOverridesException extends HombuException {}

/**
 * @throws HombuOverridesException
 */
class HombuOverrides {
    private $logger, $overrideElements;

    public function __construct(HombuLogger $logger) {

        // Logging
        $this->logger = $logger;

        $filePath = HOMBU_API_PATH . '/hombudata/scheduleOverrides.xml';
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
                    throw new HombuOverridesException( $errorMessage );
                }
            } else {
                $this->overrideElements = $xml;
            }
        } else {
            throw new HombuOverridesException("File not found at path {$filePath}");
        }
    }

    /**
     * Parse and return override objects array
     **/
    public function getHombuOverrides() {

        if($this->overrideElements){

            // REF: http://stackoverflow.com/questions/4079565/xpath-selecting-multiple-elements-with-predicates
            // Xpath 1.0
            $overridesArray = array();
            foreach ($this->overrideElements->xpath('/root/lesson') as $overrideItem) {
                $Override = new StdClass();
                foreach($overrideItem->attributes() as $a => $b) {
                    if($a == "lessonId" && strlen($b) > 0) {
                        $Override->lessonId = (string)$b;
                    } else if($a == "teacherId" && strlen($b) > 0) {
                        $Override->teacherId = (string)$b;
                    } else if($a == "wasId" && strlen($b) > 0) {
                        $Override->wasId = (string)$b;
                    }
                }

                // Add the override object to the associative array
                if(property_exists($Override, "lessonId")) {
                    $overridesArray[$Override->lessonId] = $Override;
                }
            }

            return $overridesArray;
        } else {
            throw new HombuOverridesException("override lessons elements not present");
        }
    }
}