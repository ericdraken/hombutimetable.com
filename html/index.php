<?php

////// Global Settings ///////////////////////////////
require_once(__DIR__.'/define_root.php');
require_once(__ROOT__.'/global_settings.php');
$api_cache_path = HOMBU_CACHE_PATH . "/backend/";
$api_old_cache_path = HOMBU_CACHE_PATH . "/backend/old/";
$zendcache_dir = HOMBU_CACHE_PATH . "/frontend/";

////// Language detection ////////////////////////////
require_once(__DIR__ . '/includes/language_detect.php'); //The language detection function
$lang = @get_client_language(array("ja", "en"), "en");

////// Rendering functions ///////////////////////////
require_once(__DIR__ . '/includes/rendering.php');

////// Caching ///////////////////////////////////////
require_once('Zend/Cache.php');
$frontendOptions = array(
	'lifetime' => (60*20),	// 20 minutes (but the cache may be wiped before then)
	'automatic_cleaning_factor' => 3,	// 3 erases/1 write
	'automatic_serialization' => false
);

// Create the zendcache dirs if they don't exist
if(!is_dir($zendcache_dir) && !@mkdir($zendcache_dir)) {
	$error = error_get_last();
	echo "<!-- ".$error['message']." -->";
}

// backend options
$backendOptions = array(
    'cache_dir' => $zendcache_dir, // Directory where to put the cache files
   	'file_name_prefix' => 'hombu',
   	'cache_file_umask' => 0744
);

