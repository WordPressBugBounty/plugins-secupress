<?php
/**
 * Module Name: Same Email Domain
 * Description: Prevent users to use the website domain to create an account
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.2.6
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'user_profile_update_errors', 'secupress_same_email_domain_validate_user_creation', 10, 3 );
/**
 * Triggers an error on same email domain on backend creation or update
 * 
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param (WP_Error) $errors Registration errors.
 * @param (bool) $update 
 * @param (WP_User) $user
 * 
 * @return (WP_Error) Registration errors
 */
function secupress_same_email_domain_validate_user_creation( $errors, $update, $user ) {
	$email   = secupress_same_email_domain_posted_email();
	$email   = $email ? $email : $user->user_email;
	$user_id = $update ? (int) $user->ID : 0;
	if ( ! secupress_same_email_domain_is_disallowed( $email, $user_id ) ) {
		return $errors;
	}
	/**
	 * Filter the message on same domain email registration
	 *
	 * @since 2.2.6
	 *
	 * @param $errors
	 * @param $sanitized_user_login
	 * @param $user_email
	 */
	$message = secupress_same_email_domain_rejection_message( $errors, $update, $email );
	// secupress_log_attack( 'users' ); // DO NOT LOG AS AN ATTACK, we are in backoffice, this can just be an admin mistake.
	$errors->add( 'registerfail', $message );

	return $errors;
}

/**
 * Remember a profile email that must still be checked after WordPress rewrites $_POST.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string|null) $email Email to store. Null only reads the stored value.
 *
 * @return (string)
 */
function secupress_same_email_domain_posted_email( $email = null ) {
	static $posted = '';

	if ( is_string( $email ) ) {
		$posted = $email;
	}

	return $posted;
}

/**
 * Tell whether an email uses the site domain and is not grandfathered.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $email   Email address.
 * @param (int)    $user_id User ID when updating an existing account.
 *
 * @return (bool)
 */
function secupress_same_email_domain_is_disallowed( $email, $user_id = 0 ) {
	if ( ! is_string( $email ) || false === strpos( $email, '@' ) || ! secupress_email_domain_is_same( $email ) ) {
		return false;
	}

	$user_id = (int) $user_id;
	if ( $user_id && get_user_meta( $user_id, SECUPRESS_SAME_EMAIL_DOMAIN_OK, true ) ) {
		return false;
	}

	return true;
}

/**
 * Rejection message shared by the profile form, its confirmation link, and the REST API.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (WP_Error) $errors   Error object passed to the legacy filter.
 * @param (bool)     $update   True when updating an existing user.
 * @param (string)   $email    Rejected email address.
 *
 * @return (string)
 */
function secupress_same_email_domain_rejection_message( $errors, $update, $email ) {
	return apply_filters( 'secupress.plugins.same_email_domain.backend.message', __( '<strong>Error</strong>: The email address is not correct.', 'secupress' ), $errors, $update, $email );
}

add_action( 'personal_options_update', 'secupress_same_email_domain_preserve_profile_check', 1 );
/**
 * Keep a forbidden profile email visible to user_profile_update_errors.
 *
 * WordPress sends the confirmation mail and puts the current address back into
 * $_POST before edit_user() runs, so the policy never saw the requested address.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (int) $user_id Edited user ID.
 */
function secupress_same_email_domain_preserve_profile_check( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return;
	}

	check_admin_referer( 'update-user_' . $user_id );

	if ( ! isset( $_POST['email'] ) || ! is_string( $_POST['email'] ) ) {
		return;
	}

	$email = trim( wp_unslash( $_POST['email'] ) );
	if ( ! secupress_same_email_domain_is_disallowed( $email, $user_id ) ) {
		return;
	}

	secupress_same_email_domain_posted_email( $email );
	remove_action( 'personal_options_update', 'send_confirmation_on_profile_email' );
}

add_action( 'personal_options_update', 'secupress_same_email_domain_drop_pending_email', 11 );
/**
 * Drop a pending confirmation when its address is forbidden.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (int) $user_id Edited user ID.
 */
function secupress_same_email_domain_drop_pending_email( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return;
	}

	$pending = get_user_meta( $user_id, '_new_email', true );
	if ( ! is_array( $pending ) || empty( $pending['newemail'] ) || ! is_string( $pending['newemail'] ) ) {
		return;
	}
	if ( ! secupress_same_email_domain_is_disallowed( $pending['newemail'], $user_id ) ) {
		return;
	}

	delete_user_meta( $user_id, '_new_email' );
}

add_action( 'admin_init', 'secupress_same_email_domain_block_confirmed_email' );
/**
 * Re-check the policy when the profile confirmation link applies the new address.
 *
 * Core applies that address with wp_update_user(), which does not run user_profile_update_errors.
 *
 * @since 2.7.4
 * @author Julio Potier
 */
