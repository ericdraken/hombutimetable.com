<?php

	if(!defined('GLOBAL_CONFIG_LOADED')) {
		die("Please include 'global_config.php' instead");
	}

	/***********************
	 * Production Settings *
	 ***********************/

    define('PRODUCTION_ENVIRONMENT', true);

	// HOMBUTIMETABLE
	
	// Hosted data
	// Godaddy (2012.01.11) - IP: 50.63.68.106
	// Godaddy localhost - Since Aug 1, 2016
	define('HOMBUTIMETABLE_DB_NAME', 'hombutimetable');
	define('HOMBUTIMETABLE_DB_USER', 'hombutimetable');
	define('HOMBUTIMETABLE_DB_PASSWORD', 'xxxxx');
	define('HOMBUTIMETABLE_DB_HOST', 'localhost');	// Since Aug 1, 2016
	define('HOMBUTIMETABLE_TABLE_PREFIX', "ht_");
		
	
	/*************
	 * DEBUGGING *
	 *************/	
	
	// These are the settings for production version of the site 
	error_reporting(0);

	// Turn on debuggers related to parsing the SQL query
	define('SQL_DEBUG', false);

	// The search API - cache DB query results (DB level)
	define('CACHE_SQL_QUERIES', true);
	
	// The search processor - cache processed results (PHP level)
	define('CACHE_PHP_SEARCH_RESULTS', true);
	
	// The ajax processor - cache processed results (Javascript level)
	define('CACHE_AJAX_SEARCH_RESULTS', true);

?>
