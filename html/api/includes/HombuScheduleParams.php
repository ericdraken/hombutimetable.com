<?php
/**
 * Hombu Params Object
 * Eric Draken, 2012
 */

require_once(__DIR__ . "/HombuConstants.php");

/**
 * An object to hold hombu calendar and scraping params
 */
class HombuScheduleParams {

    //Date yyyy/mm/dd
    //1900/01/01 through 2099/12/31
    //Matches invalid dates such as February 31st
    static private $date_pattern = '(?:19|20)[0-9]{2}\/(?:0[1-9]|1[012])\/(?:0[1-9]|[12][0-9]|3[01])';

    // Parse params
    static public function hombuParserParams() {

        return array(
            "type_filters" => array(
                HombuConstants::CHILDREN => "%少年|child%is",
                HombuConstants::WOMEN => "%女性|女子|women%is",
                HombuConstants::REGULAR => "%一般|道主|reggular|regu|doshu%is",
                HombuConstants::BEGINNER => "%初心|begin|beggin%is",
                HombuConstants::GAKKO => "%学校|gakko|gako%is"
            ),
            "floor_filters" => array(
                // Children's classes on 2nd floor
                "%(少年|children's|child)%is",

                // 4th floor
                "%(合気道学校|学校|gakko|gako)%is",	// This must be higher than the 2nd floor search

                // 3rd floor
                "%(一般|女性|道主|women\'s special|womens special|women special|reggular|regu|doshu)%is",		// Women's special is above women's

                // 2nd floor
                "%(初心者|初心|女子|women|beginner\'s|beginers|begin|beggin)%is"
            ),
            "filters_names" => array(
                HombuConstants::UNPLANNED_DAY,
                HombuConstants::CLOSED_DAY,
                HombuConstants::VALID_DAY
            ),
            "filters" => array(
                // UNPLANNED_DAY
                // <td class="brno center" style="border:none;">- Not Found -</td>
                "%<td class[^>]+?>- Not Found -</td>%i",

                // CLOSED_DAY
                // <td class="brno center" style="border:none;">- Not Found -</td>
                "%<td class[^>]+?>- Not Found -</td>%i",

                // VALID_DAY
                // <h3 class="stitle  bbtm_line2 mb05 fm">2014/04/28 (月) Instructors for Classes</h3> <td class="time">06:30-07:30</td> ... </table>
                "%<h3 class[^>]+?>" . self::$date_pattern . "[^<]+?</h3>.+?(<td class=\"time\">[0-9]{2}.+?)</table>%is"
            ),
            "replacers_names" => array(
                HombuConstants::MULTIPLE_TIMES_EVENTS,
                HombuConstants::SINGLE_TIME_EVENTS,
                HombuConstants::SINGLE_TIME_EVENTS_REVERSED
            ),
            "replacers" => array(
                // 15:30-16:30 16:30-17:30 Children's Ueshiba Hino
                //"%<TD>(([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9]))\s+(([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9]))\s+([^<]+)</TD>%is",
                "%<TD>(([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9]))\s+(([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9]))\s+([^<]+)</TD>%is",

                // <td class="time">06:30-07:30</td>            <td class="brno">Regular　Ueshiba</td>          </tr>
                "%<td class=\"time\">([0-9]|[01][0-9]|2[0-4]):([0-5][0-9]).*?-.*?([0-9]|[01][0-9]|2[0-4]):([0-5][0-9]).+?<td class=\"brno\">([^<]+?)</td>%is",

                //Children's Special Practice in Summer 11:00-12:15
                //"%<TD>([^0-9]+)([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-3])\:([0-5][0-9])</TD>%is"
                "%<TD>([^0-9]+)([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9])\-([0-9]|[01][0-9]|2[0-4])\:([0-5][0-9])</TD>%is"
            )
        );
    }

    /**
     * The data for the scraper
     */
    static public function hombuScraperParams()
    {
        return array(
            // Until April, 2014
//			"url" => "http://203.211.178.140/cgi-bin/mycgi/eachclass.cgi",
//			"referer" => "http://203.211.178.140/cgi-bin/mycgi/eachclass.cgi"

            // From April, 2014
            "url" => "http://aikikai.or.jp/js/load_practice_times.php",
            "referer" => "http://aikikai.or.jp/information/practiceleader.html"
        );
    }

    /////////// HOMBU SHIHANS and Regex //////////////

