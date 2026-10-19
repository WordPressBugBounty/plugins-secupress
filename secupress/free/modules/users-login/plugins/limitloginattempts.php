<?php
/**
 * Module Name: Limit Login Attempts
 * Description: Limit login attempts and ban if too many tries have been done.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.2.6
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/**
 * Check if we have to ban the IP and redirect
 *
 * @since 2.2.6
 * @author Julio Potier
 * 
 * @param (int)  $user_id 
 * @param (bool) $redirect
 * 
 * @return (int) $user_attempts
 */
function secupress_limitloginattempts_check_max_and_die( $user_id, $redirect = true ) {
	$user_attempts = (int) get_user_meta( $user_id, '_secupress_limitloginattempts', true );
	$max_attempts  = secupress_get_module_option( 'login-protection_number_attempts', 10, 'users-login' );
	if ( $user_attempts >= $max_attempts ) {
		/**
		 * Let anyone do what they need before our die
		 * /!\ This function in not used by us, use it when you need!!
		 * 
		 * @since 2.2.6
		 * @author Julio Potier
		 * 
		 * @param (int) $user_id
		 */ 
		do_action( 'secupress.plugin.limitloginattempts.check', $user_id );

		secupress_ban_ip( (int) secupress_get_module_option( 'login-protection_time_ban', 5, 'users-login' ) );
		if ( $redirect ) {
			wp_safe_redirect( secupress_get_current_url() );
			die();
		}
	}
	return $user_attempts;
}

/**
 * Add 1 login attempt to a user
 *
 * @since 2.2.6
 * @author Julio Potier
 * 
 * @param (int)      $user_id 
 * 
 * @return (int) $user_attempts
 */
function secupress_limitloginattempts_add_one_try( $user_id ) {
	global $wpdb;

	$max_attempts = secupress_get_module_option( 'login-protection_number_attempts', 10, 'users-login' );
	$ban_time     = (int) secupress_get_module_option( 'login-protection_time_ban', 5, 'users-login' );

	// Start transaction
	$wpdb->query("START TRANSACTION");

	// Get the number of attempts (line lock with FOR UPDATE)
	$user_attempts = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_secupress_limitloginattempts' AND user_id = %d LIMIT 1 FOR UPDATE",
			$user_id
		)
	);

	// If the user_attempts is null, it means the user has not attempted to login before, so we initialize it to 0
	if ( is_null( $user_attempts ) ) {
		$user_attempts = 0;
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $user_id,
				'meta_key'   => '_secupress_limitloginattempts',
				'meta_value' => $user_attempts,
			)
		);
	}

	++$user_attempts;

	if ( $user_attempts >= $max_attempts ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = '_secupress_passwordspraying'", $user_id ) );
		secupress_ban_ip( $ban_time );
	}

	// Update number of attempts
	$wpdb->update(
		$wpdb->usermeta,
		array(
			'meta_value' => $user_attempts,
		),
		array(
			'user_id'  => $user_id,
			'meta_key' => '_secupress_limitloginattempts',
		)
	);

	// End transaction with a COMMIT command
	$wpdb->query("COMMIT");

	return $user_attempts;
}

add_action( 'set_auth_cookie', 'secupress_limitloginattempts_clear_attempts_on_cookie', 10, 4 );
/**
 * Clear the attempt counter when WordPress sets the auth cookie.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $auth_cookie Authentication cookie value.
 * @param (int)    $expire      Grace period expiration timestamp.
 * @param (int)    $expiration  Cookie expiration timestamp.
 * @param (int)    $user_id     User ID.
 */
function secupress_limitloginattempts_clear_attempts_on_cookie( $auth_cookie, $expire, $expiration, $user_id ) {
	secupress_limitloginattempts_clear_attempts( $user_id );
}

/**
 * A successful login clears the bad-attempt counter, whichever path opened the session.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int) $user_id
 */
function secupress_limitloginattempts_clear_attempts( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return;
	}

	delete_user_meta( $user_id, '_secupress_limitloginattempts' );
}

add_action( 'authenticate', 'secupress_limitloginattempts', SECUPRESS_INT_MAX, 2 );
/**
 * Count failed logins for a real account, and the same way for an unknown identifier.
 *
 * @since 2.7.2 Unknown identifiers are counted in a transient, with the same limit and the same ban.
 * @since 2.2.6 Refactoring
 * @since 1.0
 *
 * @param (null|object) $raw_user WP_User if the user is authenticated.
 *                                WP_Error or null otherwise.
 * @param (string)      $username Username or email address.
 *
 * @return (null|object)
 */
