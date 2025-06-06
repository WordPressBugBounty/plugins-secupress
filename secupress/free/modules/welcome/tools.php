<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

add_filter( 'admin_body_class', 'secupress_no_contextual_help_add_css_body_class' );
/**
 * Add the no contexttual help as css class
 *
 * @since 2.3.17
 * @author Julio Potier
 * 
 * @param (string) $classes
 * @return (string) $classes
 **/
function secupress_no_contextual_help_add_css_body_class( $classes ) {
	$classes .= 'secupress-no-contextual-help';
	return $classes;
}