    static public function homuShihansArray() {

        return array(

            "Doshu|道主" => array("Doshu", "植芝　守央　うえしば もりてる　（道主）", "Doshu", "道主"),
            "Ueshiba|植芝" => array("Ueshiba Mitsuteru", "植芝　充央　うえしば みつてる", "Ueshiba", "植芝"),
            "Tada|多田" => array("Tada Hiroshi (9th dan)", "多田 宏　ただ ひろし　（九段）", "Tada", "多田"),
            "Masuda|増田" => array("Masuda Seijuro (8th dan)", "増田 誠寿郎　ますだ せいじゅうろう　（八段）", "Masuda", "増田"),
            "Watanabe|渡邊" => array("Watanabe Nobuyuki (8th dan)", "渡邊 信之　わたなべ のぶゆき　（八段）", "Watanabe", "渡邊"),
            "Endo|遠藤" => array("Endo Seishiro (8th dan)", "遠藤 征四郎　えんどう せいしろう　（八段）", "Endo", "遠藤"),
            "Yasuno|安野" => array("Yasuno Masatoshi (8th dan)", "安野 正敏　やすの まさとし　（八段）", "Yasuno", "安野"),
            "Seki|関" => array("Seki Shoji (7th dan)", "関 昭二　せき しょうじ　（七段）", "Seki", "関"),
            "Toriumi|鳥海" => array("Toriumi Koichi (7th dan)", "鳥海 幸一　とりうみ こういち　（七段）", "Toriumi", "鳥海"),
            "Miyamoto|宮本" => array("Miyamoto Tsuruzo (7th dan)", "宮本 鶴蔵　みやもと つるぞう　（七段）", "Miyamoto", "宮本"),
            "Yokota|横田" => array("Yokota Yoshiaki (7th dan)", "横田 愛明　よこた よしあき　（七段）", "Yokota", "横田"),
            "Osawa|大澤|大沢" => array("Osawa Hayato (7th dan)", "大澤 勇人　おおさわ はやと　（七段）", "Osawa", "大澤"),
            "Kobayashi|小林" => array("Kobayashi Yukimitsu (7th dan)", "小林 幸光　こばやし ゆきみつ　（七段）", "Kobayashi", "小林"),
            "Sugawara|菅原" => array("Sugawara Shigeru (7th dan)", "菅原 繁　すがわら しげる　（七段）", "Sugawara", "菅原"),
            "Kuribayashi|栗林" => array("Kuribayashi Takanori (7th dan)", "栗林 孝典　くりばやし たかのり　（七段）", "Kuribayashi", "栗林"),
            "Kanazawa|金沢|金澤" => array("Kanazawa Takeshi (7th dan)", "金沢 威　かなざわ たけし  （七段）", "Kanazawa", "金沢"),
            "Takamizo|高溝" => array("Takamizo Mariko (6th dan)", "高溝 真理子　たかみぞ まりこ　（六段）", "Takamizo", "高溝"),
            "Fujimaki|Fuzimaki|藤巻" => array("Fujimaki Hiroshi (6th dan)", "藤巻 宏　ふじまき ひろし　（六段）", "Fujimaki", "藤巻"),
            "Irie|入江" => array("Irie Yoshinobu (6th dan)", "入江 嘉信　いりえ よしのぶ　（六段）", "Irie", "入江"),
            "Mori|森" => array("Mori Tomohiro (6th dan)", "森 智洋　もり ともひろ　（六段）", "Mori", "森"),
            "Sakurai|櫻井|桜井" => array("Sakurai Hiroyuki (6th dan)", "桜井 寛幸　さくらい ひろゆき　（六段）", "Sakurai", "桜井"),
            "Kats?urada|桂田" => array("Katsurada Eiji (6th dan)", "桂田 英路　かつらだ えいじ　（六段）", "Katsurada", "桂田"),
            "Namba|Nanba|難波" => array("Namba Hiroyuki (6th dan)", "難波 弘之　なんば ひろゆき　（六段）", "Namba", "難波"),
            "Ito|伊藤" => array("Ito Makoto (5th dan)", "伊藤 眞　いとう まこと　（五段）", "Ito", "伊藤"),
            "Sasaki|佐々木(\(貞\))?" => array("Sasaki Teijyu (5th dan)", "佐々木 貞樹　ささき ていじゅ　（五段）", "Sasaki", "佐々木"),
            "Suz[ui]ki|鈴木" => array("Suzuki Toshio (5th dan)", "鈴木 俊雄　すずき としお　（五段）", "T.Suzuki", "鈴木(俊)"),
            "Kodani|小谷" => array("Kodani Yuichi (5th dan)", "小谷 佑一　こだに ゆういち　（五段）", "Kodani", "小谷"),
            "Oyama|小山" => array("Oyama Yuji (4th dan)", "小山 雄二　おやま ゆうじ　（四段）", "Oyama", "小山"),
            "Uchida|内田" => array("Uchida Naoto (4th dan)", "内田 直人　うちだ なおと　（四段）", "Uchida", "内田"),
            "Hino|日野" => array("Hino Terumasa (4th dan)", "日野 皓正　ひの てるまさ　（四段）", "Hino", "日野"),
            "Tokuda|德田" => array("Tokuda Masaya (3nd dan)", "德田 雅也　とくだ まさや　（弐段）", "Tokuda", "德田"),
            "Satodate|里舘" => array("Jun Satodate (3nd dan)", "里舘 潤　さとだて じゅん　（弐段）", "Satodate", "里舘")
        );
    }

//    static public function reverseHomuShihansArray() {
//
//        return array(
//
//            "doshu" => array("Doshu", "植芝　守央　うえしば もりてる　（道主）", "Doshu", "道主"),
//            "ueshiba" => array("Ueshiba Mitsuteru", "植芝　充央　うえしば みつてる", "Ueshiba", "植芝"),
//            "tada" => array("Tada Hiroshi (9th dan)", "多田 宏　ただ ひろし　（九段）", "Tada", "多田"),
//            "masuda" => array("Masuda Seijuro (8th dan)", "増田 誠寿郎　ますだ せいじゅうろう　（八段）", "Masuda", "増田"),
//            "watanabe" => array("Watanabe Nobuyuki (8th dan)", "渡邊 信之　わたなべ のぶゆき　（八段）", "Watanabe", "渡邊"),
//            "endo" => array("Endo Seishiro (8th dan)", "遠藤 征四郎　えんどう せいしろう　（八段）", "Endo", "遠藤"),
//            "yasuno" => array("Yasuno Masatoshi (8th dan)", "安野 正敏　やすの まさとし　（八段）", "Yasuno", "安野"),
//            "seki" => array("Seki Shoji (7th dan)", "関 昭二　せき しょうじ　（七段）", "Seki", "関"),
//            "toriumi" => array("Toriumi Koichi (7th dan)", "鳥海 幸一　とりうみ こういち　（七段）", "Toriumi", "鳥海"),
//            "miyamoto" => array("Miyamoto Tsuruzo (7th dan)", "宮本 鶴蔵　みやもと つるぞう　（七段）", "Miyamoto", "宮本"),
//            "yokota" => array("Yokota Yoshiaki (7th dan)", "横田 愛明　よこた よしあき　（七段）", "Yokota", "横田"),
//            "osawa" => array("Osawa Hayato (7th dan)", "大澤 勇人　おおさわ はやと　（七段）", "Osawa", "大澤"),
//            "kobayashi" => array("Kobayashi Yukimitsu (7th dan)", "小林 幸光　こばやし ゆきみつ　（七段）", "Kobayashi", "小林"),
//            "sugawara" => array("Sugawara Shigeru (7th dan)", "菅原 繁　すがわら しげる　（七段）", "Sugawara", "菅原"),
//            "kuribayashi" => array("Kuribayashi Takanori (7th dan)", "栗林 孝典　くりばやし たかのり　（七段）", "Kuribayashi", "栗林"),
//            "kanazawa" => array("Kanazawa Takeshi (7th dan)", "金沢 威　かなざわ たけし  （七段）", "Kanazawa", "金沢"),
//            "takamizo" => array("Takamizo Mariko (6th dan)", "高溝 真理子　たかみぞ まりこ　（六段）", "Takamizo", "高溝"),
//            "fujimaki" => array("Fujimaki Hiroshi (6th dan)", "藤巻 宏　ふじまき ひろし　（六段）", "Fujimaki", "藤巻"),
//            "irie" => array("Irie Yoshinobu (6th dan)", "入江 嘉信　いりえ よしのぶ　（六段）", "Irie", "入江"),
//            "mori" => array("Mori Tomohiro (6th dan)", "森 智洋　もり ともひろ　（六段）", "Mori", "森"),
//            "sakurai" => array("Sakurai Hiroyuki (6th dan)", "桜井 寛幸　さくらい ひろゆき　（六段）", "Sakurai", "桜井"),
//            "katsurada" => array("Katsurada Eiji (6th dan)", "桂田 英路　かつらだ えいじ　（六段）", "Katsurada", "桂田"),
//            "namba" => array("Namba Hiroyuki (6th dan)", "難波 弘之　なんば ひろゆき　（六段）", "Namba", "難波"),
//            "ito" => array("Ito Makoto (5th dan)", "伊藤 眞　いとう まこと　（五段）", "Ito", "伊藤"),
//            "sasaki" => array("Sasaki Teijyu (5th dan)", "佐々木 貞樹　ささき ていじゅ　（五段）", "Sasaki", "佐々木"),
//            "ksuzuki" => array("Suzuki Koujirou (5th dan)", "鈴木 孝次郎　すずき こうじろう　（五段）", "K.Suzuki", "鈴木(孝)"),
//            "tsuzuki" => array("Suzuki Toshio (5th dan)", "鈴木 俊雄　すずき としお　（五段）", "T.Suzuki", "鈴木(俊)"),
//            "kodani" => array("Kodani Yuichi (5th dan)", "小谷 佑一　こだに ゆういち　（五段）", "Kodani", "小谷"),
//            "oyama" => array("Oyama Yuji (4th dan)", "小山 雄二　おやま ゆうじ　（四段）", "Oyama", "小山"),
//            "uchida" => array("Uchida Naoto (4th dan)", "内田 直人　うちだ なおと　（四段）", "Uchida", "内田"),
//            "hino" => array("Hino Terumasa (4th dan)", "日野 皓正　ひの てるまさ　（四段）", "Hino", "日野"),
//            "tokuda" => array("Tokuda Masaya (2nd dan)", "德田 雅也　とくだ まさや　（弐段）", "Tokuda", "德田"),
//            "satodate" => array("Jun Satodate (2nd dan)", "里舘 潤　さとだて じゅん　（弐段）", "Satodate", "里舘")
//        );
//    }

}


?>