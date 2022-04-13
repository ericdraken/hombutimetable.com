<?php

	$max_width=874;
	$event_width=86;
	$sv=5; // version 2016.07.31
	
	header("Content-type: text/css; charset: UTF-8");
?>	
	
	html, body {
	  margin: 0;
	  padding: 0;
	  width: <?=$max_width?>px;
	  overflow-x:hidden;
	  font-family: Arial, Helvetica, sans-serif, "MS PGothic", "Meiryo";
	  background-color: #f0f0f0;	
	}

    a {
        text-decoration: none;
    }

	#navTabs {
		z-index:2;
		position: fixed;
		left:0;
		top:0;
		width:<?=$max_width?>px;
		height:30px;
		border-bottom:2px solid #999;
		background-color: #f0f0f0;
	}
	#tabSpacer {
		z-index:2;
		position: fixed;
		background:#fff;
		left:0;
		top:31px;
		width:<?=$max_width-2?>px;
		height:28px;		
		border-bottom:1px solid #999;
		border-left:1px solid #999;
		border-right:1px solid #999;
	}
	
	div.notice-bar-image {
		background: url('notice.png') no-repeat 0 0;
		width: 22px;
		height: 22px;
		margin: 2px;
		float: left;
	}
	div.notice-bar {
		margin: 2px;
		padding: 2px;
		float: left;
		font-size: 0.9em;
	}
	
	.panes {
		z-index:1;
		position:absolute;
		top:32px;
		width:<?=$max_width-2?>px;
		border:1px solid #999;
	}
	
	/* tab pane styling */
	.panes > div {
		display:none;
		padding:26px 0 0 0;
		border-top:0;
		background-color:#fff;
	}

	/* tab pane styling */
	.panes div.aboutBox {
		padding:26px 0 0 10px;
		background-color:#e5ecf9;
        height:100%;
	}

    /* promo box */
    #enpromo .eventHolder > div, #japromo .eventHolder > div {
        padding:0px 0px 0px 10px;
    }
    #enpromo .eventHolder, #japromo .eventHolder {
        background-color:#e5ecf9;
    }

	/* root element for tabs  */
	ul.tabs {
		list-style:none;
		margin:0 !important;
		padding:0;
		border-bottom:1px solid #666;
		height:30px;
	}

	/* single tab */
	ul.tabs li {
		float:left;
		text-indent:0;
		padding:0;
		margin:0 !important;
		list-style-image:none !important;
	}

	/* link inside the tab. uses a background image */
	ul.tabs a {
		background: url('blue.png') no-repeat -420px 0;
		font-size:14px;
		display:block;
		height: 30px;
		line-height:30px;
		width: 134px;
		text-align:center;
		text-decoration:none;
		color:#333;
		padding:0px;
		margin:0px;
		position:relative;
		top:1px;
	}

	ul.tabs a:active {
		outline:none;
	}

	/* when mouse enters the tab move the background image */
	ul.tabs a:hover {
		background-position: -420px -31px;
		color:#fff;
	}

	/* active tab uses a class name "current". its highlight is also done by moving the background image. */
	ul.tabs a.current, ul.tabs a.current:hover, ul.tabs li.current a {
		background-position: -420px -62px;
		cursor:default !important;
		color:#000 !important;
	}

	/* width 1 */
	ul.tabs a.s 			{ background-position: -553px 0; width:81px; }
	ul.tabs a.s:hover 	{ background-position: -553px -31px; }
	ul.tabs a.s.current  { background-position: -553px -62px; }

	/* initially all panes are hidden */
	.panes .pane {
		display:none;
	}

	/*DL, DT, DD TAGS LIST DATA*/
	dl {
		padding-bottom:10px;
	}

	dl dt {
		background:#5f9be3;
		color:#fff;
		font-weight:bold;
		margin-right:10px;
		padding: 5px;
		margin-top: 10px;
		/*width: 100px;*/
	}

	dl dd {
		margin:1px 0;
		/*padding:1px 0;*/
	}
	
	
	/* Today overrides */
	.today {
		/*border-top: 1px solid #000 !important;*/
		/*border-bottom: 1px solid #000 !important;*/
	}
	.today .dateHeader {
		background:#FFCC33 !important;
		background-image: url(date-header.png) !important;
		background-repeat: repeat-x !important;
		border-top: 1px solid #aaa !important;
		border-bottom: 1px solid #aaa !important;		
	}
	.today .eventHolder {
		/*background:#FFCC99 !important;*/
	}
		
		
			
	/* Yesterday */
	.pastDay {
		opacity:0.6;
		filter:alpha(opacity=60); /* For IE8 and earlier */		
	}
	.pastDay .eventHolder {
		padding:1px 1px 2px 1px !important;
	}		
		
		
	/* Current */
	.dateEvents {
		width: <?=$max_width-2?>px;
		float:left;
		clear:both;
	}	
	.dateHeader {
		float:left;
		width:<?=$max_width-6?>px;
		padding:2px;
		background:#ddd;
		background-image: url(date-header.png);
		background-repeat: repeat-x;
		border-top: 1px solid #888;
		border-bottom: 1px solid #888;
	}
	.clickable {
		cursor: pointer;
	}
	.dimmed {
		opacity:0.3;
		filter:alpha(opacity=30); /* For IE8 and earlier */	
	}
	.dateTitle {
		float: left;
		font-size: 1em;
		line-height: 1.1em;
		color: #222;
		padding: 1px 5px 1px 5px;
		font-weight: bold;
	}
	.lastChecked {
		float:left;
		font-size: 0.8em;
		line-height: 1.1em;
		color: #222;
		padding: 2px 5px 1px 5px;
	}
	.lastChecked abbr {
		text-decoration: none;
	}
	.eventHolder {
		float:left;
		margin-top:1px;
		padding:1px 1px 16px 1px;
		background:#F0F0F0;
		color:#202020;
		width:<?=$max_width-4?>px;
		overflow-x:hidden;
		font-size:1em;
	}
	.eventWrapper {
		float:left;
		width:<?=$event_width?>px;
		font-size: 1em;
		line-height: 1.2em;
		margin-right:1px;
	}
	.eventWrapper .eventdata {
		font-size: 0.8em;
		line-height: 0.8em;
	}
	.clearBoth {
		clear:both;
	}
	div.allday {
		width:<?=$max_width-6?>px;
		margin:1px 2px 4px 0px;
		clear: both;
	}
	span.allday {
		font-size: 1em;
		line-height: 1.2em;		
		background:#33CCFF;
		border: 1px solid #aaa;
		background-image: url(overlay.png);
		background-repeat: repeat-x;
		overflow-y: hidden;
		padding:1px 5px 1px 5px;
		clear: both;
	}
	.upArrow {
		float:left;
		width:<?=$event_width-2?>px;
		margin-top:4px;
		height:15px;
		background: url(overlay.png) 29px -200px;
		background-repeat: none;
	}
	.event {
		text-align:center;
		float:left;
		width:<?=$event_width-2?>px;
		background-image: url(overlay.png) !important;
		background-repeat: repeat-x !important;
		line-height: inherit;
	}
	.event span {
		white-space: nowrap;
		line-height: inherit;
	}
	.prevTeacher {
		background:#336699;
		color:#ffffff;
		border: 1px solid #336699;
		line-height: inherit;
	}
	.round {
		border-radius: 5px 5px 5px 5px;
	}
	.roundTop {
		border-radius: 5px 5px 0 0;
	}
	.roundBottom {
		border-radius: 0 0 5px 5px;
	}
	.children {
		background:#FFFF66;
		border: 1px solid #FFCC33;
	}
	.women {
		background-color:#FF99CC;
		border: 1px solid #CC6699;
	}
	.gakko {
		background:#66CC99;	
		border: 1px solid #339966
	}
	.regular {
		background:#FFCC66;
		border: 1px solid #CC9933;
	}
	.beginner {
		background:#99CCFF;
		border: 1px solid #3399FF
	}
	.pic {
		border-width: 1px;
		border-style: solid;
		border-color: inherit;
		width: 80px;
		height: 60px;
		margin: 1px auto 1px auto;
		background: url(shihans.jpg?v=<?=$sv?>) -1680px 0;
	}
	.doshu { background: url(shihans.jpg?v=<?=$sv?>) 0 0 !important; }
	.oyama { background: url(shihans.jpg?v=<?=$sv?>) -80px 0 !important; }
	.irie { background: url(shihans.jpg?v=<?=$sv?>) -160px 0 !important; }
	.hino { background: url(shihans.jpg?v=<?=$sv?>) -240px 0 !important; }
	.ito { background: url(shihans.jpg?v=<?=$sv?>) -320px 0 !important; }
	.mori { background: url(shihans.jpg?v=<?=$sv?>) -400px 0 !important; }
	.uchida { background: url(shihans.jpg?v=<?=$sv?>) -480px 0 !important; }
	.yokota { background: url(shihans.jpg?v=<?=$sv?>) -560px 0 !important; }
	.sugawara { background: url(shihans.jpg?v=<?=$sv?>) -640px 0 !important; }
	.fujimaki { background: url(shihans.jpg?v=<?=$sv?>) -720px 0 !important; }
	.ueshiba { background: url(shihans.jpg?v=<?=$sv?>) -800px 0 !important; }
	.osawa { background: url(shihans.jpg?v=<?=$sv?>) -880px 0 !important; }
	.kuribayashi { background: url(shihans.jpg?v=<?=$sv?>) -960px 0 !important; }
	.miyamoto { background: url(shihans.jpg?v=<?=$sv?>) -1040px 0 !important; }
	.kobayashi { background: url(shihans.jpg?v=<?=$sv?>) -1120px 0 !important; }
	.ksuzuki { background: url(shihans.jpg?v=<?=$sv?>) -1200px 0 !important; }
	.tsuzuki { background: url(shihans.jpg?v=<?=$sv?>) -1280px 0 !important; }
	.namba { background: url(shihans.jpg?v=<?=$sv?>) -1360px 0 !important; }
	.sasaki { background: url(shihans.jpg?v=<?=$sv?>) -1440px 0 !important; }
	.katsurada { background: url(shihans.jpg?v=<?=$sv?>) -1520px 0 !important; }
	.endo { background: url(shihans.jpg?v=<?=$sv?>) -1600px 0 !important; }
	
	.unknown { background: url(shihans.jpg?v=<?=$sv?>) -1680px 0 !important; }
	
	.sakurai { background: url(shihans.jpg?v=<?=$sv?>) -1760px 0 !important; }
	.seki { background: url(shihans.jpg?v=<?=$sv?>) -1840px 0 !important; }
	.yasuno { background: url(shihans.jpg?v=<?=$sv?>) -1920px 0 !important; }
	.toriumi { background: url(shihans.jpg?v=<?=$sv?>) -2000px 0 !important; }
	.masuda { background: url(shihans.jpg?v=<?=$sv?>) -2080px 0 !important; }
	.kanazawa { background: url(shihans.jpg?v=<?=$sv?>) -2160px 0 !important; }
	.kodani { background: url(shihans.jpg?v=<?=$sv?>) -2240px 0 !important; }

    .tokuda { background: url(shihans.jpg?v=<?=$sv?>) -2320px 0 !important; }
	.satodate { background: url(shihans.jpg?v=<?=$sv?>) -2400px 0 !important; }