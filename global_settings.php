<?php

// Global configuration file
if(!defined('GLOBAL_CONFIG_LOADED')) {
	define('GLOBAL_CONFIG_LOADED', true);
	
	// Define __ROOT__
	require_once('define_root.php');
	
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	
	// Set the default timezone to Japan
	// REF: http://www.php.net/manual/en/timezones.php
	// DEFINED IN .htaccess
	// DEFINED in php5.ini
	date_default_timezone_set("Asia/Tokyo");

	/**************************
	 * SPECIAL SUBDIRECTORIES *
	 **************************/

	 define('HOMBUTIMETABLE_URL', $protocol.'hombutimetable.com/');
	 define('HOMBU_CACHE_PATH', realpath(__ROOT__ . '/hombucache/'));
     define('HOMBU_API_PATH', realpath(__ROOT__ . '/html/api/'));

	/****************************
	 * SITE WIDE INCLUDES PATHS *
	 ****************************/
	
	$paths = array();
	$paths[] = get_include_path();
	$paths[] = realpath(__ROOT__ . '/frameworks/');
	$paths[] = realpath(__ROOT__ . '/frameworks/Net_GeoIP-1.0.0/');
	$paths[] = realpath(__ROOT__ . '/frameworks/ZendFramework-1.11.11/library/');
	set_include_path(implode(PATH_SEPARATOR, $paths));	
	
	// Load the development settings or production settings
	require_once(__ROOT__ . '/production_settings.php');
}
?>