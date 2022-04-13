<?php

/**
 * Eric Draken
 * 2012.01.03
 * 
 * I pulled out some useful functions from WP
 * 
 * From WP 3.3.0
 */

class WPFormattingUtils {
	
	/**
	 * Merge user defined arguments into defaults array.
	 *
	 * This function is used throughout WordPress to allow for both string or array
	 * to be merged into another array.
	 *
	 * @since 2.2.0
	 *
	 * @param string|array $args Value to merge with $defaults
	 * @param array $defaults Array that serves as the defaults.
	 * @return array Merged user defined values with defaults.
	 * 
	 * REF: http://phpxref.ftwr.co.uk/wordpress/nav.html?_functions/index.html
	 */
	static function wp_parse_args( $args, $defaults = '' ) {
		if ( is_object( $args ) )
			$r = get_object_vars( $args );
		elseif ( is_array( $args ) )
			$r =& $args;
		else
			self::wp_parse_str( $args, $r );
	
		if ( is_array( $defaults ) )
			return array_merge( $defaults, $r );
		return $r;
	}	
	
	/**
	 * Parses a string into variables to be stored in an array.
	 *
	 * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
	 * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
	 *
	 * @since 2.2.1
	 * @uses apply_filters() for the 'wp_parse_str' filter.
	 *
	 * @param string $string The string to be parsed.
	 * @param array $array Variables will be stored in this array.
	 */
	static function wp_parse_str( $string, &$array ) {
		parse_str( $string, $array );
		if ( get_magic_quotes_gpc() )
			$array = self::stripslashes_deep( $array );
	}	
	
	/**
	 * Navigates through an array and removes slashes from the values.
	 *
	 * If an array is passed, the array_map() function causes a callback to pass the
	 * value back to the function. The slashes from this value will removed.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $value The array or string to be stripped.
	 * @return array|string Stripped array (or string in the callback).
	 */
	static function stripslashes_deep($value) {
		if ( is_array($value) ) {
			$value = array_map('stripslashes_deep', $value);
		} elseif ( is_object($value) ) {
			$vars = get_object_vars( $value );
			foreach ($vars as $key=>$data) {
				$value->{$key} = self::stripslashes_deep( $data );
			}
		} else {
			$value = stripslashes($value);
		}
	
		return $value;
	}
	
	/**
	 * Escaping for HTML attributes.
	 *
	 * @since 2.8.0
	 *
	 * @param string $text
	 * @return string
	 */
	static function esc_attr( $text ) {
		$safe_text = self::wp_check_invalid_utf8( $text );
		$safe_text = self::_wp_specialchars( $safe_text, ENT_QUOTES );
		return $safe_text;
	}
	
	// Synonym function
	static function esc_html( $text ) { return self::esc_attr($text); }
	
	/**
	 * Checks for invalid UTF8 in a string.
	 *
	 * @since 2.8
	 *
	 * @param string $string The text which is to be checked.
	 * @param boolean $strip Optional. Whether to attempt to strip out invalid UTF8. Default is false.
	 * @return string The checked text.
	 */
	static function wp_check_invalid_utf8( $string, $strip = false ) {
		$string = (string) $string;
	
		if ( 0 === strlen( $string ) ) {
			return '';
		}
	
		// Store the site charset as a static to avoid multiple calls to get_option()
		static $is_utf8;
		if ( !isset( $is_utf8 ) ) {
			//$is_utf8 = in_array( get_option( 'blog_charset' ), array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) );
			$is_utf8 = true;	// Wazajournal does!
		}
		if ( !$is_utf8 ) {
			return $string;
		}
	
		// Check for support for utf8 in the installed PCRE library once and store the result in a static
		static $utf8_pcre;
		if ( !isset( $utf8_pcre ) ) {
			$utf8_pcre = @preg_match( '/^./u', 'a' );
		}
		// We can't demand utf8 in the PCRE installation, so just return the string in those cases
		if ( !$utf8_pcre ) {
			return $string;
		}
	
		// preg_match fails when it encounters invalid UTF8 in $string
		if ( 1 === @preg_match( '/^./us', $string ) ) {
			return $string;
		}
	
		// Attempt to strip the bad chars if requested (not recommended)
		if ( $strip && function_exists( 'iconv' ) ) {
			return iconv( 'utf-8', 'utf-8', $string );
		}
	
		return '';
	}
	
