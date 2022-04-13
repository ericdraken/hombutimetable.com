<?php
/*!
 * Draken
 * 2012.04.08
 * */

////// Global Settings ///////////////////////////////
require_once('define_root.php');
require_once(__ROOT__.'/global_settings.php');	
//////////////////////////////////////////////////////

require_once('config.php');

/**
 * Pull the lessons data from the Google calendars
 * using a search between two dates, then organize the
 * events in a visual table to show relative event
 * positions durning the day
 */
class HombuCalRendererV5 {
	
	private $shihans, $woman_pattern, $cachepath;
	
	public function __construct() {
		$this->shihans = homuShihansArray();	// Look in config.php
		$this->cachepath = HOMBU_CACHE_PATH . "/backend/";
		$this->cachepath_old = HOMBU_CACHE_PATH . "/backend/old/";

		$this->woman_pattern = "女|women";
		$this->regular_pattern = "一般|reggular|regu";
		$this->beginner_pattern = "初心|begin|beggin";
		$this->kids_pattern = "少年部|少年|child";
		$this->gakko_pattern = "学校|gakko|gako";
		
		// Create the cache dirs if they don't exist
		if(!file_exists($this->cachepath)) {
			mkdir($this->cachepath); 
		}

		if(!file_exists($this->cachepath_old)) {
			mkdir($this->cachepath_old); 
		}
	} 

	/**
	 * Render a date range to a calendar and save it to disk
	 */
	public function cacheRenderedCalendar(&$Day = null, $lang = 'e', $simple = false) {
		$caldata = $this->renderCalendar($Day, $lang, $simple);
		
		$today = date("Y-m-d", $Day->date);
		$filename = $this->cachepath . "{$today}-{$lang}.html";
		$bytes = @file_put_contents($filename, $caldata, LOCK_EX);
		if(!$bytes) {
			die($filename . " could not be written");
		} else {
			echo_c("Wrote {$bytes} bytes to {$filename}.");
		}
	} 
	
	/**
	 * Move old cached files
	 * $rollover_time = the hour the rollover should happen
	 */
	public function moveOldCache($rollover_time = 21) {
		
		$today_date = time();
		foreach(glob($this->cachepath . "*.html", GLOB_NOSORT) as $file) {
				
			// Move older files (older than the last practice)
			$parts = explode("-", basename($file), 4);	// Limit to 3 dashes
			$file_date = mktime($rollover_time, 0, 0, intval($parts[1]), intval($parts[2]), intval($parts[0]));
			
			if($file_date < $today_date) {
				
				//echo $file_date . " vs " . $today_date . "<br>";
				if( copy($file, $this->cachepath_old . basename($file)) ) {
					unlink($file);
				}
			}
		}			
	}

