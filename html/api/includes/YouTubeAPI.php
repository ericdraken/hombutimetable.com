<?php
// Eric Draken - Load in the hombu news objects

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__. '/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuLogger.php');		// Extends Zend_Log
require_once(__DIR__ . '/HombuException.php');
require_once(__DIR__ . '/ZendCache.php');
require_once('curl/curl.php');

// YouTube API PHP classes
require_once('Google/autoload.php');
require_once('Google/Client.php');
require_once('Google/Service/YouTube.php');


class YouTubeAPIException extends HombuException {}

/**
 * @throws YouTubeAPIException
 */
class YouTubeAPI {
    private $apiKey, $logger, $curl, $cache;
    private $DEBUG;

    private static $cache_dir = "YouTubeAPI/details";

    public function __construct(HombuLogger $logger) {

        /////// Debug /////////
        $this->DEBUG = FALSE;
        ///////////////////////

        // GData v3 API Key (Server)
        $this->apiKey = "xxxxx";

        // Logging
        $this->logger = $logger;

        // Set the params for the weather API
        $this->curl = new cURL(FALSE);	// Don't use cookies

        // Save a cache copy for a very long time - about a year
        $CACHE_TIME = 31104000;
        $this->cache = new ZendCache($CACHE_TIME, true, self::$cache_dir);
    }

    // Delete all cache files
    public static function purgeCache() {
        echo "Purging cache at " . HOMBU_CACHE_PATH . "/" . self::$cache_dir . PHP_EOL . "<br />";
        $files = glob(HOMBU_CACHE_PATH . "/" . self::$cache_dir . "/?????*"); // files at least 5 chars long
        foreach ($files as $filename) {
            echo $filename . (unlink($filename) ? " - deleted" : " - error") . PHP_EOL . "<br />";
        }
        echo "<br />Done";
    }

    // Convert all not acceptable characters to a double underscors
    public function fixCacheName($name) {
        return preg_replace('/[^A-Za-z0-9_]/', '__', $name);
    }

    public function appendAPIVersion($name, $version) {
        return $name . "_ver" . $version;
    }

    public function getVideoDetailsJSON($version = 1, $video = null) {
        if($video) {

            if($version >= 1) {
                $version = 1;

                // Serve or save and serve the cache
                if(($cached = $this->cache->loadCache(self::appendAPIVersion(self::fixCacheName($video), $version))) === null) {
                    if($this->DEBUG) { echo "Cache MISS for $video<br>" . PHP_EOL;}

                    $videoDetailsArray = array();

                    try {
                        // New Google client
                        $client = new Google_Client();
                        $client->setDeveloperKey($this->apiKey);

                        // Define an object that will be used to make all API requests.
                        $youtube = new Google_Service_YouTube($client);

                        // Get the video data
                        $response = $youtube->videos->listVideos('id,snippet', array(
                            'id' => $video
                        ));

                        if(count($response['items']) == 0) {
                            if($this->DEBUG) { echo "No results for $video<br>" . PHP_EOL;}
                            return array();
                        }

                        // Video ID
                        $videoDetailsArray["videoId"] = $video;

                        // Video title
                        $videoDetailsArray["title"] = @$response['items'][0]['snippet']['title'];
                        if(!isset($videoDetailsArray["title"])) {
                            throw new YouTubeAPIException("title was not found");
                        }

                        // Video author
                        $videoDetailsArray["author"] = @$response['items'][0]['snippet']['channelTitle'];
                        if(!isset($videoDetailsArray["author"])) {
                            throw new YouTubeAPIException("author was not found");
                        }

                        // Detect widescreen or not from thumbnail data
                        $isWidescreen = self::isWidescreen($video);  // Check if widescreen or not
                        $videoDetailsArray["widescreen"] = $isWidescreen;

                        // Append the API version
                        $videoDetailsArray["apiVer"] = $version;

                        // Find and process the thumbnails for this video
                        $videoDetailsArray["thumbnails"] = $this->buildYouTubeThumbnailsArray($video, $isWidescreen);

                    } catch (Google_Service_Exception $e) {
                        throw new YouTubeAPIException("YouTube Service Exception: " . htmlspecialchars($e->getMessage()));
                    } catch (Google_Exception $e) {
                        throw new YouTubeAPIException("YouTube Exception: " . htmlspecialchars($e->getMessage()));
                    }

                    $json = json_encode($videoDetailsArray);
                    $this->cache->saveCache($json, self::appendAPIVersion(self::fixCacheName($video), $version));
                    return $json;
                } else {
                    if($this->DEBUG) { echo "Cache HIT for $video<br>" . PHP_EOL;}
                    return $cached;
                }
            }
        } else {
            throw new YouTubeAPIException("video not present as parameter");
        }
    }

