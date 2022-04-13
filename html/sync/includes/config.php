<?php

/////////// HOMBU PARAMS //////////////

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

define('HOMBU_4TH_FLOOR', "4th floor");
define('HOMBU_3RD_FLOOR', "3rd floor");
define('HOMBU_2ND_FLOOR', "2nd floor");
define('HOMBU_CHILDREN', "CHILDREN");
define('ALL_FLOORS', "all floors");
define('LAST_CHECKED', "last checked");

$translations = array(

	"made" => "まで",
	"l-bracket" => "「",
	"r-bracket" => "」",
	"deshita" => "でした。",
	"izen-ni" => "以前",
	"closed" => "お休み",
	"not_planned" => "予定外",
	"data_lost" => "済",
	"japanese_date" => "Y年m月d日 H:i",
	"last_checked" => "確認日： m月d日 H:i"

);


/////////// HOMBU SHIHANS and Regex //////////////

function homuShihansArray() {

	return array(
	
		"Doshu|道主" => array("Doshu", "Doshu", "道主"),
		"Ueshiba|植芝" => array("Ueshiba Mitsuteru", "Ueshiba", "植芝"),
		"Tada|多田" => array("Tada Hiroshi", "Tada", "多田"),
		"Masuda|増田" => array("Masuda Seijuro", "Masuda", "増田"),
		"Watanabe|渡邊" => array("Watanabe Nobuyuki", "Watanabe", "渡邊"),
		"Endo|遠藤" => array("Endo Seishiro", "Endo", "遠藤"),
		"Yasuno|安野" => array("Yasuno Masatoshi", "Yasuno", "安野"),
		"Seki|関" => array("Seki Shoji", "Seki", "関"),
		"Toriumi|鳥海" => array("Toriumi Koichi", "Toriumi", "鳥海"),
		"Miyamoto|宮本" => array("Miyamoto Tsuruzo", "Miyamoto", "宮本"),
		"Yokota|横田" => array("Yokota Yoshiaki", "Yokota", "横田"),
		"Osawa|大澤|大沢" => array("Osawa Hayato", "Osawa", "大澤"),
		"Kobayashi|小林" => array("Kobayashi Yukimitsu", "Kobayashi", "小林"),
		"Sugawara|菅原" => array("Sugawara Shigeru", "Sugawara", "菅原"),
		"Kuribayashi|栗林" => array("Kuribayashi Takanori", "Kuribayashi", "栗林"),
		"Kanazawa|金沢|金澤" => array("Kanazawa Takeshi", "Kanazawa", "金沢"),
		"Takamizo|高溝" => array("Takamizo Mariko", "Takamizo", "高溝"),
		"Fujimaki|Fuzimaki|藤巻" => array("Fujimaki Hiroshi", "Fujimaki", "藤巻"),
		"Irie|入江" => array("Irie Yoshinobu", "Irie", "入江"),
		"Mori|森" => array("Mori Tomohiro", "Mori", "森"),
		"Sakurai|櫻井|桜井" => array("Sakurai Hiroyuki", "Sakurai", "桜井"),
		"Kats?urada|桂田" => array("Katsurada Eiji", "Katsurada", "桂田"),
		"Namba|Nanba|難波" => array("Namba Hiroyuki", "Namba", "難波"),
		"Ito|伊藤" => array("Ito Makoto", "Ito", "伊藤"),
		"Sasaki|佐々木(\(貞\))?" => array("Sasaki Teijyu", "Sasaki", "佐々木"),
		"Suz[ui]ki|鈴木" => array("Suzuki Toshio", "T.Suzuki", "鈴木(俊)"),
		"Kodani|小谷" => array("Kodani Yuichi", "Kodani", "小谷"),
		"Oyama|小山" => array("Oyama Yuji", "Oyama", "小山"),
		"Uchida|内田" => array("Uchida Naoto", "Uchida", "内田"),
		"Hino|日野" => array("Hino Terumasa", "Hino", "日野"),
		"Tokuda|德田" => array("Tokuda Masaya", "Tokuda", "德田"),
		"Satodate|里舘" => array("Satodate Jun", "Satodate", "里舘")
	);
}

?>