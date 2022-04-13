<?php
/**
 * Cache data
 */

////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/define_root.php');
require_once(__ROOT__.'/global_settings.php');
//////////////////////////////////////////////////////

require_once(__DIR__ . '/HombuException.php');
require_once("Zend/Cache.php");

class HombuZendCacheException extends HombuException {}
/**
 * Class ZendCache - Control cache for Hombu data
 */
final class ZendCache {

    private $cache, $cacheName;

    public function __construct($cacheLifeTime = 600, $isSerialize = true, $cachePath = 'cache_path') {
        //Set the cache life time and serialization
        $frontendOptions = array('lifetime' => $cacheLifeTime, 'automatic_serialization' => $isSerialize);

        // Create the zendcache dir if it desn't exist
        if(!is_dir(HOMBU_CACHE_PATH . "/" . $cachePath)) {

            // Create the recursive directories
            if (!@mkdir(HOMBU_CACHE_PATH . "/" . $cachePath, true)) {
                $error = error_get_last();
                throw new HombuZendCacheException(print_r($error, true));
            }
        }

        // Set the directory where to put the cache files
        $backendOptions = array('cache_dir' => HOMBU_CACHE_PATH . "/" . $cachePath);

        // Getting a Zend_Cache_Core object and return the object
        $this->cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);

        // Save the cache name
        $this->cacheName = basename($cachePath);
    }

    // Try to retrieve the cache
    // Specify a name, or else the path is used as a name
    public function loadCache($name = null) {
        $cached = $this->cache->load( $name ? $name : $this->cacheName );
        if(!$cached) {
            return null;
        } else {
            return $cached;
        }
    }

    // Save cache
    // Specify a name, or else the path is used as a name
    public function saveCache($toCache = "", $name = null) {
        if($name) {
            $this->cache->save($toCache, $name);
        } else {
            $this->cache->save($toCache, $this->cacheName);
        }
    }
}

?>