    // Build 4 240x180 thumbnails from the public YouTube thumbnails collection
    public function buildYouTubeThumbnailsArray($video, $isWidescreen = true) {

        $thumbsArray = array();
        $thumb = new Imagick();
        $curl = new cURL(FALSE);	// Don't use cookies

        // Main image
        $url = "https://i.ytimg.com/vi/{$video}/mqdefault.jpg";
        $response = $curl->get($url);

        // Handle 404 errors here
        if($curl->responseCode < 200 || $curl->responseCode > 302) {
            throw new YouTubeAPIException("Thumbnail {$url} response code was " . $curl->responseCode);
        }

        $thumb->readImageBlob($response);

        // Crop default.jpg because it has black bars due to widescreen
        if($isWidescreen) {
            // Compress mqdefault.jpg from 320x180 (16:9) --> 240x180 (4:3)
            // This is because Retina display uses 2x resolution --> 120x90
            $thumb->resizeImage(240, 180, Imagick::FILTER_CATROM, 0.9);
        } else {
            // A 4:3 image will have side bars that need to be cropped only
            $thumb->cropImage(240, 180, 40, 0);
        }

        $thumb->contrastImage(true); // Add a little contrast
        $thumb->setImageCompression(Imagick::COMPRESSION_JPEG);
        $thumb->setImageCompressionQuality(80); // Set compression level (1 lowest quality, 100 highest quality)
        $thumb->stripImage(); // Strip out unneeded meta data

        // Add this main image
        $this->setArrayEntry($thumbsArray, $thumb->getImageBlob(), $url);

        // Clean up
        $thumb->destroy();

        //////////////////////////////////////////////////////////////

        // 1..4.jpg
        for($i = 1; $i < 4; $i++) {

            $url = "https://i.ytimg.com/vi/{$video}/{$i}.jpg";
            $response = $curl->get($url);
            $thumb->readImageBlob($response);

            // Crop default.jpg because it has black bars due to widescreen
            if($isWidescreen) {
                $thumb->cropImage(120, 67, 0, 11);
            }

            // Compress default.jpg from 320x180 (16:9) --> 240x180 (4:3)
            // This is because Retina display uses 2x resolution --> 120x90
            $thumb->resizeImage(240, 180, Imagick::FILTER_CATROM, 1);
            $thumb->sharpenImage(9, 2); // Sharpen the tiny thumbnail
            $thumb->contrastImage(true); // Add a little contrast
            $thumb->setImageCompression(Imagick::COMPRESSION_JPEG);
            $thumb->setImageCompressionQuality(80); // Set compression level (1 lowest quality, 100 highest quality)
            $thumb->stripImage(); // Strip out unneeded meta data

            // Add this frame image
            $this->setArrayEntry($thumbsArray, $thumb->getImageBlob(), $url);

            // Clean up
            $thumb->destroy();
        }

        return $thumbsArray;
    }

    //// HELPERS ////

    // Set the image in the array
    private function setArrayEntry(&$array, $blob, $url) {
        $array[] = array("data" => base64_encode($blob), "url" => $url);
    }

    private function isWidescreen($video = null) {

         // Get the default thumbnail (may have black bars on top and bottom)
        $response = self::accessCurlObj()->get("http://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video}&format=json");

        if ($response) {
            $json = json_decode($response);
            if ($json) {
                $width = $json->width;
                $height = $json->height;

                if($this->DEBUG) { echo "Aspect ratio is: " . $width / (float) $height . PHP_EOL . "<BR>"; }

                if ($width / (float) $height < 1.7) {
                    return false;   // 1.33 = 4:3
                } else {
                    return true;    // 1.77 = 16:9
                }
            }
        }

        if($this->DEBUG) { echo "Primary isWidescreen failed for $video<br>" . PHP_EOL;}

        // oEmbed failed, so use the backup method
        return $this->isWidescreenViaBlackbars($video);
    }

    private function isWidescreenViaBlackbars($video = null) {

        // LOGIC:
        // 4:3 videos will have default.jpg with no top black bars
        // 16:9 videos will have black top and bottom borders on default.jpg

        // Get the default thumbnail (may have black bars on top and bottom)
        $response = self::accessCurlObj()->get("https://i.ytimg.com/vi/{$video}/default.jpg");
        $defaultImgRes = imagecreatefromstring($response);

        $samplePoints = array(array(20,2), array(40,4), array(60,6), array(80,8));

        // Scan a few points for equality between top and bottom
        $height = imagesy($defaultImgRes);
        foreach($samplePoints as $point) {
            // Top
            $rgbTop = imagecolorat($defaultImgRes, $point[0], $point[1]);
            $colorsTop = imagecolorsforindex($defaultImgRes, $rgbTop);

            // Bottom
            $rgbBottom = imagecolorat($defaultImgRes, $point[0], $height - $point[1]);
            $colorsBottom = imagecolorsforindex($defaultImgRes, $rgbBottom);

            // If these arrays are not close, then let's call this 4:3 aspect
            if(!$this->areArraysClose($colorsTop, $colorsBottom, 20)) {
                return false;
            }
        }

        // Default to widescreen
        return true;
    }

    // Determine if the numeric values in the RGBA array are within some delta from each other
    private function areArraysClose(&$a, &$b, $delta = 10) {
        foreach($a as $key => $val) {
            if(abs($val - $b[$key]) > $delta) {
                return false;
            }
        }
        return true;
    }

    protected function accessCurlObj() {
        return $this->curl;
    }
}