	public function renderCalendar(&$Day = null, $lang = 'e', $simple = false) {

        $date_epoch = $Day->date;
        $lessons = $Day->lessons;

		$s = "";
		$entries = 0;

        $s .= '<div class="dateEvents'.($simple?" pastDay":"").'">';
            $s .= '<div class="dateHeader">';
                $s .= '<div class="dateTitle">' .($lang=="e"?$this->edate2($date_epoch):$this->jdate2($date_epoch)).'</div>';
                $s .= (!$simple?'<div class="lastChecked" title="' . date("c") . '">' . ($lang=="e"?$this->edate(strtotime("now")):$this->jdate(strtotime("now"))) . '</div>':"");
            $s .= '</div>';
            $s .= '<div class="eventHolder">';

        if(count($lessons) > 0) {
			foreach($lessons as &$Lesson) {
                $lesson_type = $Lesson->lessonType;

				// Skip children classes
				if($lesson_type == "CHILDREN") {
					continue;

				} else {
					
					$entry_class = $this->getEventClasses($lesson_type);
                    $teacherId = $Lesson->teachers[0]->uniqueId;
					$entry_title = $this->adjustTitle($teacherId, $lang);
					$shihan_pic = (!$simple?$this->shihanPicHTML($teacherId):"");
					
					// DEBUG
					//echo "<pre>" . $event_arr[3] . " -- " . htmlspecialchars($shihan_pic) . PHP_EOL . "</pre>";

                    $just_before_teacherId = @$Lesson->changes[0]->teachers[0]->uniqueId;
					$prev_teacher = $this->adjustTitle($just_before_teacherId, $lang);
					$prev_shihan_pic = (!$simple?$this->shihanPicHTML($just_before_teacherId):"");
					
					$entry_short_floor = $Lesson->lessonFloor;
					$entry_time = date('H:i', $Lesson->startDate);
	
					// 2012.04.09 - Make sure now and prev shihans are different
					if($just_before_teacherId == $teacherId) {
						$prev_teacher = "";
					}					
					
					// Event wrapper
					$s .= '<div class="eventWrapper">';
					$sched_data = '<br /><span class="eventdata">' . $entry_time . " - " .$entry_short_floor . "F</span>";
	
					// Add previous teacher
					if(!empty($prev_teacher)) {
						if(!$simple){
							$s .= '<div class="clearBoth round event '.$entry_class.'">'.$entry_title.$sched_data.$shihan_pic.'</div>';
							$s .= '<div class="clearBoth upArrow">&nbsp;</div>';
							$s .= '<div class="event round prevTeacher">(' . $prev_teacher . ')'.$prev_shihan_pic.'</div>';							
						} else {
							$s .= '<div class="clearBoth roundTop event '.$entry_class.'">'.$entry_title.$sched_data.'</div>';
							$s .= '<div class="event roundBottom prevTeacher">(' . $prev_teacher . ')</div>';								
						}
					} else {
						$s .= '<div class="clearBoth round event '.$entry_class.'">'.$entry_title.$sched_data.$shihan_pic.'</div>';
					}
					
					$s .= '</div>';				
				}

				$entries++;
			}
        }

            $s .= '</div>';
        $s .= '</div>';

		// Don't return an empty calendar
		if($entries < 0) {
			return "";
		}

		return $s;
	}
	
	private function jdate($ts) {
	    $dy  = date("w", $ts);
	
	    $dys = array("日","月","火","水","木","金","土");
	    $dyj = $dys[$dy];
	      return "最終確認： ".date('m',$ts) . '月' . date('d',$ts) . ' 日' . '(' . $dyj . ') ' . date('H:i',$ts);
	}

	private function edate($ts) {
		return "Checked: ".date("l M. jS, H:i", $ts);
	}

	private function jdate2($ts) {
	    $dy  = date("w", $ts);
	
	    $dys = array("日","月","火","水","木","金","土");
	    $dyj = $dys[$dy];
	      return date('m',$ts) . '月' . date('d',$ts) . ' 日' . '(' . $dyj . ')';
	}

	private function edate2($ts) {
		return date("l, F jS", $ts);
	}

	/**
	 * Adjust the title
	 */
	private function adjustTitle($title, $lang = "e") {
		
		$name = "";
		
		$offset_index = 1;	// English
		if($lang == "j") {
			$offset_index = 2;	// Japanese
		}
		
		// Scan the list of shihans for a match
		foreach($this->shihans AS $key => $val) {
			if(preg_match('%'.$key.'%i', $title) > 0) {
				// Adding spans to prevent Japanese names from being split
				$name .= "<span>" . $val[$offset_index] . "</span>, ";
			}
		}

		//echo "<br>" . $title . " -- " . $name .  "<br>";

		return rtrim($name, ", ");
	}
	
	/**
	 * Add html to render the shihan pic
	 */
	private function shihanPicHTML($uniqueId) {
		$html = '<div class="pic '. trim($uniqueId) .'" title="' . $val[0] . '">&nbsp;</div>';

		if(empty($uniqueId) || $uniqueId == "") {
			$html = '<div class="pic unknown" title="Not decided yet">&nbsp;</div>';
		}

		return $html;
	}
	
	/**
	 * Set the class of this event based on the floor and title
	 * of the event in question
	 */
	private function getEventClasses($lessonType) {
		
    	$classes = "";
		if($lessonType == "GAKKO")
			$classes .= "gakko";
		else if($lessonType == "CHILDREN")
			$classes .= "children";
		else if($lessonType == "BEGINNER")
			$classes .= "beginner";
		else if($lessonType == "REGULAR")
			$classes .= "regular";
		else if($lessonType == "WOMEN")
			$classes .= "women";

		return $classes;
	}
};

?>