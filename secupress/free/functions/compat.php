<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Find next prime number
 *
 * @since 2.3.14 function named secupress_next_prime()
 * @since 2.2.6
 * @author Julio Potier
 * 
 * @param (int) $n
 * @return (int) $n
 **/
function secupress_next_prime( $n ) {
	if ( function_exists( 'gmp_nextprime' ) && ! secupress_is_function_disabled( 'gmp_nextprime' ) ) {
		return (int) gmp_nextprime( $n );
	}
	$c = $n + ( ( $n <= 2 ? 3 - $n : $n % 2 ) ? 2 : 1 );
	while ( true ) { // Finding a prime is mandatory.
		$m = (int) sqrt( $c ) + 1;
		$i = 3;
		while ( $i <= $m ) {
			if ( $c % $i++ === 0 ) {
				break;
			}
			++$i;
		}
		if ( $i > $m ) {
			return $c;
		}
		$c += 2;
	}
}


if ( ! function_exists( 'mb_strtolower' ) ) {
	/**
	 * Fallback when the mbstring extension is not loaded.
	 *
	 * @since 2.7
	 * @author Julio Potier
	 *
	 * @param (string) $string   The string being lowercased.
	 * @param (string) $encoding Unused, kept for compatibility with mb_strtolower().
	 *
	 * @return (string)
	 */
	function mb_strtolower( $string, $encoding = null ) {
		return strtolower( $string );
	}
}


if ( ! function_exists( 'mb_strpos' ) ) {
	/**
	 * Fallback when the mbstring extension is not loaded.
	 *
	 * @since 2.7
	 * @author Julio Potier
	 *
	 * @param (string) $haystack The string to search in.
	 * @param (string) $needle   The string to search for.
	 * @param (int)    $offset   The search offset.
	 * @param (string) $encoding Unused, kept for compatibility with mb_strpos().
	 *
	 * @return (int|false)
	 */
	function mb_strpos( $haystack, $needle, $offset = 0, $encoding = null ) {
		return strpos( $haystack, $needle, $offset );
	}
}


if ( ! function_exists( 'mb_ord' ) ) {
	/**
	 * Fallback when the mbstring extension is not loaded.
	 *
	 * @since 2.7
	 * @author Julio Potier
	 *
	 * @param (string) $string   A character.
	 * @param (string) $encoding Unused, kept for compatibility with mb_ord().
	 *
	 * @return (int|false)
	 */
	function mb_ord( $string, $encoding = null ) {
		return '' === $string ? false : ord( $string );
	}
}