	/**
	 * Converts a number of special characters into their HTML entities.
	 *
	 * Specifically deals with: &, <, >, ", and '.
	 *
	 * $quote_style can be set to ENT_COMPAT to encode " to
	 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
	 *
	 * @since 1.2.2
	 *
	 * @param string $string The text which is to be encoded.
	 * @param mixed $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
	 * @param string $charset Optional. The character encoding of the string. Default is false.
	 * @param boolean $double_encode Optional. Whether to encode existing html entities. Default is false.
	 * @return string The encoded text with HTML entities.
	 */
	static function _wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
		$string = (string) $string;
	
		if ( 0 === strlen( $string ) )
			return '';
	
		// Don't bother if there are no specialchars - saves some processing
		if ( ! preg_match( '/[&<>"\']/', $string ) )
			return $string;
	
		// Account for the previous behaviour of the function when the $quote_style is not an accepted value
		if ( empty( $quote_style ) )
			$quote_style = ENT_NOQUOTES;
		elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) )
			$quote_style = ENT_QUOTES;
	
		// Store the site charset as a static to avoid multiple calls to wp_load_alloptions()
		//if ( ! $charset ) {
		//	static $_charset;
		//	if ( ! isset( $_charset ) ) {
		//		$alloptions = wp_load_alloptions();
		//		$_charset = isset( $alloptions['blog_charset'] ) ? $alloptions['blog_charset'] : '';
		//	}
		//	$charset = $_charset;
		//}
	
		//if ( in_array( $charset, array( 'utf8', 'utf-8', 'UTF8' ) ) )
			$charset = 'UTF-8';
	
		$_quote_style = $quote_style;
	
		if ( $quote_style === 'double' ) {
			$quote_style = ENT_COMPAT;
			$_quote_style = ENT_COMPAT;
		} elseif ( $quote_style === 'single' ) {
			$quote_style = ENT_NOQUOTES;
		}
	
		// Handle double encoding ourselves
		if ( $double_encode ) {
			$string = @htmlspecialchars( $string, $quote_style, $charset );
		} else {
			// Decode &amp; into &
			$string = self::wp_specialchars_decode( $string, $_quote_style );
	
			// Guarantee every &entity; is valid or re-encode the &
			//$string = wp_kses_normalize_entities( $string );
	
			// Now re-encode everything except &entity;
			$string = preg_split( '/(&#?x?[0-9a-z]+;)/i', $string, -1, PREG_SPLIT_DELIM_CAPTURE );
	
			for ( $i = 0; $i < count( $string ); $i += 2 )
				$string[$i] = @htmlspecialchars( $string[$i], $quote_style, $charset );
	
			$string = implode( '', $string );
		}
	
		// Backwards compatibility
		if ( 'single' === $_quote_style )
			$string = str_replace( "'", '&#039;', $string );
	
		return $string;
	}
	
	/**
	 * Converts a number of HTML entities into their special characters.
	 *
	 * Specifically deals with: &, <, >, ", and '.
	 *
	 * $quote_style can be set to ENT_COMPAT to decode " entities,
	 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
	 *
	 * @since 2.8
	 *
	 * @param string $string The text which is to be decoded.
	 * @param mixed $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old _wp_specialchars() values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
	 * @return string The decoded text without HTML entities.
	 */
	static function wp_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
		$string = (string) $string;
	
		if ( 0 === strlen( $string ) ) {
			return '';
		}
	
		// Don't bother if there are no entities - saves a lot of processing
		if ( strpos( $string, '&' ) === false ) {
			return $string;
		}
	
		// Match the previous behaviour of _wp_specialchars() when the $quote_style is not an accepted value
		if ( empty( $quote_style ) ) {
			$quote_style = ENT_NOQUOTES;
		} elseif ( !in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
			$quote_style = ENT_QUOTES;
		}
	
		// More complete than get_html_translation_table( HTML_SPECIALCHARS )
		$single = array( '&#039;'  => '\'', '&#x27;' => '\'' );
		$single_preg = array( '/&#0*39;/'  => '&#039;', '/&#x0*27;/i' => '&#x27;' );
		$double = array( '&quot;' => '"', '&#034;'  => '"', '&#x22;' => '"' );
		$double_preg = array( '/&#0*34;/'  => '&#034;', '/&#x0*22;/i' => '&#x22;' );
		$others = array( '&lt;'   => '<', '&#060;'  => '<', '&gt;'   => '>', '&#062;'  => '>', '&amp;'  => '&', '&#038;'  => '&', '&#x26;' => '&' );
		$others_preg = array( '/&#0*60;/'  => '&#060;', '/&#0*62;/'  => '&#062;', '/&#0*38;/'  => '&#038;', '/&#x0*26;/i' => '&#x26;' );
	
		if ( $quote_style === ENT_QUOTES ) {
			$translation = array_merge( $single, $double, $others );
			$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
		} elseif ( $quote_style === ENT_COMPAT || $quote_style === 'double' ) {
			$translation = array_merge( $double, $others );
			$translation_preg = array_merge( $double_preg, $others_preg );
		} elseif ( $quote_style === 'single' ) {
			$translation = array_merge( $single, $others );
			$translation_preg = array_merge( $single_preg, $others_preg );
		} elseif ( $quote_style === ENT_NOQUOTES ) {
			$translation = $others;
			$translation_preg = $others_preg;
		}
	
		// Remove zero padding on numeric entities
		$string = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $string );
	
		// Replace characters according to translation table
		return strtr( $string, $translation );
	}

	/**
	 * Converts value to nonnegative integer.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $maybeint Data you wish to have converted to a nonnegative integer
	 * @return int An nonnegative integer
	 */
	static function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
		
	/**
	 * Sanitizes title or use fallback title.
	 *
	 * Specifically, HTML and PHP tags are stripped. Further actions can be added
	 * via the plugin API. If $title is empty and $fallback_title is set, the latter
	 * will be used.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The string to be sanitized.
	 * @param string $fallback_title Optional. A title to use if $title is empty.
	 * @param string $context Optional. The operation for which the string is sanitized
	 * @return string The sanitized string.
	 */
	static function sanitize_title($title, $fallback_title = '', $context = 'save') {
		$raw_title = $title;
	
		if ( 'save' == $context )
			$title = self::remove_accents($title);
	
		//$title = apply_filters('sanitize_title', $title, $raw_title, $context);
	
		if ( '' === $title || false === $title )
			$title = $fallback_title;
	
		return $title;
	}
		
	/**
	 * Converts all accent characters to ASCII characters.
	 *
	 * If there are no accent characters, then the string given is just returned.
	 *
	 * @since 1.2.1
	 *
	 * @param string $string Text that might have accent characters
	 * @return string Filtered string with replaced "nice" characters.
	 */
	static function remove_accents($string) {
		if ( !preg_match('/[\x80-\xff]/', $string) )
			return $string;
	
		if (self::seems_utf8($string)) {
			$chars = array(
			// Decompositions for Latin-1 Supplement
			chr(194).chr(170) => 'a', chr(194).chr(186) => 'o',
			chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
			chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
			chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
			chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C',
			chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
			chr(195).chr(138) => 'E', chr(195).chr(139) => 'E',
			chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
			chr(195).chr(142) => 'I', chr(195).chr(143) => 'I',
			chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
			chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
			chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
			chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
			chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
			chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
			chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
			chr(195).chr(160) => 'a', chr(195).chr(161) => 'a',
			chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
			chr(195).chr(164) => 'a', chr(195).chr(165) => 'a',
			chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
			chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
			chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
			chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
			chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
			chr(195).chr(176) => 'd', chr(195).chr(177) => 'n',
			chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
			chr(195).chr(180) => 'o', chr(195).chr(181) => 'o',
			chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
			chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
			chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
			chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',
			chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
			// Decompositions for Latin Extended-A
			chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
			chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
			chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
			chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
			chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
			chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
			chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
			chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
			chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
			chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
			chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
			chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
			chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
			chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
			chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
			chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
			chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
			chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
			chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
			chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
			chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
			chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
			chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
			chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
			chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
			chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
			chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
			chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
			chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
			chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
			chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
			chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
			chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
			chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
			chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
			chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
			chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
			chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
			chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
			chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
			chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
			chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
			chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
			chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
			chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
			chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
			chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
			chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
			chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
			chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
			chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
			chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
			chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
			chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
			chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
			chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
			chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
			chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
			chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
			chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
			chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
			chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
			chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
			chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
			// Decompositions for Latin Extended-B
			chr(200).chr(152) => 'S', chr(200).chr(153) => 's',
			chr(200).chr(154) => 'T', chr(200).chr(155) => 't',
			// Euro Sign
			chr(226).chr(130).chr(172) => 'E',
			// GBP (Pound) Sign
			chr(194).chr(163) => '');
	
			$string = strtr($string, $chars);
		} else {
			// Assume ISO-8859-1 if not UTF-8
			$chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
				.chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
				.chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
				.chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
				.chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
				.chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
				.chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
				.chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
				.chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
				.chr(252).chr(253).chr(255);
	
			$chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
	
			$string = strtr($string, $chars['in'], $chars['out']);
			$double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
			$double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
			$string = str_replace($double_chars['in'], $double_chars['out'], $string);
		}
	
		return $string;
	}	

	/**
	 * Checks to see if a string is utf8 encoded.
	 *
	 * NOTE: This function checks for 5-Byte sequences, UTF8
	 *       has Bytes Sequences with a maximum length of 4.
	 *
	 * @author bmorel at ssi dot fr (modified)
	 * @since 1.2.1
	 *
	 * @param string $str The string to be checked
	 * @return bool True if $str fits a UTF-8 model, false otherwise.
	 */
	static function seems_utf8($str) {
		$length = strlen($str);
		for ($i=0; $i < $length; $i++) {
			$c = ord($str[$i]);
			if ($c < 0x80) $n = 0; # 0bbbbbbb
			elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
			elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
			elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
			elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
			elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
			else return false; # Does not match any model
			for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
				if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
					return false;
			}
		}
		return true;
	}
	
	// Eric Draken
	// Force bracketed items to the lower line
	// This is a hack on a non-utf editor
	// http://people.w3.org/rishida/tools/conversion/
	static function newlineBracketedItems($str){
		return urldecode(str_replace(array('%EF%BC%88','%28'), '<br/>(', str_replace('%EF%BC%89', ')', rawurlencode($str)) ));
	}
	
	// Split long names
	static function newlineLongItems($str, $charlimit = 20) {
		
		// Replace asian brackets with ASCII brackets
		$str = urldecode(str_replace(array('%EF%BC%88','%28'), '(', str_replace('%EF%BC%89', ')', rawurlencode($str)) ));
		
		// e.g. Ueshiba Kisshomaru (Past Doshu)
		
		// Initial check
		if(strlen($str) > $charlimit) {
		
			// See if there is a bracket quantity
			$parts = explode("(", $str);
			
			// There is a bracketed quantity
			if(count($parts) == 2) {
				$parts[1] = "(" . $parts[1];	// Put the bracket back on
			
				// Work with both parts from now
				// e.g. 
				// 0 => Ueshiba Kisshomaru 
				// 1 => (Past Doshu)
				
				if(strlen($parts[0]) <= $charlimit) {
					// Apply a simple break
					return $parts[0] . "<br/>" . $parts[1]; 	
				} else {
					// Try to break the name some more
					$names = explode(" ", $parts[0]);
					
					// Two names?
					if(count($names) == 2) {
						// See what split combination works:
						// Split in the middle of the names (no break at the bracketed quanity)?
						if(strlen($names[1] . " " . $parts[1]) <= $charlimit) {
							return $names[0] . "<br/>" . $names[1] . " " . $parts[1];
						} else {
							return $names[0] . "<br/>" . $names[1] . "<br/>" . $parts[1];
						}
					} else {
						// Nothing we can do but split the line at the brackets
						return $parts[0] . "<br/>" . $parts[1]; 
					}
				}
				
			// There is no bracketed quantity
			} else {
				
				// Try to break the name some more
				$names = explode(" ", $str);
				
				// Three names?
				if(count($names) == 3) {
					// See what split combination works:
					if(strlen($names[0] . " " . $names[1]) <= $charlimit) {
						return $names[0] . " " . $names[1] . "<br/>" . $names[2];
					} else {
						return $names[0] . "<br/>" . $names[1] . " " . $names[2];
					}
					
				// Two names?
				} else if(count($names) == 2) {
					return $names[0] . "<br/>" . $names[1];
				}
			}
		}

		return $str;
	}			
}
?>