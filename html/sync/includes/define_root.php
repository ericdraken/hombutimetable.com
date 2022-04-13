<?php
/**
 * Try to define the __ROOT__ constant
 * 
 * Eric Draken
 * 2012.03.07
 */

// Cronjob safe
if(!defined("__ROOT__")) {
	
	// html is the docroot; get the dir below this
	$paths = array();
	$paths[] = ((isset($_SERVER["argv"]) && isset($_SERVER["argv"][0])) ? $_SERVER["argv"][0] : getcwd());
	$paths[] = @$_SERVER["DOCUMENT_ROOT"];
	$paths[] = @$_ENV["PHP_INI_SCAN_DIR"];

	foreach($paths as $path) {
		$_root = preg_replace('%^(.+?)('.preg_quote(DIRECTORY_SEPARATOR).'html'.preg_quote(DIRECTORY_SEPARATOR).'?)(.*)$%', '$1', $path, 1, $count);
		if($count > 0) {
			define('__ROOT__', $_root);
			break;
		}
	}
}

?>