<?php
/**
 * Module Name: Disable Application Passwords
 * Description: Disable Application Passwords on this site.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.7.2
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_filter( 'wp_is_application_passwords_available', '__return_false' );

add_filter( 'determine_current_user', 'secupress_log_disabled_application_password_request', 19 );
/**
 * Log a REST/XML-RPC Application Password authentication attempt.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param $user_id (int|false)
 *
 * @return (int|false)
 */
function secupress_log_disabled_application_password_request( $user_id ) {
	if ( ! empty( $user_id ) ) {
		return $user_id;
	}

	if ( ! isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
		return $user_id;
	}

	$is_api_request = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	$is_api_request = apply_filters( 'application_password_is_api_request', $is_api_request );

	if ( ! $is_api_request ) {
		return $user_id;
	}

	secupress_maybe_log_disabled_application_password_attempt();

	return $user_id;
}

add_action( 'application_password_failed_authentication', 'secupress_maybe_log_disabled_application_password_attempt' );
/**
 * Log a refused Application Password authentication attempt once per request.
 *
 * @author Julio Potier
 * @since 2.7.2
 */
function secupress_maybe_log_disabled_application_password_attempt() {
	static $logged = false;

	if ( $logged ) {
		return;
	}

	$logged = true;
	secupress_log_attack( 'loginattempts' );
}

add_filter( 'get_user_metadata', 'secupress_hide_application_passwords_meta', 0, 4 );
/**
 * Hide stored Application Passwords from WordPress without deleting them.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param $check (null|bool|mixed)
 * @param $object_id (int)
 * @param $meta_key (string)
 * @param $single (bool)
 *
 * @return (null|string|array)
 */
function secupress_hide_application_passwords_meta( $check, $object_id, $meta_key, $single ) {
	if ( '_application_passwords' !== $meta_key ) {
		return $check;
	}

	return $single ? '' : [];
}

add_filter( 'add_user_metadata', 'secupress_freeze_application_passwords_meta', 0, 4 );
add_filter( 'update_user_metadata', 'secupress_freeze_application_passwords_meta', 0, 4 );
add_filter( 'delete_user_metadata', 'secupress_freeze_application_passwords_meta', 0, 4 );
/**
 * Freeze Application Passwords: no create, update, or delete via PHP.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param $check (null|bool)
 * @param $object_id (int)
 * @param $meta_key (string)
 *
 * @return (null|bool)
 */
function secupress_freeze_application_passwords_meta( $check, $object_id, $meta_key ) {
	if ( '_application_passwords' !== $meta_key ) {
		return $check;
	}

	return false;
}
