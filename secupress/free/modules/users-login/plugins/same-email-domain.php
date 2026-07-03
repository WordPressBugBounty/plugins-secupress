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
	if ( secupress_email_domain_is_same( $user->user_email ) ) {
		if ( $update && get_user_meta( $user->ID, SECUPRESS_SAME_EMAIL_DOMAIN_OK, true ) ) {
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
		$message = apply_filters( 'secupress.plugins.same_email_domain.backend.message', __( '<strong>Error</strong>: The email address is not correct.', 'secupress' ), $errors, $update, $user->user_email );
		// secupress_log_attack( 'users' ); // DO NOT LOG AS AN ATTACK, we are in backoffice, this can just be an admin mistake.
		$errors->add( 'registerfail', $message );
	}

	return $errors;
}

add_action( 'secupress.modules.activate_submodule_same-email-domain', 'secupress_same_email_domain_add_ok_meta', 10, 1 );
/**
 * On first activation, whitelist existing users whose email matches the site domain.
 *
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

	$website_domain = secupress_get_current_url( 'domain' );
	if ( ! $website_domain ) {
		return;
	}

	$like  = '%@' . $wpdb->esc_like( $website_domain );
	$query = $wpdb->prepare(
		"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
		SELECT u.ID, %s, %s
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
		WHERE u.user_email LIKE %s AND um.umeta_id IS NULL",
		SECUPRESS_SAME_EMAIL_DOMAIN_OK,
		'1',
		SECUPRESS_SAME_EMAIL_DOMAIN_OK,
		$like
	);

	$wpdb->query( $query ); // WPCS: unprepared SQL ok.
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