function secupress_limitloginattempts( $raw_user, $username ) {
	static $done = false;

	if ( $done ) {
		return $raw_user;
	}
	$done = true;

	if ( empty( $_POST ) || secupress_ip_is_whitelisted() ) { // WPCS: CSRF ok.
		return $raw_user;
	}

	if ( isset( $_POST['passwordless_get_magik_link'] ) && function_exists( 'secupress_passwordless_is_activated' ) && secupress_is_submodule_active( 'users-login', 'passwordless' ) && secupress_passwordless_is_activated() ) { // WPCS: CSRF ok.
		return $raw_user;
	}

	if ( ! is_wp_error( $raw_user ) ) {
		if ( ! empty( $raw_user->ID ) ) {
			secupress_limitloginattempts_clear_attempts( $raw_user->ID );
		}

		return $raw_user;
	}

	$username = is_string( $username ) ? $username : '';
	$user     = secupress_limitloginattempts_get_user( $username );
	if ( $user ) {
		secupress_limitloginattempts_after_increment( secupress_limitloginattempts_add_one_try( $user->ID ) );
		return $raw_user;
	}

	if ( '' === trim( $username ) || ! secupress_is_submodule_active( 'users-login', 'stop-user-enumeration' ) ) {
		return $raw_user;
	}

	secupress_limitloginattempts_add_unknown_try( $username );

	return $raw_user;
}

/**
 * Find the account targeted by a login attempt.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $username Login or email.
 *
 * @return (WP_User|false)
 */
function secupress_limitloginattempts_get_user( $username ) {
	$username = trim( (string) $username );
	if ( '' === $username ) {
		return false;
	}

	$user = get_user_by( 'login', $username );
	if ( ! $user && is_email( $username ) ) {
		$user = get_user_by( 'email', $username );
	}

	return secupress_is_user( $user ) ? $user : false;
}

/**
 * Ban or warn once the shared attempt limit is reached.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int) $user_attempts Attempts already stored.
 */
function secupress_limitloginattempts_after_increment( $user_attempts ) {
	$max_attempts       = (int) secupress_get_module_option( 'login-protection_number_attempts', 10, 'users-login' );
	$user_attempts      = (int) $user_attempts;
	$user_attempts_left = $max_attempts - $user_attempts;

	if ( $user_attempts >= $max_attempts ) {
		secupress_ban_ip( (int) secupress_get_module_option( 'login-protection_time_ban', 5, 'users-login' ) );
		wp_safe_redirect( secupress_get_current_url() );
		die();
	}

	if ( $user_attempts_left <= 3 ) {
		secupress_cache_data( 'limitloginattempts_user_attempts_left', $user_attempts_left );
		add_filter( 'login_message', 'secupress_limitloginattempts_error_message' );
	}
}

/**
 * Hash an unknown identifier so the transient never stores it in clear.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $username Login or email as typed.
 *
 * @return (string)
 */
function secupress_limitloginattempts_unknown_hash( $username ) {
	return hash_hmac( 'sha256', strtolower( trim( (string) $username ) ), wp_salt( 'auth' ) );
}

/**
 * Count one failed login on an identifier that has no account.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $username Login or email as typed.
 */
function secupress_limitloginattempts_add_unknown_try( $username ) {
	$bag          = secupress_get_site_transient( 'secupress_lla_unknown' );
	$bag          = is_array( $bag ) ? $bag : [];
	$hash         = secupress_limitloginattempts_unknown_hash( $username );
	$bag[ $hash ] = isset( $bag[ $hash ] ) ? (int) $bag[ $hash ] + 1 : 1;
	secupress_set_site_transient( 'secupress_lla_unknown', $bag );
	secupress_limitloginattempts_after_increment( $bag[ $hash ] );
}



/**
 * Append our error message.
 *
 * @since 1.0
 * @since 1.1 The 2nd argument, `$user_attempts_left`, has been removed. Its value is retrieved with `secupress_cache_data()` instead.
 *
 * @param (string) $message Previous messages.
 *
 * @return (string) html message
 */
function secupress_limitloginattempts_error_message( $message ) {
	$user_attempts_left = secupress_cache_data( 'limitloginattempts_user_attempts_left' );

	if ( ! isset( $user_attempts_left ) ) {
		return $message;
	}

	return $message . '<p class="message">' . sprintf( _n( 'Login failed, <strong>%d</strong> attempt left.', 'Login failed, <strong>%d</strong> attempts left.', $user_attempts_left, 'secupress' ), $user_attempts_left ) . '</p><br>';
}
