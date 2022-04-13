<?php

////// Global Settings ///////////////////////////////
require_once(__DIR__.'/define_root.php');
require_once(__ROOT__.'/global_settings.php');	
$api_cache_path = HOMBU_CACHE_PATH . "/backend/";

////// Rendering functions ///////////////////////////
require_once(__DIR__ . '/includes/rendering.php');

// Skip
$skip = (isset($_REQUEST["s"])?intval($_REQUEST["s"]):0);
if($skip < 0 || $skip > 60) {
	$skip = 0;
}

// Limit
$limit = (isset($_REQUEST["lim"])?intval($_REQUEST["lim"]):1);
if($limit < 1 || $limit > 60) {
	$limit = 1;
}

// Language
$lang = strtolower(isset($_REQUEST["lang"])?$_REQUEST["lang"]:"e");
if($lang != "e" && $lang != "j") {
	$lang = "e";
}

$api_cache_path_pattern = "{$api_cache_path}*-{$lang}.html";

echo concatFiles($api_cache_path_pattern, $skip, $limit);
?>