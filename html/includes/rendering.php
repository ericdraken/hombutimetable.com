<?php

// Render all the cache files in range
function concatFiles($api_cache_path_pattern, $skip = 0, $limit = 31, $min_file_size = 150, $reverse = false) {
	
	$i = 0;
	$s = 0;
	$out = "";
	
	$files = (!$reverse?glob($api_cache_path_pattern):array_reverse(glob($api_cache_path_pattern)));
	foreach($files as $file) {
		
		// Skip some files
		if($s < $skip) {
			$s++;
			continue;
		}
		
		$str = @file_get_contents($file) . "<br />";
		
		// Prevent eventless dates from showing up
		if(strlen($str) > $min_file_size) {
			$out .= $str;
			$i++;
		}
		
		// Only get some days
		if($i >= $limit) {
			break;
		}
	}
	return $out;
}

// Render all the cache files in range
function doFiles($api_cache_path_pattern) {
	echo concatFiles($api_cache_path_pattern, 0, 31, false, 150);
}

?>