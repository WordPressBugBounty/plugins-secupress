<?php
/**
 * Module Name: Fix Mixed Content
 * Description: Switch every http:// to https:// in the website content
 * Main Module: ssl
 * Author: Julio Potier
 * Version: 2.2.6
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/**
 * Starts the output buffer for mixed content fix
 *
 * @since 2.2.6
 * @author Julio Potier
 **/
add_action( 'admin_init', 'secupress_ssl_mixed_content_fix_start' );
add_action( 'init', 'secupress_ssl_mixed_content_fix_start' );
function secupress_ssl_mixed_content_fix_start() {
	if ( ! preg_match( '/\.(xsl|xml)$/i', secupress_get_current_url() ) ) {
		ob_start( 'secupress_ssl_mixed_content_fix' );
	}
}

/**
 * Filter the whole site content, replacing http with https
 *
 * @since 2.7 Keep XML namespaces, CSS @namespace, and other identifiers
 * @since 2.3.7 Not for XLS & XML (props Aurélien Denis from SeoPress)
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param (string) $content
 *
 * @return (string) $content
 **/
function secupress_ssl_mixed_content_fix( $content ) {
	$pattern  = '/xmlns(?::[A-Za-z0-9_.:-]+)?\s*=\s*(?:["\']http:\/\/[^"\']*["\']|http:\/\/[^\s>]+)(*SKIP)(*FAIL)';
	$pattern .= '|@namespace\s*(?:[A-Za-z_][\w-]*\s+)?(?:["\']http:\/\/[^"\']*["\']|url\(\s*["\']?http:\/\/[^)\'"]+["\']?\s*\))\s*;(*SKIP)(*FAIL)';
	$pattern .= '|http:\/\/([^\s"\']+)/i';

	$fixed = preg_replace_callback(
		$pattern,
		'__secupress_ssl_mixed_content_callback',
		$content
	);

	return is_string( $fixed ) ? $fixed : $content;
}

/**
 * Replace one http URL, unless it is a namespace or a schema identifier.
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @param (array) $matches
 *
 * @return (string)
 **/
function __secupress_ssl_mixed_content_callback( $matches ) {
	if ( empty( $matches[1] ) ) {
		return $matches[0];
	}
	if ( preg_match( '/\.(?:xsl|xml|dtd|xsd)$/i', $matches[1] ) || secupress_ssl_mixed_content_is_identifier( $matches[1] ) ) {
		return $matches[0];
	}
	return 'https://' . $matches[1];
}

/**
 * Tell if an http URL is an identifier, not a resource loaded by the browser.
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @param (string) $url URL without the scheme.
 *
 * @return (bool)
 **/
function secupress_ssl_mixed_content_is_identifier( $url ) {
	$identifiers = [
		'www.w3.org/2000/svg',
		'www.w3.org/1999/xlink',
		'www.w3.org/1998/Math/MathML',
		'www.w3.org/1999/xhtml',
		'www.w3.org/XML/1998/namespace',
		'www.w3.org/2000/xmlns/',
		'www.w3.org/2001/XMLSchema-instance',
		'www.w3.org/2001/XMLSchema',
		'www.w3.org/1999/XSL/Transform',
		'www.w3.org/1999/XSL/Format',
		'www.w3.org/2001/XInclude',
		'www.w3.org/2005/xpath-functions',
		'www.w3.org/2005/Atom',
		'ogp.me/ns',
		'gmpg.org/xfn/11',
		'purl.org/dc/elements/1.1/',
		'purl.org/dc/terms/',
		'purl.org/dc/dcmitype/',
		'schemas.xmlsoap.org/soap/envelope/',
		'schemas.xmlsoap.org/soap/encoding/',
		'schemas.xmlsoap.org/wsdl/',
	];

	foreach ( $identifiers as $identifier ) {
		if ( 0 !== stripos( $url, $identifier ) ) {
			continue;
		}
		$next = substr( $url, strlen( $identifier ), 1 );
		if ( '' === $next || false !== strpos( '#/?', $next ) ) {
			return true;
		}
	}

	return false;
}