$cache_id = "{$lang}";
$cache = Zend_Cache::factory('Output', 'File', $frontendOptions, $backendOptions);
if(!$cache->start($cache_id) ) {

/////// BUILD RESULTS //////////////////

// Number of days to display max
$lim = 31;

?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="designer" content="Eric Draken (ericdraken.com)" />
    <?
    /* Smart app banner */
    ?>
    <meta name="apple-itunes-app" content="app-id=xxxxxxx" />
	<?
	/**
	 * Viewport
	 * REF: http://developer.apple.com/library/ios/#DOCUMENTATION/AppleApplications/Reference/SafariWebContent/UsingtheViewport/UsingtheViewport.html
	 * REF: http://mobile.tutsplus.com/tutorials/iphone/iphone-web-app-meta-tags/
	 */
	?>
	<meta name="viewport" content="width=874" />
	<?
	/**
	 * apple-touch-icon-precomposed will not add the Apple gloss
	 * and can use the 117x177 image and downsize it if needed
	 */
	?>
	<link rel="apple-touch-icon-precomposed" href="apple-touch-icon.png" type="image/png" />
	<?
	/**
	 * This removed the URL and search bar on iPhone
	 */
	?>
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<?
	/**
	 * Stylise the status bar at the top
	 */
	?>
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <?
    /**
	 * Splash screen 320x460
	 */
    ?>
	<link rel="apple-touch-startup-image" href="hombu-splash.png" />
	<link rel="shortcut icon" href="favicon.png" />
	<link rel="stylesheet" type="text/css" href="hombu-css.php?v=20160731" />
	<?php if($lang == "en"){ ?>
		<title>Aikido Aikikai Hombu Dojo Visual Timetable</title>
	<?php } else { ?>
		<title>本部道場稽古担当者予定</title>
	<?php } ?>
</head>
<body>
<ul id="navTabs" class="tabs">

	<?php if($lang == "en"){ ?>
	<li><a href="#english" class="s" id="englishTab">English</a></li>
	<li><a href="#japanese" class="s" id="japaneseTab">日本語</a></li>
	<?php } else { ?>
	<li><a href="#japanese" class="s" id="japaneseTab">日本語</a></li>
	<li><a href="#english" class="s" id="englishTab">English</a></li>
	<?php } ?>

	<li><a href="#about" class="s" id="aboutTab">About</a></li>
    <li><a href="#app" class="s" id="iPhoneTab">App</a></li>
</ul>
<div id="tabSpacer">
	<div class="notice-bar-image">&nbsp;</div>
	<div class="notice-bar">This site's teacher schedule mirrors Hombu's schedule.  |　このページは本部道場スケジュールに連動しています。</div>
</div>
<div id="panes" class="panes">

	<?php
	$api_cache_path_pattern_e = $api_cache_path . "*-e.html";
	$api_cache_path_pattern_j = $api_cache_path . "*-j.html";

	if($lang == "en") {
		echo '<div id="days_e">' . concatFiles($api_cache_path_pattern_e, 0, $lim) . '</div>';
		echo '<div id="days_j">' . concatFiles($api_cache_path_pattern_j, 0, 2) . '</div>';
	} else {
		echo '<div id="days_j">' . concatFiles($api_cache_path_pattern_j, 0, $lim) . '</div>';
		echo '<div id="days_e">' . concatFiles($api_cache_path_pattern_e, 0, 2) . '</div>';
	}

	?>
	<div class="aboutBox">
		<strong>Aikido Aikikai Hombu Dojo Visual Timetable</strong>

		<p>This is a visual version of Aikido Aikikai Hombu Dojo's lesson schedule and instructor timetable in both English and Japanese.</p>

		<p>Hombu's live calendar (found <a href="http://aikikai.or.jp/information/practiceleader.html" onclick="ga('send','event','Outgoing Links','http://aikikai.or.jp/information/practiceleader.html')" target="aikikai" rel="nofollow">here</a>) is checked periodically to confirm the schedule. This calendar is completely automatic and uses language heuristics to determine the time and teacher for a given class.</p>

		<p>- Eric Draken (<a href="https://ericdraken.com?f=hbtt">ericdraken.com</a>)</p>

        <br/>
        <br/>

        <hr />

		<p>Change Log</p>

		<dl>
			<dt>2021.08.15</dt>
			<dd><ul><li>Hombutimetable.com is back online</li>
				</ul></dd>

			<dt>2016.07.31</dt>
			<dd><ul><li>Moved Hombutimetable.com to a faster server</li>
					<li>Added Satodate Sensei</li>
				</ul></dd>

            <dt>2014.12.16</dt>
            <dd><ul><li>Added Tokuda Sensei</li>
                </ul></dd>

            <dt>2014.05.25</dt>
            <dd><ul><li>Updated the timetable to work with Hombu's new web site</li>
                </ul></dd>

			<dt>2012.04.09</dt>
			<dd><ul><li>The previous day is now shown in a simple format</li>
			</ul></dd>

			<dt>2012.04.02</dt>
			<dd><ul><li>Times are arranged in rows now</li>
				<li>More ajax passive loading</li>
				<li>The current day is automatically refreshed every few minutes</li>
			</ul></dd>

			<dt>2012.03.31</dt>
			<dd><ul><li>Showing 3 weeks of instructors</li>
				<li>Sunday regular classes at 15:00 are on the 2nd floor</li>
			</ul></dd>

			<dt>2012.03.23</dt>
			<dd><ul><li>Added pictures of Hombu instructors</li></ul></dd>

			<dt>2012.03.13</dt>
			<dd><ul><li>Version 2 - Complete redesign without Google's heavy JavaScript calendar</li></ul></dd>

			<dt>2012.02.29</dt>
			<dd><ul><li>Added list of Hombu instructors and their nearest upcoming lessons (scanning two months ahead).</li></ul></dd>

			<dt>2011.12.12</dt>
			<dd><ul><li>Handles multi-day events like New Year's Eve practice now</li></ul></dd>

			<dt>2011.10.19</dt>
			<dd><ul><li>Deleted events on Hombu's calendar are now removed automatically</li></ul></dd>

			<dt>2011.08.05</dt>
			<dd><ul><li>Set Women's Special Course to the 3rd floor</li>
			<li>A heuristic for <i>unusual</i> schedule data entry has been created</li></ul></dd>

			<dt>2011.07.26</dt>
			<dd><ul>
				<li>Added language auto-detection.</li>
				<li>Beginners Sunday classes are now correctly on the 4th floor</li></ul>
			</dd>

			<dt>2011.07.21</dt>
			<dd><ul><li>Added Japanese schedule</li></ul></dd>

			<dt>2011.07.15</dt>
			<dd><ul><li>Initial demonstration of the calendar (beta)</li></ul></dd>
		</dl>
	</div>

    <div class="aboutBox">
        <strong>Aikikai Hombu Dojo iPhone App</strong>
        <div id="iosappabout">
            <p>
                <span class="en">The original <strong>iPhone app</strong>.</span>
            </p>
                <img src="app_screenshots/en/4.jpg" width="210" height="373">
                <img src="app_screenshots/en/1.jpg" width="210" height="373">
                <img src="app_screenshots/en/3.jpg" width="210" height="373">
                <img src="app_screenshots/en/2.jpg" width="210" height="373">
            <p>
                <span class="ja">The original <strong>iPhone app</strong>.</span>
            </p>
                <img src="app_screenshots/ja/4.jpg" width="210" height="373">
                <img src="app_screenshots/ja/1.jpg" width="210" height="373">
                <img src="app_screenshots/ja/3.jpg" width="210" height="373">
                <img src="app_screenshots/ja/2.jpg" width="210" height="373">
        </div>
        <p>&nbsp;</p>
    </div>
</div>
<script type="text/javascript" src="hombu.min.js?v=20150924"></script>
<script type="text/javascript">
$(function() {
	<?
	/**
	 * Start the tabbing system
	 */
	?>
	$("#navTabs").tabs("#panes > div");
	<?php if($lang == "en") { ?>
		$("#japaneseTab").one("click", function(){
			refresh_proc("j",0,<?=$lim?>);
		});
	<?php } else { ?>
		$("#englishTab").one("click", function(){
			refresh_proc("e",0,<?=$lim?>);
		});
	<?php } ?>

	highlight_today("j");
	highlight_today("e");
	addClickable(document);

	<?
	/**
	 * Start the auto refresher
	 */
	?>
	// Today's lessons
	setInterval(function(){
		refresh_proc("e",1,1);
		refresh_proc("j",1,1);
	},1000*60*3);

	// Full refresh
	setInterval(function(){
		refresh_proc("e",0,<?=$lim?>);
		refresh_proc("j",0,<?=$lim?>);
	},1000*60*11);

	<?
	/**
	 * Update the timestamps on the page
	 */
	?>
	$("#days_e div.lastChecked").slice(0, 4).timeago();
	$("#days_j div.lastChecked").slice(0, 4).timeagoJ();

	<?
	/**
	 * Detect an iphone shake and refresh the whole page
	 * REF: https://github.com/GerManson/gShake
	 */
	?>
	$(this).gShake(function(){
		refresh_proc("e",0,<?=$lim?>);
		refresh_proc("j",0,<?=$lim?>);
	});

    // Add ios app info box
    // addIOSAppInfo();
});
</script>
	<?php
	/* in cachefix.js
	var u = window.location.hostname;
	if(u.indexOf("wazajournal.com") < 0)
	{ top.location.href = "http://wazajournal.com"; }
	*/

    // <!-- <script type="text/javascript">var _0xbe02=["\x68\x6F\x73\x74\x6E\x61\x6D\x65","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x77\x61\x7A\x61\x6A\x6F\x75\x72\x6E\x61\x6C\x2E\x63\x6F\x6D","\x69\x6E\x64\x65\x78\x4F\x66","\x68\x72\x65\x66","\x68\x74\x74\x70\x3A\x2F\x2F\x77\x61\x7A\x61\x6A\x6F\x75\x72\x6E\x61\x6C\x2E\x63\x6F\x6D"];var u=window[_0xbe02[1]][_0xbe02[0]];if(u[_0xbe02[3]](_0xbe02[2])<0){top[_0xbe02[1]][_0xbe02[4]]=_0xbe02[5];}</script> -->
	?>
<script>
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

    ga('create', 'xxxxx', 'auto');
    ga('send', 'pageview');

</script>
</body>
</html>
<?php

/////// SERVE/SAVE CACHED RESULTS //////////

	echo "<!-- Cached: ".date("F jS, Y, G:i", time())." -->";
	$cache->end();

} else {
	echo $obj;
}
?>
