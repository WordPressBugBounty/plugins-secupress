<?php
/**
 * Module Name: Block Bad Referers
 * Description: If you don't want that some website can link to yours, use this plugin.
 * Main Module: firewall
 * Author: SecuPress
 * Version: 2.8
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'init', 'secupress_check_and_block_refs' );
/**
 * Block bad referers
 *
 * 8G FIREWALL
 * https://perishablepress.com/8g-firewall/
 *
 * @since 2.7.1 Moved to Free
 * @since 2.7 Add 8G regex data file
 * @since 2.2.6 Add secupress_block_bad_content_but_what( 'referer', 'HTTP_REFERER', 'BRC' );
 * @since 2.0
 * @author Julio Potier
 *
 * @return void
 **/
function secupress_check_and_block_refs() {
	$param = 'HTTP_REFERER';
	if ( isset( $_SERVER[ $param ] ) && ! empty( $_SERVER[ $param ] ) ) {
		if ( ! secupress_is_scan_request() ) {
			secupress_firewall_block_regex_slug( 'ng_http_referer', $_SERVER[ $param ], 'BRC' );
		}
		secupress_block_bad_content_but_what( 'referer', $param, 'BRC' );
		$referers = array_filter( explode( ',', secupress_get_module_option( 'bbq-headers_bad-referer-list', '', 'firewall' ) ) );
		if ( empty( $referers ) ) {
			return;
		}
		foreach ( $referers as $ref ) {
			if ( 0 === strpos( $_SERVER[ $param ], trim( $ref ) ) ) {
				secupress_block( 'BRU', [ 'code' => 400, 'b64' => [ 'data' => esc_html( $_SERVER[ $param ] ) ] ] );
			}
		}
	}
}