function secupress_same_email_domain_block_confirmed_email() {
	global $pagenow;

	if ( ! isset( $_GET['newuseremail'] ) || ! is_string( $_GET['newuseremail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! in_array( $pagenow, [ 'profile.php', 'user-edit.php' ], true ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	$new_email = get_user_meta( $user_id, '_new_email', true );
	if ( ! is_array( $new_email ) || empty( $new_email['hash'] ) || empty( $new_email['newemail'] ) || ! is_string( $new_email['hash'] ) || ! is_string( $new_email['newemail'] ) ) {
		return;
	}
	if ( ! hash_equals( $new_email['hash'], wp_unslash( $_GET['newuseremail'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! secupress_same_email_domain_is_disallowed( $new_email['newemail'], $user_id ) ) {
		return;
	}

	delete_user_meta( $user_id, '_new_email' );
	wp_safe_redirect( add_query_arg( 'secupress-same-email-domain', '1', self_admin_url( 'profile.php' ) ) );
	exit;
}

add_action( 'admin_notices', 'secupress_same_email_domain_confirmed_email_notice' );
/**
 * Explain why a profile confirmation link was refused.
 *
 * @since 2.7.4
 * @author Julio Potier
 */
function secupress_same_email_domain_confirmed_email_notice() {
	global $pagenow;

	if ( empty( $_GET['secupress-same-email-domain'] ) || 'profile.php' !== $pagenow ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$message = secupress_same_email_domain_rejection_message( new WP_Error(), true, '' );
	echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
}

add_filter( 'rest_request_before_callbacks', 'secupress_same_email_domain_rest_pre_user', 10, 3 );
/**
 * Refuse a site-domain email sent through the users REST controller.
 *
 * A subscriber can edit their own account here, and WordPress saves that email immediately.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (mixed)           $response Current REST response.
 * @param (array)           $handler  Matched route handler.
 * @param (WP_REST_Request) $request  REST request.
 *
 * @return (mixed|WP_Error)
 */
function secupress_same_email_domain_rest_pre_user( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request || empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
		return $response;
	}

	$callback   = $handler['callback'];
	$controller = isset( $callback[0] ) ? $callback[0] : null;
	$method     = isset( $callback[1] ) ? $callback[1] : '';
	$is_users   = ( is_object( $controller ) && $controller instanceof WP_REST_Users_Controller ) || 'WP_REST_Users_Controller' === $controller;
	if ( ! $is_users || ! in_array( $method, [ 'create_item', 'update_item', 'update_current_item' ], true ) ) {
		return $response;
	}

	$email = $request->get_param( 'email' );
	if ( ! is_string( $email ) || '' === $email ) {
		return $response;
	}

	if ( 'update_current_item' === $method ) {
		$user_id = get_current_user_id();
	} elseif ( 'update_item' === $method ) {
		$user_id = (int) $request->get_param( 'id' );
	} else {
		$user_id = 0;
	}

	if ( ! secupress_same_email_domain_is_disallowed( $email, $user_id ) ) {
		return $response;
	}

	// secupress_log_attack( 'users' ); // DO NOT LOG AS AN ATTACK, same choice as the profile form.
	return new WP_Error(
		'rest_user_invalid_email',
		wp_strip_all_tags( secupress_same_email_domain_rejection_message( new WP_Error(), (bool) $user_id, $email ) ),
		[ 'status' => 400 ]
	);
}

add_action( 'secupress.modules.activate_submodule_same-email-domain', 'secupress_same_email_domain_add_ok_meta', 10, 1 );
/**
 * On first activation, whitelist existing users whose email matches the site domain.
 *
 * @since 2.7.3 Accept a string or an array of domains.
 * @since 2.6.2
 * @author Julio Potier
 *
 * @param (bool) $is_active True if the sub-module was already active.
 */
function secupress_same_email_domain_add_ok_meta( $is_active ) {
	if ( $is_active ) {
		return;
	}
	global $wpdb;

	$website_domain = secupress_get_current_url( 'domain', 'same_email_domain' );
	$website_domain = array_values( array_filter( array_map( 'strval', (array) $website_domain ) ) );
	if ( ! $website_domain ) {
		return;
	}

	$conditions = [];
	$values     = [
		SECUPRESS_SAME_EMAIL_DOMAIN_OK,
		'1',
		SECUPRESS_SAME_EMAIL_DOMAIN_OK,
	];
	foreach ( $website_domain as $domain ) {
		$conditions[] = 'u.user_email LIKE %s';
		$values[]     = '%@' . $wpdb->esc_like( $domain );
	}

	$query = $wpdb->prepare(
		"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
		SELECT u.ID, %s, %s
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
		WHERE ( " . implode( ' OR ', $conditions ) . ' ) AND um.umeta_id IS NULL',
		$values
	);

	$wpdb->query( $query ); // WPCS: unprepared SQL ok.
	secupress_flush_user_meta_cache();
}

add_action( 'secupress.modules.deactivate_submodule_same-email-domain', 'secupress_same_email_domain_remove_ok_meta' );
/**
 * On deactivation, remove the whitelist meta from all users.
 *
 * @since 2.6.2
 * @author Julio Potier
 */
function secupress_same_email_domain_remove_ok_meta() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", SECUPRESS_SAME_EMAIL_DOMAIN_OK ) ); // WPCS: unprepared SQL ok.
	secupress_flush_user_meta_cache();
}

add_filter( 'registration_errors', 'secupress_same_email_domain_validate_user_registration', 10, 3 );
/**
 * Triggers an error on same email domain on frontend registration
 * 
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param (WP_Error) $errors Registration errors.
 * @param (string) $sanitized_user_login The sanitized user login.
 * @param (string) $user_email The user email address.
 * 
 * @return (WP_Error) Registration errors
 */
function secupress_same_email_domain_validate_user_registration( $errors, $sanitized_user_login, $user_email ) {
	if ( secupress_email_domain_is_same( $user_email ) ) {
		/**
		 * Filter the message on same domain email registration
		 * 
		 * @since 2.2.6
		 * 
		 * @param $errors
		 * @param $sanitized_user_login
		 * @param $user_email
		 */
		$message = apply_filters( 'secupress.plugins.same_email_domain.frontend.message', __( '<strong>Error</strong>: User registration is currently not allowed.', 'secupress' ), $errors, $sanitized_user_login, $user_email );
		secupress_log_attack( 'users' );
		$errors->add( 'registerfail', $message );
	}

	return $errors;
}
