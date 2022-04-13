<?php

// http://snipplr.com/view/12631/detect-browser-language/
function get_client_language($availableLanguages, $default='en'){

	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {

	$langs=explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);

	//start going through each one
	foreach ($langs as $value){

	$choice=substr($value,0,2);
	if(in_array($choice, $availableLanguages)){
	return $choice;

	}

	}
	}
return $default;
}


////////////////////////////


// FROM: http://urbanoalvarez.es/blog/2008/04/01/language-detection-php/

function dlang($Var)
{
 if(empty($GLOBALS[$Var]))
 {
  $GLOBALS[$Var]=(!empty($GLOBALS['_SERVER'][$Var]))?
  $GLOBALS['_SERVER'][$Var]:
  (!empty($GLOBALS['HTTP_SERVER_VARS'][$Var]))?
  $GLOBALS['HTTP_SERVER_VARS'][$Var]:'';
 }
}

function language()
{
 // Detect HTTP_ACCEPT_LANGUAGE & HTTP_USER_AGENT.
 dlang('HTTP_ACCEPT_LANGUAGE');
 dlang('HTTP_USER_AGENT');

 $_AL=strtolower($GLOBALS['HTTP_ACCEPT_LANGUAGE']);
 $_UA=strtolower($GLOBALS['HTTP_USER_AGENT']);

 // Try to detect Primary language if several languages are accepted.
 foreach($GLOBALS['_LANG'] as $K)
 {
  if(strpos($_AL, $K)===0)
   return $K;
 }

 // Try to detect any language if not yet detected.
 foreach($GLOBALS['_LANG'] as $K)
 {
  if(strpos($_AL, $K)!==false)
   return $K;
 }
 foreach($GLOBALS['_LANG'] as $K)
 {
  if(preg_match("/[\[\( ]{$K}[;,_\-\)]/",$_UA))
   return $K;
 }

 // Return default language if language is not yet detected.
 return $GLOBALS['_DLANG'];
}

// Define default language.
$GLOBALS['_DLANG']='ja';

// Define all available languages.
// WARNING: uncomment all available languages

$GLOBALS['_LANG'] = array(
'en',
'ja'
);
?>
